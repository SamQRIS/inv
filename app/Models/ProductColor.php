<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductColor extends Model
{
    protected $fillable = ['name', 'hex_code', 'sort_order', 'is_active'];
}
