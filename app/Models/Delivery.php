<?php

namespace App\Models;

use App\Models\DeliveryItem;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'do_number',
        'transaction_id',
        'user_id',
        'do_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'do_date' => 'date',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public static function generateDoNumber(): string
    {
        $prefix = 'DO';
        $date   = now()->format('Ymd');
        $last   = static::whereDate('created_at', today())->orderByDesc('id')->first();
        $seq    = $last ? ((int) substr($last->do_number, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }
}
