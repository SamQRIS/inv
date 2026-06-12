<?php

namespace App\Models;

use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderItem extends Model
{
    protected $fillable = [
        'production_order_id',
        'product_name',
        'size',
        'color',
        'headboard_type',
        'quantity',
        'item_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function specSummary(): string
    {
        return collect([
            $this->size        ? 'Uk. ' . $this->size        : null,
            $this->color       ? 'Warna: ' . $this->color    : null,
            $this->headboard_type ? 'Sandaran: ' . $this->headboard_type : null,
        ])->filter()->join(' · ');
    }
}
