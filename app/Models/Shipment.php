<?php

namespace App\Models;

use App\Models\Delivery;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'shipment_number',
        'delivery_id',
        'shipment_date',
        'driver_name',
        'vehicle_number',
        'notes',
    ];

    protected $casts = [
        'shipment_date' => 'date',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }
}
