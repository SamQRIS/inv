<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\CreditService;
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
        protected CreditService   $creditService,
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

            // 5. Hitung total payment yang akan dibayar sekarang
            $totalPayments = collect($data['payments'] ?? [])->sum(fn($p) => (float)($p['amount'] ?? 0));

            // 6. Untuk customer DO: pertimbangkan deposit yang akan dipakai
            $depositToUse = 0;
            if ($customer && $customer->type === 'do' && $customer->hasDeposit()) {
                $remainingAfterPayment = max(0, $grandTotal - $totalPayments);
                $depositToUse = min($customer->depositBalance(), $remainingAfterPayment);
            }

            // 7. Validasi credit limit — hitung sisa setelah payment + deposit
            if ($customer && $customer->type === 'do' && $customer->credit_limit > 0) {
                $unpaidAmount = max(0, $grandTotal - $totalPayments - $depositToUse);
                if ($unpaidAmount > 0) {
                    $available = $customer->availableCredit();
                    if ($unpaidAmount > $available) {
                        throw ValidationException::withMessages([
                            'customer_id' => sprintf(
                                'Credit limit %s tidak mencukupi. Sisa kredit: Rp %s, Dibutuhkan: Rp %s. ' .
                                    'Bayar DP atau hubungi admin untuk top up kredit.',
                                $customer->name,
                                number_format($available, 0, ',', '.'),
                                number_format($unpaidAmount, 0, ',', '.')
                            ),
                        ]);
                    }
                }
            }

            // 8. Validasi overpayment untuk end user
            if ((!$customer || $customer->type !== 'do') && $totalPayments > $grandTotal) {
                throw ValidationException::withMessages([
                    'payments' => 'Total pembayaran melebihi grand total.',
                ]);
            }

            // 9. Buat transaksi
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

            // 10. Buat transaction items
            foreach ($data['items'] as $item) {
                $product   = Product::findOrFail($item['product_id']);
                $warehouse = isset($item['warehouse_id'])
                    ? Warehouse::findOrFail($item['warehouse_id'])
                    : $defaultWarehouse;

                $this->stockService->ensureStockAvailable($product, $item['quantity'], $warehouse);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'warehouse_id'   => $warehouse->id,
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'unit_name'      => $product->unit->symbol,
                    'unit_price'     => $item['unit_price'],
                    'quantity'       => $item['quantity'],
                    'subtotal'       => $item['quantity'] * $item['unit_price'],
                    'notes'          => $item['notes'] ?? null,
                ]);
            }

            // 11. Proses pembayaran tunai
            if (!empty($data['payments'])) {
                $this->paymentService->processPayments($transaction, $data['payments']);
            }

            // 12. ✅ Auto-apply deposit jika customer DO punya saldo deposit
            if ($depositToUse > 0) {
                $this->paymentService->applyDeposit($transaction);
            }

            // 13. Catat penggunaan credit untuk sisa yang belum dibayar
            $transaction->refresh();
            if ($customer && $customer->type === 'do' && (float) $transaction->amount_remaining > 0) {
                $this->creditService->recordUsage(
                    $customer,
                    (float) $transaction->amount_remaining,
                    $transaction->id
                );
            }

            return $transaction->fresh(['items', 'payments']);
        });
    }

    private function resolveCustomer(array $data): ?Customer
    {
        if (($data['customer_type'] ?? '') === 'end_user') {
            return Customer::findOrCreateEndUser($data['customer_name'], $data['customer_phone'] ?? null);
        }
        if (!empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }
        return null;
    }
}