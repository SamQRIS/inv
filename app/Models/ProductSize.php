<?php

namespace App\Models;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];
 
    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'size_id');
    }
}
