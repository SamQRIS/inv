<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
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

                if ($payment->isInstallment()) {
                    $totalPaid += $transaction->grand_total;
                } else {
                    $totalPaid += $payment->amount;
                }
            }

            $this->updatePaymentStatus($transaction, $totalPaid);

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
    // =========================================================

    public function addPayment(Transaction $transaction, array $paymentData): Payment
    {
        return DB::transaction(function () use ($transaction, $paymentData) {
            $newAmount = (float) $paymentData['amount'];
            $remaining = (float) $transaction->amount_remaining;

            $customer = $transaction->customer;
            $isDo     = $customer && $customer->type === 'do';

            // Cek apakah metode yang dipilih adalah Deposit
            $paymentMethod = PaymentMethod::find($paymentData['payment_method_id']);
            $isDepositMethod = $paymentMethod && $paymentMethod->code === 'deposit';

            // Jika pakai metode Deposit, validasi saldo deposit customer
            if ($isDepositMethod && $isDo) {
                $depositBalance = $customer->depositBalance();
                if ($newAmount > $depositBalance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo deposit tidak mencukupi. Saldo deposit: Rp ' .
                            number_format($depositBalance, 0, ',', '.') . '.',
                    ]);
                }
                if ($newAmount > $remaining) {
                    throw ValidationException::withMessages([
                        'amount' => 'Jumlah melebihi sisa tagihan (Rp ' .
                            number_format($remaining, 0, ',', '.') . ').',
                    ]);
                }
            }

            if (!$isDo && $newAmount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran melebihi sisa tagihan (Rp ' .
                        number_format($remaining, 0, ',', '.') . ').',
                ]);
            }

            $overpaid      = ($isDo && !$isDepositMethod) ? max(0, $newAmount - $remaining) : 0;
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

            // Jika metode Deposit → kurangi deposit_balance customer & catat di credit_logs
            if ($isDepositMethod && $isDo) {
                $depositBefore = $customer->depositBalance();
                $customer->decrement('deposit_balance', $appliedAmount);
                $customer->refresh();

                CreditLog::create([
                    'customer_id'    => $customer->id,
                    'user_id'        => Auth::id(),
                    'type'           => 'deposit_used',
                    'amount'         => $appliedAmount,
                    'credit_before'  => $depositBefore,
                    'credit_after'   => $customer->depositBalance(),
                    'reference_type' => 'transaction',
                    'reference_id'   => $transaction->id,
                    'notes'          => "Deposit dipakai (manual) untuk transaksi {$transaction->invoice_number}",
                ]);
            }

            $totalPaid = (float) $transaction->amount_paid + $appliedAmount;
            $this->updatePaymentStatus($transaction, $totalPaid);

            // Kelebihan bayar (non-deposit) masuk deposit
            if ($overpaid > 0 && $isDo) {
                $this->addDeposit($customer, $overpaid, $transaction);
            }

            $transaction->refresh();
            ActivityLogger::payment(
                $transaction,
                $transaction->invoice_number,
                $newAmount,
                $payment->paymentMethod?->name ?? '-'
            );

            return $payment;
        });
    }

    // =========================================================
    // PAKAI DEPOSIT UNTUK MELUNASI TRANSAKSI
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
            $depositBefore = $customer->depositBalance();

            // 1. Kurangi deposit customer
            $customer->decrement('deposit_balance', $useDeposit);
            $customer->refresh();

            // 2. Catat di credit_logs (audit trail deposit)
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

            // 3. ✅ Buat record Payment dengan payment_method 'deposit'
            //    Ini yang membuat amount_paid terupdate dan status berubah
            $depositMethod = PaymentMethod::where('code', 'deposit')->first();

            if ($depositMethod) {
                Payment::create([
                    'transaction_id'    => $transaction->id,
                    'payment_method_id' => $depositMethod->id,
                    'amount'            => $useDeposit,
                    'payment_date'      => today()->toDateString(),
                    'notes'             => "Dari deposit customer — saldo sebelum: Rp " 
                                          . number_format($depositBefore, 0, ',', '.'),
                ]);
            }

            // 4. Update amount_paid dan payment_status transaksi
            $totalPaid = (float) $transaction->amount_paid + $useDeposit;
            $this->updatePaymentStatus($transaction, $totalPaid);
        });
    }

    // =========================================================
    // RECALCULATE STATUS
    // =========================================================

    public function recalculateStatus(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->refresh();

            $totalPaid = $transaction->payments()
                ->with('paymentMethod')
                ->get()
                ->sum(function (Payment $payment) use ($transaction) {
                    if ($payment->isInstallment()) {
                        return (float) $transaction->grand_total;
                    }
                    return (float) $payment->amount;
                });

            $this->updatePaymentStatus($transaction, $totalPaid);

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

        $status = match (true) {
            $totalPaid <= 0           => 'unpaid',
            $totalPaid >= $grandTotal => 'paid',
            default                   => 'partial',
        };

        $transaction->update([
            'amount_paid'      => min($totalPaid, $grandTotal),
            'amount_remaining' => $remaining,
            'payment_status'   => $status,
        ]);
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
            'notes'          => "Kelebihan bayar transaksi {$transaction->invoice_number} masuk deposit.",
        ]);

        Notification::make()
            ->success()
            ->title('Kelebihan Bayar — Masuk Deposit')
            ->body('Rp ' . number_format($amount, 0, ',', '.') . ' disimpan sebagai deposit ' . $customer->name . '.')
            ->send();
    }
}