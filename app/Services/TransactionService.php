<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\DepositService;
use App\Services\DiscountService;
use App\Services\PaymentService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        protected DiscountService $discountService,
        protected StockService    $stockService,
        protected PaymentService  $paymentService,
        protected DepositService  $depositService,
    ) {}

    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {

            // 1. Resolve warehouse default
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

            // 5. Validasi deposit untuk customer DO
            // $data['admin_override'] = true artinya admin sudah approve meski deposit tidak cukup
            if ($customer && $customer->type === 'do') {
                $adminOverride = (bool) ($data['admin_override'] ?? false);
                $this->depositService->validateSufficientDeposit($customer, $grandTotal, $adminOverride);
            }

            // 6. Validasi overpayment untuk end user
            $totalPayments = collect($data['payments'] ?? [])->sum(fn($p) => (float)($p['amount'] ?? 0));
            if ((!$customer || $customer->type !== 'do') && $totalPayments > $grandTotal) {
                throw ValidationException::withMessages([
                    'payments' => 'Total pembayaran melebihi grand total.',
                ]);
            }

            // 7. Buat transaksi
            $transaction = Transaction::create([
                'invoice_number'   => $data['invoice_number'],
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

            // 8. Buat transaction items
            foreach ($data['items'] as $item) {
                $product   = Product::findOrFail($item['product_id']);
                $warehouse = isset($item['warehouse_id'])
                    ? Warehouse::findOrFail($item['warehouse_id'])
                    : $defaultWarehouse;

                $available    = $product->stockAt($warehouse);
                $qty          = (int) $item['quantity'];
                $isBackorder  = $available < $qty;
                $qtyBackorder = $isBackorder ? ($qty - $available) : 0;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'warehouse_id'   => $warehouse->id,
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'unit_name'      => $product->unit->symbol,
                    'unit_price'     => $item['unit_price'],
                    'quantity'       => $qty,
                    'subtotal'       => $qty * $item['unit_price'],
                    'notes'          => $item['notes'] ?? null,
                    'is_backorder'   => $isBackorder,
                    'qty_backorder'  => $qtyBackorder,
                ]);

                if ($isBackorder) {
                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('Stok Tidak Mencukupi — Backorder')
                        ->body("{$product->name}: tersedia {$available}, dibutuhkan {$qty}. Kekurangan {$qtyBackorder} unit ditandai backorder.")
                        ->persistent()
                        ->send();
                }
            }

            // 9. Proses pembayaran tunai (jika ada, misalnya end user)
            if (!empty($data['payments'])) {
                $this->paymentService->processPayments($transaction, $data['payments']);
            }

            // 10. Auto-apply deposit untuk customer DO
            if ($customer && $customer->type === 'do' && $customer->hasDeposit()) {
                $this->paymentService->applyDeposit($transaction);
            }

            // 11. HAPUS: credit usage tidak lagi dicatat — sistem sudah pure deposit
            // (credit_used dibiarkan 0, credit_limit tidak dipakai)

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