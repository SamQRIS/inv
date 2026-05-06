<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'product_id',
        'product_name', 'product_sku', 'unit_name',
        'unit_price', 'quantity', 'subtotal', 'notes',
    ];
 
    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
    ];
 
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
 
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
