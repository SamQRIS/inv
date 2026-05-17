<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidCancelService
{
    // =========================================================
    // CANCEL — transaksi belum diproses (tidak ada DO terkirim,
    // tidak ada payment, atau payment bisa di-refund penuh)
    // =========================================================

    // public function cancel(Transaction $transaction, string $reason): void
    // {
    //     // Validasi: tidak bisa cancel jika sudah ada pengiriman
    //     if ($transaction->delivery_status === 'delivered') {
    //         throw ValidationException::withMessages([
    //             'status' => 'Tidak bisa cancel transaksi yang sudah terkirim. Gunakan Void.',
    //         ]);
    //     }

    //     // Validasi: tidak bisa cancel jika sudah void/cancelled
    //     if (in_array($transaction->payment_status, ['void', 'cancelled'])) {
    //         throw ValidationException::withMessages([
    //             'status' => 'Transaksi sudah dibatalkan sebelumnya.',
    //         ]);
    //     }

    //     DB::transaction(function () use ($transaction, $reason) {
    //         $customer = $transaction->customer;

    //         // 1. Refund payment ke deposit jika ada payment
    //         if ((float) $transaction->amount_paid > 0) {
    //             $this->refundPaymentsToDeposit($transaction);
    //         }

    //         // 2. Kembalikan credit_used customer DO
    //         if ($customer && $customer->type === 'do' && (float) $transaction->amount_remaining > 0) {
    //             $newCreditUsed = max(0, (float) $customer->credit_used - (float) $transaction->amount_remaining);
    //             $customer->update(['credit_used' => $newCreditUsed]);

    //             CreditLog::create([
    //                 'customer_id'    => $customer->id,
    //                 'user_id'        => Auth::id(),
    //                 'type'           => 'refund',
    //                 'amount'         => (float) $transaction->amount_remaining,
    //                 'credit_before'  => (float) $customer->credit_used + (float) $transaction->amount_remaining,
    //                 'credit_after'   => (float) $customer->credit_used,
    //                 'reference_type' => 'transaction',
    //                 'reference_id'   => $transaction->id,
    //                 'notes'          => "Kredit dikembalikan — Cancel {$transaction->invoice_number}",
    //             ]);
    //         }

    //         // 3. Update status transaksi
    //         $transaction->update([
    //             'payment_status'      => 'void',      // atau 'cancelled'
    //             'delivery_status'     => 'cancelled', // ← TAMBAH INI
    //             'cancellation_reason' => $reason,
    //             'cancelled_at'        => now(),
    //             'cancelled_by'        => Auth::id(),
    //         ]);
            

    //         ActivityLogger::log(
    //             'cancelled',
    //             $transaction,
    //             $transaction->invoice_number,
    //             null,
    //             ['reason' => $reason],
    //             "Transaksi dibatalkan: {$reason}"
    //         );
    //     });
    // }

    // =========================================================
    // VOID — transaksi sudah diproses (ada payment/DO)
    // Semua efek finansial dibatalkan, data tetap ada
    // =========================================================

    public function void(Transaction $transaction, string $reason): void
    {
        // Validasi: tidak bisa void jika sudah void/cancelled
        if (in_array($transaction->payment_status, ['void', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => 'Transaksi sudah dibatalkan sebelumnya.',
            ]);
        }

        DB::transaction(function () use ($transaction, $reason) {
            $customer = $transaction->customer;

            // 1. Refund semua payment ke deposit customer DO
            //    atau catat sebagai piutang refund untuk end user
            if ((float) $transaction->amount_paid > 0) {
                $this->refundPaymentsToDeposit($transaction);
            }

            // 2. Kembalikan credit_used customer DO
            if ($customer && $customer->type === 'do' && (float) $transaction->amount_remaining > 0) {
                $newCreditUsed = max(0, (float) $customer->credit_used - (float) $transaction->amount_remaining);
                $customer->update(['credit_used' => $newCreditUsed]);

                CreditLog::create([
                    'customer_id'    => $customer->id,
                    'user_id'        => Auth::id(),
                    'type'           => 'refund',
                    'amount'         => (float) $transaction->amount_remaining,
                    'credit_before'  => (float) $customer->credit_used + (float) $transaction->amount_remaining,
                    'credit_after'   => (float) $customer->credit_used,
                    'reference_type' => 'transaction',
                    'reference_id'   => $transaction->id,
                    'notes'          => "Kredit dikembalikan — Void {$transaction->invoice_number}",
                ]);
            }

            // 3. Update status transaksi
            $transaction->update([
                'payment_status'      => 'void',      // atau 'cancelled'
                'delivery_status'     => 'cancelled', // ← TAMBAH INI
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
                'cancelled_by'        => Auth::id(),
            ]);

            ActivityLogger::log(
                'void',
                $transaction,
                $transaction->invoice_number,
                null,
                ['reason' => $reason, 'amount_paid' => $transaction->amount_paid],
                "Transaksi di-void: {$reason}"
            );
        });
    }

    // =========================================================
    // INTERNAL — Refund payment ke deposit (untuk customer DO)
    // atau catat saja untuk end user
    // =========================================================

    private function refundPaymentsToDeposit(Transaction $transaction): void
    {
        $customer  = $transaction->customer;
        $amountPaid = (float) $transaction->amount_paid;

        if ($amountPaid <= 0) return;

        if ($customer && $customer->type === 'do') {
            // Customer DO: kembalikan ke deposit
            $depositBefore = (float) $customer->deposit_balance;
            $customer->increment('deposit_balance', $amountPaid);

            CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'deposit',
                'amount'         => $amountPaid,
                'credit_before'  => $depositBefore,
                'credit_after'   => $depositBefore + $amountPaid,
                'reference_type' => 'transaction',
                'reference_id'   => $transaction->id,
                'notes'          => "Refund payment ke deposit — {$transaction->invoice_number}",
            ]);
        }
        // End user: tidak ada deposit, cukup catat di activity log
        // Tim finance bisa proses pengembalian uang secara manual
    }
}
