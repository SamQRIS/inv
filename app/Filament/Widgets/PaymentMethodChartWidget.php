<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class PaymentMethodChartWidget extends ChartWidget
{
    protected static ?int    $sort    = 3;
    protected ?string $heading = 'Pembayaran per Metode';
    protected int | string |array  $columnSpan = 1;

    protected ?string $maxHeight= '200px';

    public ?string $filter = 'this_month';

    protected function getFilters(): ?array
    {
        return [
            'this_month' => 'Bulan Ini',
            'last_month' => 'Bulan Lalu',
            'this_year'  => 'Tahun Ini',
        ];
    }

    protected function getData(): array
    {
        $query = Payment::selectRaw('payment_method_id, SUM(amount) as total')
            ->with('paymentMethod')
            ->groupBy('payment_method_id');

        match($this->filter) {
            'this_month' => $query->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year),
            'last_month' => $query->whereMonth('payment_date', now()->subMonth()->month)->whereYear('payment_date', now()->subMonth()->year),
            'this_year'  => $query->whereYear('payment_date', now()->year),
        };

        $results = $query->get();

        $labels = $results->map(fn($r) => $r->paymentMethod?->name ?? 'Tidak Diketahui')->toArray();
        $data   = $results->map(fn($r) => (float) $r->total)->toArray();

        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'];

        return [
            'datasets' => [[
                'data'            => $data,
                'backgroundColor' => array_slice($colors, 0, count($data)),
                'borderWidth'     => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'cutout' => '65%',
        ];
    }
}