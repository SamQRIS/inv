<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\ActivityLogger;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        ActivityLogger::created(
            $transaction,
            $transaction->invoice_number,
            ['grand_total' => $transaction->grand_total, 'payment_status' => $transaction->payment_status]
        );
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
         // Jika transaksi dihapus (soft delete) dan belum void/cancel
        // kembalikan credit
        if (!in_array($transaction->payment_status, ['void', 'cancelled'])) {
            $customer = $transaction->customer;
            if ($customer && $customer->type === 'do' && (float) $transaction->amount_remaining > 0) {
                $newCreditUsed = max(0, (float) $customer->credit_used - (float) $transaction->amount_remaining);
                $customer->update(['credit_used' => $newCreditUsed]);
            }
        }
 
        ActivityLogger::deleted($transaction, $transaction->invoice_number);
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        // Restore: kembalikan credit_used jika transaksi belum lunas
        if (!in_array($transaction->payment_status, ['void', 'cancelled'])) {
            $customer = $transaction->customer;
            if ($customer && $customer->type === 'do' && (float) $transaction->amount_remaining > 0) {
                $customer->update([
                    'credit_used' => (float) $customer->credit_used + (float) $transaction->amount_remaining
                ]);
            }
        }
 
        ActivityLogger::restored($transaction, $transaction->invoice_number);
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
