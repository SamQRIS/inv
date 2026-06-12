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
        'reserved_qty',   // ← tambah
        'minimum_stock',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'reserved_qty'  => 'integer',
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

    /**
     * Stok tersedia efektif = quantity - reserved_qty
     * Ini yang digunakan untuk validasi transaksi baru
     */
    public function availableQty(): int
    {
        return max(0, $this->quantity - $this->reserved_qty);
    }

    public function isLowStock(): bool
    {
        return $this->availableQty() <= $this->minimum_stock;
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('(quantity - reserved_qty) <= minimum_stock');
    }
}
