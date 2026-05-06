<?php

namespace App\Models;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id',
        'transaction_item_id',
        'product_id',
        'qty_ordered',
        'qty_delivered',
    ];

    protected $casts = [
        'qty_ordered'   => 'integer',
        'qty_delivered' => 'integer',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function qtyRemaining(): int
    {
        return $this->qty_ordered - $this->qty_delivered;
    }

    public function isFullyDelivered(): bool
    {
        return $this->qty_delivered >= $this->qty_ordered;
    }
}
