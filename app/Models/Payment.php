<?php

namespace App\Models;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'transaction_id', 'payment_method_id',
        'amount', 'payment_date', 'reference_number',
        'installment_detail', 'notes',
    ];
 
    protected $casts = [
        'amount'             => 'decimal:2',
        'payment_date'       => 'date',
        'installment_detail' => 'array',
    ];
 
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
 
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
 
    public function isInstallment(): bool
    {
        return $this->paymentMethod?->is_installment ?? false;
    }
}
