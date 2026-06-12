<?php

namespace App\Models;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Model;

class ProductFabric extends Model
{
    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'fabric_id');
    }
}
