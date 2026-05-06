<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLog extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'amount',
        'credit_before',
        'credit_after',
        'reference_type',
        'reference_id',
        'notes',
    ];
 
    protected $casts = [
        'amount'        => 'decimal:2',
        'credit_before' => 'decimal:2',
        'credit_after'  => 'decimal:2',
    ];
 
    // ── Relasi ──────────────────────────────────────────────
 
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    // ── Helpers ─────────────────────────────────────────────
 
    public function typeLabel(): string
    {
        return match($this->type) {
            'topup'  => 'Top Up',
            'deduct' => 'Pengurangan',
            'used'   => 'Terpakai',
            'refund' => 'Refund',
            default  => $this->type,
        };
    }
 
    public function typeColor(): string
    {
        return match($this->type) {
            'topup'  => 'success',
            'deduct' => 'danger',
            'used'   => 'warning',
            'refund' => 'info',
            default  => 'gray',
        };
    }
 
    public function isCredit(): bool
    {
        return in_array($this->type, ['topup', 'refund']);
    }
}
