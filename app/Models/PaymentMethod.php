<?php

namespace App\Models;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'code', 'provider',
        'is_installment', 'is_active', 'sort_order',
    ];
 
    protected $casts = [
        'is_installment' => 'boolean',
        'is_active'      => 'boolean',
    ];
 
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
 
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
 
    public function scopeInstallment($query)
    {
        return $query->where('is_installment', true);
    }
}
