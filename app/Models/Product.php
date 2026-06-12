<?php

namespace App\Models;

use App\Models\Category;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'unit_id',
        'supplier_id',
        'name',
        'sku',
        'description',
        'cost_price',
        'selling_price',
        'stock_quantity',  // total agregat dari semua gudang (di-sync otomatis)
        'minimum_stock',   // minimum global
        'is_active',
        'product_type'
    ];

    protected $casts = [
        'cost_price'     => 'decimal:2',
        'selling_price'  => 'decimal:2',
        'stock_quantity' => 'integer',
        'minimum_stock'  => 'integer',
        'is_active'      => 'boolean',
    ];

    // ── Relasi ──────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Stok per gudang */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /** Many-to-many ke warehouses via product_stocks */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'product_stocks')
            ->withPivot(['quantity', 'minimum_stock'])
            ->withTimestamps();
    }

    // ── Stock Helpers ────────────────────────────────────────

    /** Stok di gudang tertentu */
    public function stockAt(int|Warehouse $warehouse): int
    {
        $id = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;
        return $this->productStocks()->where('warehouse_id', $id)->value('quantity') ?? 0;
    }

    /** Cek kecukupan stok di gudang tertentu */
    public function hasEnoughStockAt(int $qty, int|Warehouse $warehouse): bool
    {
        return $this->stockAt($warehouse) >= $qty;
    }

    /** Cek kecukupan stok total (semua gudang) */
    public function hasEnoughStock(int $qty): bool
    {
        return $this->stock_quantity >= $qty;
    }

    /** Sync kolom stock_quantity (total semua gudang).
     *  Pakai DB::table() agar bypass semua Eloquent events/observers
     *  sehingga tidak bisa trigger loop rekursif.
     */
    public function syncTotalStock(): void
    {
        $total = DB::table('product_stocks')
            ->where('product_id', $this->id)
            ->sum('quantity');

        DB::table('products')
            ->where('id', $this->id)
            ->update(['stock_quantity' => (int) $total]);

        // Refresh nilai in-memory
        $this->stock_quantity = (int) $total;
    }

    /** Inisialisasi baris product_stocks jika belum ada */
    public function initStockAt(Warehouse $warehouse, int $quantity = 0, int $minimum = 0): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $this->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => $quantity, 'minimum_stock' => $minimum]
        );
    }

    // ── Status Helpers ───────────────────────────────────────

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->minimum_stock;
    }

    public function isLowStockAt(int|Warehouse $warehouse): bool
    {
        $id = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;
        $ps = $this->productStocks()->where('warehouse_id', $id)->first();
        return $ps ? $ps->isLowStock() : false;
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'minimum_stock');
    }

    // Product.php
    public function __sleep(): array
    {
        $this->unsetRelations();
        return array_keys($this->getAttributes());
    }

    // Tambah relasi:
    public function prices()
    {
        return $this->hasMany(\App\Models\ProductPrice::class);
    }

    // Helper: cari harga otomatis berdasarkan size + fabric
    public function getPriceFor(?int $sizeId, ?int $fabricId = null): ?float
    {
        if (!$sizeId) return $this->selling_price ?: null;

        return \App\Models\ProductPrice::findPrice($this->id, $sizeId, $fabricId)
            ?? \App\Models\ProductPrice::findPrice($this->id, $sizeId, null)
            ?? $this->selling_price
            ?: null;
    }

    // Generate nama produk otomatis dari variasi
    public function buildProductName(?string $sizeName, ?string $fabricName, ?string $colorName): string
    {
        return trim(implode(' ', array_filter([
            $this->name,
            $sizeName,
            $fabricName,
            $colorName,
        ])));
    }

    // Cek apakah produk butuh pilih ukuran
    public function needsSize(): bool
    {
        return in_array($this->product_type, ['divan', 'kasur']);
    }

    // Cek apakah produk butuh pilih kain
    public function needsFabric(): bool
    {
        return $this->product_type === 'divan';
    }
}
