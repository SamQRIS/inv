<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Warehouse;
use App\Services\DiscountService;
use App\Services\PaymentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(
        protected DiscountService $discountService,
        protected PaymentService  $paymentService,
    ) {}

    // =========================================================
    // CREATE — input list order dari WA customer
    // =========================================================

    public function create(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);

            if ($customer->type !== 'do') {
                throw ValidationException::withMessages([
                    'customer_id' => 'Sales Order hanya untuk customer DO.',
                ]);
            }

            $items    = $data['items'] ?? [];
            $subtotal = collect($items)->sum(fn($i) => ($i['quantity'] ?? 0) * ($i['unit_price'] ?? 0));

            // Diskon opsional
            $discountResult = $this->discountService->calculateGrandTotal($subtotal, $data['discount_json'] ?? null);
            $grandTotal     = $discountResult['after_discount'];

            $so = SalesOrder::create([
                'so_number'               => SalesOrder::generateSoNumber(),
                'customer_id'             => $customer->id,
                'user_id'                 => Auth::id(),
                'order_date'              => $data['order_date'],
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? null,
                'status'                  => 'draft',
                'grand_total'             => $grandTotal,
                'notes'                   => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = Product::with('unit')->findOrFail($item['product_id']);

                SalesOrderItem::create([
                    'sales_order_id' => $so->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'unit_name'      => $product->unit->symbol,
                    'quantity'       => (int) $item['quantity'],
                    'unit_price'     => (float) ($item['unit_price'] ?? 0),
                    'subtotal'       => (int) $item['quantity'] * (float) ($item['unit_price'] ?? 0),
                    'notes'          => $item['notes'] ?? null, // warna, ukuran, spec khusus
                ]);
            }

            return $so->load('items');
        });
    }

    // =========================================================
    // CONFIRM — konfirmasi ke customer bahwa order diterima
    // =========================================================

    public function confirm(SalesOrder $so): SalesOrder
    {
        if (!$so->canConfirm()) {
            throw ValidationException::withMessages([
                'status' => 'SO hanya bisa dikonfirmasi dari status Draft.',
            ]);
        }

        $so->update(['status' => 'confirmed']);

        Notification::make()
            ->success()
            ->title('SO ' . $so->so_number . ' Dikonfirmasi')
            ->body('Order diterima dan sedang diproses/diproduksi.')
            ->send();

        return $so->fresh();
    }

    // =========================================================
    // CONVERT → buat Transaksi (saat barang siap dikirim)
    // Harga bisa diubah saat convert jika belum fix
    // =========================================================

    public function convertToTransaction(SalesOrder $so, array $overrideData = []): Transaction
    {
        if (!$so->canConvert()) {
            throw ValidationException::withMessages([
                'status' => 'SO yang sudah dibatalkan tidak bisa dikonvert.',
            ]);
        }

        if ($so->isConverted()) {
            throw ValidationException::withMessages([
                'status' => 'SO ini sudah pernah dikonvert menjadi transaksi.',
            ]);
        }

        return DB::transaction(function () use ($so, $overrideData) {
            $customer = $so->customer;

            // Pakai item dari SO, tapi harga bisa di-override saat convert
            $items    = $overrideData['items'] ?? $so->items->toArray();
            $subtotal = collect($items)->sum(fn($i) => ($i['quantity'] ?? 0) * ($i['unit_price'] ?? 0));

            $discountJson   = $overrideData['discount_json'] ?? null;
            $discountResult = $this->discountService->calculateGrandTotal($subtotal, $discountJson);
            $grandTotal     = $discountResult['after_discount'];
            $discountAmount = $discountResult['discount_amount'];

            // Cek deposit jika customer DO
            if ($customer->type === 'do' && $customer->depositBalance() < $grandTotal) {
                $override = (bool) ($overrideData['admin_override'] ?? false);
                if (!$override) {
                    throw ValidationException::withMessages([
                        'deposit' => 'Deposit ' . $customer->name . ' tidak mencukupi (Rp ' .
                            number_format($customer->depositBalance(), 0, ',', '.') . '). ' .
                            'Centang override admin untuk tetap lanjutkan.',
                    ]);
                }
            }

            // Cari default warehouse
            $defaultWarehouse = Warehouse::where('is_default', true)->where('is_active', true)->first()
                ?? Warehouse::where('is_active', true)->orderBy('sort_order')->first();

            // Buat Transaksi
            $transaction = Transaction::create([
                'invoice_number'   => Transaction::generateInvoiceNumber(),
                'customer_id'      => $customer->id,
                'user_id'          => Auth::id(),
                'transaction_date' => today()->toDateString(),
                'delivery_note'    => $overrideData['delivery_note'] ?? null,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discountAmount,
                'grand_total'      => $grandTotal,
                'amount_paid'      => 0,
                'amount_remaining' => $grandTotal,
                'discount_type'    => $this->discountService->resolveDiscountType($discountJson),
                'discount_json'    => $discountJson,
                'payment_status'   => 'unpaid',
                'delivery_status'  => 'pending',
                'notes'            => 'Dari SO ' . $so->so_number . ($so->notes ? '. ' . $so->notes : ''),
            ]);

            // Buat TransactionItems
            foreach ($items as $item) {
                $product   = Product::findOrFail($item['product_id'] ?? $item['product']['id'] ?? null);
                $warehouse = $defaultWarehouse;

                // Cek stok — jika kurang → backorder (bukan blok)
                $available    = $product->stockAt($warehouse);
                $qty          = (int) ($item['quantity'] ?? 1);
                $isBackorder  = $available < $qty;
                $qtyBackorder = $isBackorder ? ($qty - $available) : 0;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'warehouse_id'   => $warehouse->id,
                    'product_name'   => $item['product_name'] ?? $product->name,
                    'product_sku'    => $product->sku ?? null,
                    'unit_name'      => $item['unit_name'] ?? $product->unit->symbol,
                    'unit_price'     => (float) ($item['unit_price'] ?? 0),
                    'quantity'       => $qty,
                    'subtotal'       => $qty * (float) ($item['unit_price'] ?? 0),
                    'is_backorder'   => $isBackorder,
                    'qty_backorder'  => $qtyBackorder,
                    'notes'          => $item['notes'] ?? null,
                ]);

                if ($isBackorder) {
                    Notification::make()
                        ->warning()
                        ->title('Stok Kurang — Backorder')
                        ->body("{$product->name}: tersedia {$available}, dibutuhkan {$qty}. Kekurangan {$qtyBackorder} unit di-backorder.")
                        ->persistent()
                        ->send();
                }
            }

            // Apply deposit otomatis untuk customer DO
            if ($customer->type === 'do' && $customer->hasDeposit()) {
                $this->paymentService->applyDeposit($transaction);
            }

            // Update SO → converted
            $so->update([
                'status'         => 'converted',
                'transaction_id' => $transaction->id,
                'grand_total'    => $grandTotal, // update jika harga berubah saat convert
            ]);

            Notification::make()
                ->success()
                ->title('Transaksi ' . $transaction->invoice_number . ' Berhasil Dibuat')
                ->body('SO ' . $so->so_number . ' sudah dikonvert. Deposit terpotong otomatis.')
                ->send();

            return $transaction;
        });
    }

    // =========================================================
    // CANCEL
    // =========================================================

    public function cancel(SalesOrder $so, string $reason): SalesOrder
    {
        if (!$so->canCancel()) {
            throw ValidationException::withMessages([
                'status' => 'SO yang sudah dikonvert atau dibatalkan tidak bisa dibatalkan.',
            ]);
        }

        $so->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        Notification::make()
            ->warning()
            ->title('SO ' . $so->so_number . ' Dibatalkan')
            ->send();

        return $so->fresh();
    }
}