<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\SalesOrderItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'so_number', 'customer_id', 'user_id',
        'order_date', 'estimated_delivery_date',
        'status', 'grand_total',
        'transaction_id', 'notes', 'cancellation_reason',
    ];

    protected $casts = [
        'order_date'               => 'date',
        'estimated_delivery_date'  => 'date',
        'grand_total'              => 'decimal:2',
    ];

    // ── Relasi ───────────────────────────────────────────────

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
        return $this->hasMany(SalesOrderItem::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // ── Status helpers ───────────────────────────────────────

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isConverted(): bool { return $this->status === 'converted'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function canConfirm(): bool  { return $this->isDraft(); }
    public function canConvert(): bool  { return in_array($this->status, ['draft', 'confirmed']); }
    public function canCancel(): bool   { return !in_array($this->status, ['converted', 'cancelled']); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'confirmed' => 'Dikonfirmasi',
            'converted' => 'Sudah Jadi Transaksi',
            'cancelled' => 'Dibatalkan',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'draft'     => 'gray',
            'confirmed' => 'info',
            'converted' => 'success',
            'cancelled' => 'danger',
            default     => 'gray',
        };
    }

    // ── Generate SO number ───────────────────────────────────

    public static function generateSoNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'SO-' . now()->format('Ymd');
            $seq    = DB::table('so_sequences')->where('prefix', $prefix)->lockForUpdate()->first();

            if ($seq) {
                $next = $seq->last_number + 1;
                DB::table('so_sequences')->where('prefix', $prefix)->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                $next = 1;
                DB::table('so_sequences')->insert(['prefix' => $prefix, 'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $prefix . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }
}