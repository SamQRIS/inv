<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductFabric;
use App\Models\ProductSize;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = ['product_id', 'size_id', 'fabric_id', 'price'];

    protected $casts = ['price' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function size()
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }
    public function fabric()
    {
        return $this->belongsTo(ProductFabric::class, 'fabric_id');
    }

    // Helper: cari harga berdasarkan kombinasi
    public static function findPrice(int $productId, int $sizeId, ?int $fabricId = null): ?float
    {
        return static::where('product_id', $productId)
            ->where('size_id', $sizeId)
            ->where('fabric_id', $fabricId)
            ->value('price');
    }
}
