<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\ProductionOrder;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'production_order_id',
        'user_id',
        'transaction_date',
        'delivery_date',
        'delivery_note',
        'subtotal',
        'discount_amount',
        'grand_total',
        'amount_paid',
        'amount_remaining',
        'discount_type',
        'discount_json',
        'payment_status',
        'delivery_status',
        'notes',
    ];

    protected $casts = [
        'transaction_date'  => 'date',
        'delivery_date'     => 'date',
        'discount_json'     => 'array',
        'subtotal'          => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'grand_total'       => 'decimal:2',
        'amount_paid'       => 'decimal:2',
        'amount_remaining'  => 'decimal:2',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    // =============================================
    // ACCESSORS / COMPUTED
    // =============================================

    /**
     * Delivery date display: tanggal atau teks note
     */
    protected function deliveryDateDisplay(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->delivery_date) {
                    return $this->delivery_date->format('d/m/Y');
                }
                return $this->delivery_note ?? '-';
            }
        );
    }

    /**
     * Apakah delivery date hanya teks (bukan tanggal pasti)
     */
    protected function isDeliveryDateFlexible(): Attribute
    {
        return Attribute::make(
            get: fn() => is_null($this->delivery_date) && !is_null($this->delivery_note)
        );
    }

    // =============================================
    // INVOICE NUMBER GENERATOR (atomic — race condition safe)
    // =============================================

    /**
     * Generate invoice number yang atomic menggunakan database lock.
     * Mencegah duplikat nomor invoice jika 2 kasir transaksi bersamaan.
     *
     * Format: INV-YYYYMMDD-XXXX
     * Contoh: INV-20260508-0001
     */
    public static function generateInvoiceNumber(): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $date   = now()->format('Ymd');
            $key    = "INV-{$date}";

            // Upsert + lock row untuk atomic increment
            // Jika belum ada row untuk hari ini, buat baru dengan sequence = 0
            \Illuminate\Support\Facades\DB::table('invoice_sequences')
                ->insertOrIgnore([
                    'prefix'         => $key,
                    'last_sequence'  => 0,
                    'sequence_date'  => today()->toDateString(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

            // Lock row dan increment — atomic, tidak bisa race condition
            $row = \Illuminate\Support\Facades\DB::table('invoice_sequences')
                ->where('prefix', $key)
                ->lockForUpdate()
                ->first();

            $nextSequence = $row->last_sequence + 1;

            \Illuminate\Support\Facades\DB::table('invoice_sequences')
                ->where('prefix', $key)
                ->update([
                    'last_sequence' => $nextSequence,
                    'updated_at'    => now(),
                ]);

            return sprintf('INV-%s-%04d', $date, $nextSequence);
        });
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopePartialPaid($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopePendingDelivery($query)
    {
        return $query->where('delivery_status', '!=', 'delivered');
    }
}