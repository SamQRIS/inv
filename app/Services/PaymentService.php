<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    // =========================================================
    // PROCESS MULTIPLE PAYMENTS (saat create transaksi)
    // =========================================================

    public function processPayments(Transaction $transaction, array $payments): void
    {
        DB::transaction(function () use ($transaction, $payments) {
            $totalPaid = 0;

            foreach ($payments as $paymentData) {
                $payment = Payment::create([
                    'transaction_id'     => $transaction->id,
                    'payment_method_id'  => $paymentData['payment_method_id'],
                    'amount'             => $paymentData['amount'],
                    'payment_date'       => $paymentData['payment_date'] ?? today()->toDateString(),
                    'reference_number'   => $paymentData['reference_number'] ?? null,
                    'installment_detail' => $paymentData['installment_detail'] ?? null,
                    'notes'              => $paymentData['notes'] ?? null,
                ]);

                // Cicilan pihak ketiga = dianggap full payment
                if ($payment->isInstallment()) {
                    $totalPaid += $transaction->grand_total;
                } else {
                    $totalPaid += $payment->amount;
                }
            }

            $this->updatePaymentStatus($transaction, $totalPaid);

            // Di method processPayments(), setelah $this->updatePaymentStatus():
            $transaction->refresh();
            ActivityLogger::log(
                'payment',
                $transaction,
                $transaction->invoice_number,
                null,
                ['total_payments' => count($payments), 'total_paid' => $totalPaid],
                'Pembayaran awal saat transaksi dibuat.'
            );
        });
    }

    // =========================================================
    // ADD SINGLE PAYMENT (tambah bayar dari view/tabel)
    // Mendukung overpayment untuk customer DO — kelebihan
    // otomatis masuk deposit_balance customer
    // =========================================================

    public function addPayment(Transaction $transaction, array $paymentData): Payment
    {
        return DB::transaction(function () use ($transaction, $paymentData) {
            $newAmount = (float) $paymentData['amount'];
            $remaining = (float) $transaction->amount_remaining;

            $customer = $transaction->customer;
            $isDo = $customer && $customer->type === 'do';

            // Untuk end user: tetap blok overpayment
            if (!$isDo && $newAmount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran melebihi sisa tagihan (Rp ' .
                        number_format($transaction->amount_remaining, 0, ',', '.') . ').',
                ]);
            }

             // Hitung applied amount dan overpaid
            $overpaid      = $isDo ? max(0, $newAmount - $remaining) : 0;
            $appliedAmount = min($newAmount, $remaining);

            $payment = Payment::create([
                'transaction_id'     => $transaction->id,
                'payment_method_id'  => $paymentData['payment_method_id'],
                'amount'             => $appliedAmount,
                'payment_date'       => $paymentData['payment_date'] ?? today()->toDateString(),
                'reference_number'   => $paymentData['reference_number'] ?? null,
                'installment_detail' => $paymentData['installment_detail'] ?? null,
                'notes'              => $paymentData['notes'] ?? null,
            ]);

            $totalPaid = $transaction->amount_paid + $appliedAmount;
            $this->updatePaymentStatus($transaction, $totalPaid);

             // ✅ Simpan kelebihan ke deposit customer DO
            if ($overpaid > 0 && $isDo) {
                $this->addDeposit($customer, $overpaid, $transaction);
            }

            // Di method addPayment(), setelah $this->updatePaymentStatus():
            $transaction->refresh();
            $method = $payment->paymentMethod?->name ?? '-';

            ActivityLogger::payment(
                $transaction,
                $transaction->invoice_number,
                $newAmount,
                $method
            );

            return $payment;
        });
    }

    // =========================================================
    // PAKAI DEPOSIT UNTUK TRANSAKSI
    // Dipanggil dari TransactionService saat customer DO
    // punya deposit dan masih ada sisa tagihan
    // =========================================================
 
    public function applyDeposit(Transaction $transaction): void
    {
        $customer = $transaction->customer;
 
        if (!$customer || $customer->type !== 'do' || !$customer->hasDeposit()) {
            return;
        }
 
        $transaction->refresh();
        $remaining = (float) $transaction->amount_remaining;
 
        if ($remaining <= 0) return;
 
        $useDeposit = min($customer->depositBalance(), $remaining);
 
        DB::transaction(function () use ($transaction, $customer, $useDeposit) {
            // Kurangi deposit
            $depositBefore = $customer->depositBalance();
            $customer->decrement('deposit_balance', $useDeposit);
            $customer->refresh();
 
            // Kurangi credit_used juga karena tagihan berkurang
            $newCreditUsed = max(0, (float) $customer->credit_used - $useDeposit);
            $customer->update(['credit_used' => $newCreditUsed]);
 
            // Catat di credit_logs
            CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'deposit_used',
                'amount'         => $useDeposit,
                'credit_before'  => $depositBefore,
                'credit_after'   => $customer->depositBalance(),
                'reference_type' => 'transaction',
                'reference_id'   => $transaction->id,
                'notes'          => "Deposit dipakai untuk transaksi {$transaction->invoice_number}",
            ]);
 
            // Update payment status transaksi
            $totalPaid = (float) $transaction->amount_paid + $useDeposit;
            $this->updatePaymentStatus($transaction, $totalPaid);
 
            // Buat record payment dari deposit (opsional, untuk audit trail)
            // Jika tidak mau buat payment record dari deposit, hapus bagian ini
            // Payment::create([...]);
        });
    }
    
    // =========================================================
    // RECALCULATE STATUS
    // Dipanggil setelah edit atau hapus payment dari PaymentResource
    // Hitung ulang dari semua payment yang masih ada di DB
    // =========================================================

    public function recalculateStatus(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->refresh();

            // Hitung ulang total dari semua payment yang tersisa
            $totalPaid = $transaction->payments()
                ->with('paymentMethod')
                ->get()
                ->sum(function (Payment $payment) use ($transaction) {
                    // Cicilan = dianggap lunas penuh
                    if ($payment->isInstallment()) {
                        return (float) $transaction->grand_total;
                    }
                    return (float) $payment->amount;
                });

            $this->updatePaymentStatus($transaction, $totalPaid);

            // Di method recalculateStatus(), setelah $this->updatePaymentStatus():
            $transaction->refresh();
            ActivityLogger::log(
                'updated',
                $transaction,
                $transaction->invoice_number,
                null,
                ['payment_status' => $transaction->payment_status, 'amount_paid' => $transaction->amount_paid],
                'Status pembayaran diperbarui.'
            );
        });
    }

    // =========================================================
    // INTERNAL
    // =========================================================

    private function updatePaymentStatus(Transaction $transaction, float $totalPaid): void
    {
        $grandTotal = (float) $transaction->grand_total;
        $remaining  = max(0, $grandTotal - $totalPaid);
        $oldRemaining = (float) $transaction->amount_remaining; // ← simpan dulu

        $status = match (true) {
            $totalPaid <= 0       => 'unpaid',
            $totalPaid >= $grandTotal => 'paid',
            default               => 'partial',
        };

        $transaction->update([
            'amount_paid'      => min($totalPaid, $grandTotal),
            'amount_remaining' => $remaining,
            'payment_status'   => $status,
        ]);

        // ✅ Kurangi credit_used saat ada pembayaran masuk
        $customer = $transaction->customer;
        if ($customer && $customer->type === 'do' && $oldRemaining > $remaining) {
            $paidAmount = $oldRemaining - $remaining;
            $newCreditUsed = max(0, (float) $customer->credit_used - $paidAmount);
            $customer->update(['credit_used' => $newCreditUsed]);
        }
    }

    private function addDeposit($customer, float $amount, Transaction $transaction): void
    {
        $depositBefore = $customer->depositBalance();
        $customer->increment('deposit_balance', $amount);
        $customer->refresh();
 
        CreditLog::create([
            'customer_id'    => $customer->id,
            'user_id'        => Auth::id(),
            'type'           => 'deposit',
            'amount'         => $amount,
            'credit_before'  => $depositBefore,
            'credit_after'   => $customer->depositBalance(),
            'reference_type' => 'transaction',
            'reference_id'   => $transaction->id,
            'notes'          => "Kelebihan bayar transaksi {$transaction->invoice_number}",
        ]);
 
        Notification::make()
            ->warning()
            ->title('Kelebihan Pembayaran — Disimpan sebagai Deposit')
            ->body('Rp ' . number_format($amount, 0, ',', '.') . ' disimpan sebagai deposit ' . $customer->name . '.')
            ->send();
    }
}
