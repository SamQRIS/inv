<?php

namespace App\Models;

use App\Models\CreditLog;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    const TYPE_DO       = 'do';
    const TYPE_END_USER = 'end_user';

    protected $fillable = [
        'name', 'phone', 'address', 'type',
        // credit_limit & credit_used dipertahankan di DB tapi tidak dipakai di logic baru
        'credit_limit', 'credit_used',
        'deposit_balance',
        'default_discount', 'is_active',
    ];

    protected $casts = [
        'default_discount' => 'array',
        'credit_limit'     => 'decimal:2',
        'credit_used'      => 'decimal:2',
        'deposit_balance'  => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    // ── Relasi ──────────────────────────────────────────────

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditLogs(): HasMany
    {
        return $this->hasMany(CreditLog::class)->latest();
    }

    // ── Deposit helpers ─────────────────────────────────────

    public function hasDeposit(): bool
    {
        return (float) $this->deposit_balance > 0;
    }

    public function depositBalance(): float
    {
        return (float) $this->deposit_balance;
    }

    /**
     * Apakah deposit cukup untuk menutup sejumlah amount?
     */
    public function isDepositSufficientFor(float $amount): bool
    {
        return $this->depositBalance() >= $amount;
    }

    // ── Type helpers ────────────────────────────────────────

    public function isDo(): bool
    {
        return $this->type === self::TYPE_DO;
    }

    public function isEndUser(): bool
    {
        return $this->type === self::TYPE_END_USER;
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeDo($query)
    {
        return $query->where('type', self::TYPE_DO);
    }

    public function scopeEndUser($query)
    {
        return $query->where('type', self::TYPE_END_USER);
    }

    // ── Static helpers ──────────────────────────────────────

    public static function findOrCreateEndUser(string $name, ?string $phone = null, ?string $address = null): static
    {
        return static::firstOrCreate(
            ['name' => $name, 'type' => self::TYPE_END_USER],
            ['phone' => $phone, 'address' => $address, 'is_active' => true]
        );
    }

    // ── Deprecated (tidak dipakai di sistem baru) ───────────
    // Dibiarkan agar tidak ada error jika ada kode lama yang masih memanggil

    /** @deprecated Gunakan depositBalance() */
    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->credit_used);
    }

    /** @deprecated */
    public function creditUsagePercent(): float
    {
        if ((float) $this->credit_limit <= 0) return 0;
        return round((float) $this->credit_used / (float) $this->credit_limit * 100, 1);
    }

    /** @deprecated */
    public function isCreditFull(): bool
    {
        return $this->credit_used >= $this->credit_limit;
    }
}