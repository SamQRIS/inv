<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Warehouse;
use App\Services\DiscountService;
use App\Services\PaymentService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TransactionService — versi multi-warehouse
 *
 * Setiap item transaksi WAJIB menyertakan warehouse_id:
 * - stok dicek per gudang
 * - dikurangi dari gudang yang dipilih saat DO/shipment
 *
 * data['items'] contoh:
 * [
 *   {
 *     product_id:   1,
 *     warehouse_id: 2,   ← WAJIB
 *     quantity:     5,
 *     unit_price:   50000,
 *   }
 * ]
 */
class TransactionService
{
    public function __construct(
        protected DiscountService $discountService,
        protected StockService    $stockService,
        protected PaymentService  $paymentService,
    ) {}

    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {

            // 1. Resolve warehouse default jika tidak disertakan
            $defaultWarehouse = Warehouse::where('is_default', true)->where('is_active', true)->first()
                ?? Warehouse::where('is_active', true)->orderBy('sort_order')->first();

            if (!$defaultWarehouse) {
                throw ValidationException::withMessages([
                    'items' => 'Belum ada gudang aktif. Silakan buat gudang terlebih dahulu di menu Master Data → Gudang.',
                ]);
            }

            // 2. Resolve customer
            $customer = $this->resolveCustomer($data);

            // 3. Hitung subtotal
            $subtotal = collect($data['items'])->sum(fn($i) => $i['quantity'] * $i['unit_price']);

            // 4. Apply diskon
            $discountResult = $this->discountService->calculateGrandTotal($subtotal, $data['discount_json'] ?? null);
            $grandTotal     = $discountResult['after_discount'];
            $discountAmount = $discountResult['discount_amount'];
            $discountJson   = $data['discount_json'] ?? null;
            $discountType   = $this->discountService->resolveDiscountType($discountJson);

            // 5. Validasi tidak overpay
            $totalPayments = collect($data['payments'] ?? [])->sum('amount');
            if ($totalPayments > $grandTotal) {
                throw ValidationException::withMessages([
                    'payments' => "Total pembayaran melebihi grand total.",
                ]);
            }

            // 6. Buat transaksi
            $transaction = Transaction::create([
                'invoice_number'   => Transaction::generateInvoiceNumber(),
                'customer_id'      => $customer?->id,
                'user_id'          => Auth::id(),
                'transaction_date' => $data['transaction_date'],
                'delivery_date'    => $data['delivery_date'] ?? null,
                'delivery_note'    => $data['delivery_note'] ?? null,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discountAmount,
                'grand_total'      => $grandTotal,
                'amount_paid'      => 0,
                'amount_remaining' => $grandTotal,
                'discount_type'    => $discountType,
                'discount_json'    => $discountJson,
                'payment_status'   => 'unpaid',
                'delivery_status'  => 'pending',
                'notes'            => $data['notes'] ?? null,
            ]);

            // 7. Buat transaction items
            foreach ($data['items'] as $item) {
                $product   = Product::findOrFail($item['product_id']);
                // Gunakan warehouse dari item, fallback ke default
                $warehouse = isset($item['warehouse_id'])
                    ? Warehouse::findOrFail($item['warehouse_id'])
                    : $defaultWarehouse;

                // Validasi stok di gudang yang dipilih
                // (pengurangan stok terjadi saat shipment, bukan saat transaksi)
                // Tapi kita tetap validasi ketersediaan stok secara soft di sini
                $this->stockService->ensureStockAvailable($product, $item['quantity'], $warehouse);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'warehouse_id'   => $warehouse->id,   // ← simpan gudang asal
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'unit_name'      => $product->unit->symbol,
                    'unit_price'     => $item['unit_price'],
                    'quantity'       => $item['quantity'],
                    'subtotal'       => $item['quantity'] * $item['unit_price'],
                    'notes'          => $item['notes'] ?? null,
                ]);
            }

            // 8. Proses pembayaran
            if (!empty($data['payments'])) {
                $this->paymentService->processPayments($transaction, $data['payments']);
            }

            return $transaction->fresh(['items', 'payments']);
        });
    }

    private function resolveCustomer(array $data): ?Customer
    {
        if (($data['customer_type'] ?? '') === 'end_user') {
            return Customer::findOrCreateEndUser($data['customer_name'], $data['customer_phone'], $data['customer_address'] ?? null);
        }
        if (!empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }
        return null;
    }
}