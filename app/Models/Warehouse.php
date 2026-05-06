<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'name', 'code', 'address', 'phone',
        'pic', 'is_default', 'is_active', 'sort_order',
    ];
 
    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];
 
    // ── Relasi ──────────────────────────────────────────────
 
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }
 
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_stocks')
            ->withPivot(['quantity', 'minimum_stock'])
            ->withTimestamps();
    }
 
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
 
    // ── Scopes ──────────────────────────────────────────────
 
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
 
    // ── Helpers ─────────────────────────────────────────────
 
    /**
     * Ambil gudang default (fallback ke first active)
     */
    public static function getDefault(): static
    {
        return static::where('is_default', true)->where('is_active', true)->firstOrFail();
    }
 
    /**
     * Saat set is_default = true, reset gudang lain
     */
    protected static function booted(): void
    {
        static::saving(function (Warehouse $warehouse) {
            if ($warehouse->is_default && $warehouse->isDirty('is_default')) {
                static::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }
        });
    }
}
