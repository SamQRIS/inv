<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'minimum_stock',
    ];
 
    protected $casts = [
        'quantity'      => 'integer',
        'minimum_stock' => 'integer',
    ];
 
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
 
    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
 
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->minimum_stock;
    }
 
    /**
     * Scope: stok menipis di semua gudang
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'minimum_stock');
    }
}
