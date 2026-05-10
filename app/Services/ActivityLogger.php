<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Log aktivitas generik.
     *
     * @param  string      $action   created|updated|deleted|restored|payment|shipment
     * @param  Model       $model    Model yang diubah
     * @param  string|null $label    Label human-readable (invoice number, nama, dll)
     * @param  array|null  $old      Nilai lama (untuk updated/deleted)
     * @param  array|null  $new      Nilai baru (untuk created/updated)
     * @param  string|null $desc     Deskripsi tambahan
     */
    public static function log(
        string  $action,
        Model   $model,
        ?string $label  = null,
        ?array  $old    = null,
        ?array  $new    = null,
        ?string $desc   = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'model_type'  => get_class($model),
            'model_id'    => $model->getKey(),
            'model_label' => $label,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => request()->ip(),
            'description' => $desc,
            'logged_at'   => now(),
        ]);
    }

    // ── Shortcut helpers ────────────────────────────────────

    public static function created(Model $model, ?string $label = null, ?array $data = null): ActivityLog
    {
        return static::log('created', $model, $label, null, $data);
    }

    public static function updated(Model $model, ?string $label = null, array $old = [], array $new = []): ActivityLog
    {
        // Hanya simpan field yang benar-benar berubah
        $changed = array_keys(array_diff_assoc($new, $old));
        $oldFiltered = array_intersect_key($old, array_flip($changed));
        $newFiltered = array_intersect_key($new, array_flip($changed));

        return static::log('updated', $model, $label, $oldFiltered ?: null, $newFiltered ?: null);
    }

    public static function deleted(Model $model, ?string $label = null): ActivityLog
    {
        return static::log('deleted', $model, $label);
    }

    public static function restored(Model $model, ?string $label = null): ActivityLog
    {
        return static::log('restored', $model, $label);
    }

    public static function payment(Model $model, ?string $label = null, float $amount = 0, string $method = ''): ActivityLog
    {
        return static::log(
            'payment',
            $model,
            $label,
            null,
            ['amount' => $amount, 'method' => $method],
            "Pembayaran Rp " . number_format($amount, 0, ',', '.') . " via {$method}"
        );
    }

    public static function shipment(Model $model, ?string $label = null, string $desc = ''): ActivityLog
    {
        return static::log('shipment', $model, $label, null, null, $desc);
    }
}
