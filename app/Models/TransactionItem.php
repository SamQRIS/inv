<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'warehouse_id',    // ← tambah ini
        'product_name',
        'product_sku',
        'unit_name',
        'unit_price',
        'quantity',
        'subtotal',
        'notes',
        'is_backorder',
        'qty_backorder',

        // ── Display / Konsinyasi ─────────────────────────────
        'is_display',
        'display_location',
        'display_status',
        'qty_display_sold',
        'qty_display_returned',
        'display_confirmed_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',

        'is_backorder'         => 'boolean',
        'qty_backorder'        => 'integer',
        'is_display'           => 'boolean',
        'qty_display_sold'     => 'integer',
        'qty_display_returned' => 'integer',
        'display_confirmed_at' => 'date',
    ];

    // ── Relasi ───────────────────────────────────────────────

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
 
    // ── Display helpers ──────────────────────────────────────

    /**
     * Qty yang masih ada di lokasi display (belum terjual & belum retur)
     */
    public function qtyDisplayRemaining(): int
    {
        if (!$this->is_display) return 0;
        return max(0, $this->quantity - $this->qty_display_sold - $this->qty_display_returned);
    }

    /**
     * Apakah item display ini sudah selesai (semua terjual atau diretur)
     */
    public function isDisplaySettled(): bool
    {
        if (!$this->is_display) return true;
        return $this->qtyDisplayRemaining() === 0;
    }

    public function displayStatusLabel(): string
    {
        return match ($this->display_status) {
            'pending'  => 'Di Lokasi Display',
            'sold'     => 'Terjual',
            'returned' => 'Diretur',
            default    => '—',
        };
    }

    public function displayStatusColor(): string
    {
        return match ($this->display_status) {
            'pending'  => 'warning',
            'sold'     => 'success',
            'returned' => 'info',
            default    => 'gray',
        };
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeDisplay($query)
    {
        return $query->where('is_display', true);
    }

    public function scopeDisplayPending($query)
    {
        return $query->where('is_display', true)->where('display_status', 'pending');
    }

    public function scopeDisplayByLocation($query, string $location)
    {
        return $query->where('is_display', true)->where('display_location', $location);
    }
}
