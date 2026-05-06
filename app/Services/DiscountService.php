<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * DiscountService
 *
 * Menangani logika diskon bertingkat yang kompleks.
 * Diskon dihitung BERURUTAN (cascading), bukan dijumlah langsung.
 *
 * Contoh:
 *   Subtotal = 1.000.000
 *   Diskon: 5% + 1%
 *   → 1.000.000 * (1 - 0.05) = 950.000
 *   → 950.000 * (1 - 0.01)   = 940.500
 *   Total diskon = 59.500
 *
 * Format discount_json:
 *   [
 *     {"type": "percent",  "value": 5},
 *     {"type": "nominal",  "value": 200000},
 *     {"type": "percent",  "value": 1}
 *   ]
 */
class DiscountService
{
    // =============================================
    // CORE: Hitung total setelah diskon bertingkat
    // =============================================

    /**
     * Terapkan semua layer diskon ke subtotal secara berurutan.
     *
     * @param  float  $subtotal     Nilai sebelum diskon
     * @param  array  $discounts    Array of ["type" => "percent|nominal", "value" => float]
     * @return array  ['after_discount', 'discount_amount', 'breakdown']
     */
    public function apply(float $subtotal, array $discounts): array
    {
        $this->validate($discounts);

        $current   = $subtotal;
        $breakdown = [];

        foreach ($discounts as $index => $layer) {
            $before  = $current;
            $current = $this->applyLayer($current, $layer);
            $reduced = $before - $current;

            $breakdown[] = [
                'index'          => $index,
                'type'           => $layer['type'],
                'value'          => $layer['value'],
                'before'         => round($before, 2),
                'after'          => round($current, 2),
                'amount_reduced' => round($reduced, 2),
                'label'          => $this->formatLabel($layer),
            ];
        }

        return [
            'after_discount'  => round($current, 2),
            'discount_amount' => round($subtotal - $current, 2),
            'breakdown'       => $breakdown,
        ];
    }

    /**
     * Shortcut: hitung grand total dari subtotal + discount_json
     */
    public function calculateGrandTotal(float $subtotal, ?array $discountJson): array
    {
        if (empty($discountJson)) {
            return [
                'after_discount'  => $subtotal,
                'discount_amount' => 0,
                'breakdown'       => [],
            ];
        }

        return $this->apply($subtotal, $discountJson);
    }

    // =============================================
    // LAYER PROCESSOR
    // =============================================

    private function applyLayer(float $current, array $layer): float
    {
        return match ($layer['type']) {
            'percent' => $this->applyPercent($current, (float) $layer['value']),
            'nominal' => $this->applyNominal($current, (float) $layer['value']),
            default   => throw new InvalidArgumentException(
                "Tipe diskon tidak valid: [{$layer['type']}]. Gunakan 'percent' atau 'nominal'."
            ),
        };
    }

    private function applyPercent(float $amount, float $percent): float
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("Persentase diskon harus antara 0-100. Diterima: {$percent}");
        }

        return $amount * (1 - ($percent / 100));
    }

    private function applyNominal(float $amount, float $nominal): float
    {
        if ($nominal < 0) {
            throw new InvalidArgumentException("Nominal diskon tidak boleh negatif. Diterima: {$nominal}");
        }

        // Diskon tidak boleh melebihi nilai saat ini
        return max(0, $amount - $nominal);
    }

    // =============================================
    // VALIDATOR
    // =============================================

    private function validate(array $discounts): void
    {
        if (empty($discounts)) {
            return;
        }

        foreach ($discounts as $i => $layer) {
            if (!isset($layer['type']) || !isset($layer['value'])) {
                throw new InvalidArgumentException(
                    "Discount layer [{$i}] harus memiliki 'type' dan 'value'."
                );
            }

            if (!in_array($layer['type'], ['percent', 'nominal'])) {
                throw new InvalidArgumentException(
                    "Discount type [{$layer['type']}] tidak valid di layer [{$i}]."
                );
            }

            if (!is_numeric($layer['value']) || $layer['value'] < 0) {
                throw new InvalidArgumentException(
                    "Discount value harus angka positif di layer [{$i}]. Diterima: [{$layer['value']}]"
                );
            }
        }
    }

    // =============================================
    // FORMATTER & BUILDER
    // =============================================

    /**
     * Format label untuk display (e.g. "5%", "Rp 200.000")
     */
    public function formatLabel(array $layer): string
    {
        return match ($layer['type']) {
            'percent' => "{$layer['value']}%",
            'nominal' => 'Rp ' . number_format($layer['value'], 0, ',', '.'),
            default   => '?',
        };
    }

    /**
     * Format ringkasan semua diskon (e.g. "5% + 1%" atau "5% + Rp 200.000")
     */
    public function formatSummary(array $discounts): string
    {
        if (empty($discounts)) {
            return 'Tidak ada diskon';
        }

        return collect($discounts)
            ->map(fn($layer) => $this->formatLabel($layer))
            ->join(' + ');
    }

    /**
     * Tentukan discount_type berdasarkan isi discount_json
     */
    public function resolveDiscountType(?array $discounts): string
    {
        if (empty($discounts)) {
            return 'none';
        }

        $types = collect($discounts)->pluck('type')->unique();

        if ($types->count() > 1) {
            return 'mixed';
        }

        return $types->first() === 'percent' ? 'percentage' : 'nominal';
    }

    // =============================================
    // BUILDER HELPERS (untuk Filament Form)
    // =============================================

    /**
     * Build discount_json dari input sederhana single diskon
     */
    public static function buildSingle(string $type, float $value): array
    {
        return [['type' => $type, 'value' => $value]];
    }

    /**
     * Parse string "5+1" atau "5%+200000" menjadi discount_json
     * (opsional, untuk import / quick entry)
     */
    public static function parseString(string $input): array
    {
        $layers = [];
        $parts  = array_map('trim', explode('+', $input));

        foreach ($parts as $part) {
            if (str_ends_with($part, '%')) {
                $layers[] = ['type' => 'percent', 'value' => (float) rtrim($part, '%')];
            } else {
                $val = (float) str_replace(['.', ','], ['', '.'], $part);
                $layers[] = ['type' => 'nominal', 'value' => $val];
            }
        }

        return $layers;
    }

    /**
     * Validasi bahwa total diskon tidak melebihi subtotal
     */
    public function validateTotalDiscount(float $subtotal, array $discounts): bool
    {
        $result = $this->apply($subtotal, $discounts);
        return $result['after_discount'] >= 0;
    }
}