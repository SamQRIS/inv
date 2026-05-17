<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLog extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'payment_method_id',
        'type',
        'amount',
        'credit_before',
        'credit_after',
        'reference_type',
        'reference_id',
        'reference_number',
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // ── Helpers ─────────────────────────────────────────────

    public function typeLabel(): string
    {
        return match ($this->type) {
            // Deposit (sistem baru)
            'deposit_topup'         => 'Top Up Deposit',
            'deposit_used'          => 'Deposit Dipakai',
            'deposit_manual_deduct' => 'Koreksi Deposit',
            'deposit'               => 'Deposit Masuk (Kelebihan Bayar)',
            // Credit lama (legacy — tidak dipakai di alur baru)
            'topup'  => 'Top Up Limit',
            'deduct' => 'Pengurangan Limit',
            'used'   => 'Kredit Terpakai',
            'refund' => 'Refund Kredit',
            default  => $this->type,
        };
    }

    public function typeColor(): string
    {
        return match ($this->type) {
            'deposit_topup'         => 'success',
            'deposit_used'          => 'warning',
            'deposit_manual_deduct' => 'danger',
            'deposit'               => 'primary',
            'topup'                 => 'success',
            'deduct'                => 'danger',
            'used'                  => 'warning',
            'refund'                => 'info',
            default                 => 'gray',
        };
    }

    /**
     * Apakah log ini menambah saldo deposit?
     */
    public function isDebit(): bool
    {
        return in_array($this->type, ['deposit_topup', 'deposit', 'topup', 'refund']);
    }

    /**
     * Apakah log ini mengurangi saldo deposit?
     */
    public function isCredit(): bool
    {
        return in_array($this->type, ['deposit_used', 'deposit_manual_deduct', 'deduct', 'used']);
    }
}