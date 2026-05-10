<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'model_label',
        'old_values',
        'new_values',
        'ip_address',
        'description',
        'logged_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'logged_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created'  => 'Dibuat',
            'updated'  => 'Diubah',
            'deleted'  => 'Dihapus',
            'restored' => 'Dipulihkan',
            'payment'  => 'Pembayaran',
            'shipment' => 'Pengiriman',
            default    => ucfirst($this->action),
        };
    }

    public function actionColor(): string
    {
        return match ($this->action) {
            'created'  => 'success',
            'updated'  => 'warning',
            'deleted'  => 'danger',
            'restored' => 'info',
            'payment'  => 'success',
            'shipment' => 'primary',
            default    => 'gray',
        };
    }
}
