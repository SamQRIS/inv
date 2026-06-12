<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\ProductionOrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'user_id',
        'order_date',
        'target_date',
        'delivery_address',
        'production_notes',
        'customer_notes',
        'status',
    ];

    protected $casts = [
        'order_date'  => 'date',
        'target_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function totalUnits(): int
    {
        return $this->items->sum('quantity');
    }

    public function totalItems(): int
    {
        return $this->items->count();
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $last = static::whereDate('created_at', today())
            ->orderByDesc('id')->first();
        $seq  = $last
            ? ((int) substr($last->order_number, -3)) + 1
            : 1;
        return sprintf('ORD-%s-%03d', $date, $seq);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'         => 'Draft',
            'confirmed'     => 'Dikonfirmasi',
            'in_production' => 'Dalam Produksi',
            'done'          => 'Selesai',
            default         => $this->status,
        };
    }
}
