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
        'credit_limit', 'credit_used',
        'default_discount', 'is_active',
    ];
 
    protected $casts = [
        'default_discount' => 'array',
        'credit_limit'     => 'decimal:2',
        'credit_used'      => 'decimal:2',
        'is_active'        => 'boolean',
    ];
 
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
 
    public function creditLogs(): HasMany
    {
        return $this->hasMany(CreditLog::class)->latest();
    }
 
    public function creditUsagePercent(): float
    {
        if ((float) $this->credit_limit <= 0) return 0;
        return round((float) $this->credit_used / (float) $this->credit_limit * 100, 1);
    }
 
    public function isCreditFull(): bool
    {
        return $this->credit_used >= $this->credit_limit;
    }
 
    public function isDo(): bool
    {
        return $this->type === self::TYPE_DO;
    }
 
    public function isEndUser(): bool
    {
        return $this->type === self::TYPE_END_USER;
    }
 
    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->credit_used);
    }
 
    public function scopeDo($query)
    {
        return $query->where('type', self::TYPE_DO);
    }
 
    public function scopeEndUser($query)
    {
        return $query->where('type', self::TYPE_END_USER);
    }
 
    /**
     * Auto-create end user customer dari input transaksi
     */
    public static function findOrCreateEndUser(string $name, ?string $phone = null): static
    {
        return static::firstOrCreate(
            ['name' => $name, 'type' => self::TYPE_END_USER],
            ['phone' => $phone, 'is_active' => true]
        );
    }
}