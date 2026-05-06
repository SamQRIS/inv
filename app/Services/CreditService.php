<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditService
{
    /**
     * Top up credit limit customer (tambah limit)
     */
    public function topup(
        Customer $customer,
        float    $amount,
        ?string  $notes          = null,
        string   $referenceType  = 'manual',
        ?int     $referenceId    = null
    ): CreditLog {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah top up harus lebih dari 0.',
            ]);
        }
 
        return DB::transaction(function () use ($customer, $amount, $notes, $referenceType, $referenceId) {
            // Lock row untuk hindari race condition
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
 
            $before = (float) $customer->credit_limit;
            $after  = $before + $amount;
 
            $customer->update(['credit_limit' => $after]);
 
            return CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'topup',
                'amount'         => $amount,
                'credit_before'  => $before,
                'credit_after'   => $after,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'notes'          => $notes,
            ]);
        });
    }
 
    /**
     * Kurangi credit limit customer (manual deduct)
     */
    public function deduct(
        Customer $customer,
        float    $amount,
        ?string  $notes         = null,
        string   $referenceType = 'manual',
        ?int     $referenceId   = null
    ): CreditLog {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pengurangan harus lebih dari 0.',
            ]);
        }
 
        return DB::transaction(function () use ($customer, $amount, $notes, $referenceType, $referenceId) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
 
            $before = (float) $customer->credit_limit;
 
            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => "Pengurangan (Rp " . number_format($amount, 0, ',', '.') . ") " .
                                "melebihi credit limit saat ini (Rp " . number_format($before, 0, ',', '.') . ").",
                ]);
            }
 
            $after = $before - $amount;
 
            $customer->update(['credit_limit' => $after]);
 
            return CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'deduct',
                'amount'         => $amount,
                'credit_before'  => $before,
                'credit_after'   => $after,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'notes'          => $notes,
            ]);
        });
    }
 
    /**
     * Catat pemakaian credit (dipanggil otomatis saat transaksi)
     */
    public function recordUsage(
        Customer $customer,
        float    $amount,
        int      $transactionId
    ): CreditLog {
        return DB::transaction(function () use ($customer, $amount, $transactionId) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
 
            $before = (float) $customer->credit_used;
            $after  = $before + $amount;
 
            $customer->update(['credit_used' => $after]);
 
            return CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'used',
                'amount'         => $amount,
                'credit_before'  => $before,
                'credit_after'   => $after,
                'reference_type' => 'transaction',
                'reference_id'   => $transactionId,
                'notes'          => "Dipakai untuk transaksi #{$transactionId}",
            ]);
        });
    }
}
