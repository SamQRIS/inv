<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
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
        });
    }
 
    // =========================================================
    // ADD SINGLE PAYMENT (tambah bayar dari view/tabel)
    // =========================================================
 
    public function addPayment(Transaction $transaction, array $paymentData): Payment
    {
        return DB::transaction(function () use ($transaction, $paymentData) {
            $newAmount = (float) $paymentData['amount'];
 
            // Cegah overpayment
            if ($transaction->amount_remaining < $newAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran melebihi sisa tagihan (Rp ' .
                                number_format($transaction->amount_remaining, 0, ',', '.') . ').',
                ]);
            }
 
            $payment = Payment::create([
                'transaction_id'     => $transaction->id,
                'payment_method_id'  => $paymentData['payment_method_id'],
                'amount'             => $newAmount,
                'payment_date'       => $paymentData['payment_date'] ?? today()->toDateString(),
                'reference_number'   => $paymentData['reference_number'] ?? null,
                'installment_detail' => $paymentData['installment_detail'] ?? null,
                'notes'              => $paymentData['notes'] ?? null,
            ]);
 
            $totalPaid = $transaction->amount_paid + $newAmount;
            $this->updatePaymentStatus($transaction, $totalPaid);
 
            return $payment;
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
            $totalPaid <= 0       => 'unpaid',
            $totalPaid >= $grandTotal => 'paid',
            default               => 'partial',
        };
 
        $transaction->update([
            'amount_paid'      => min($totalPaid, $grandTotal),
            'amount_remaining' => $remaining,
            'payment_status'   => $status,
        ]);
    }
}
