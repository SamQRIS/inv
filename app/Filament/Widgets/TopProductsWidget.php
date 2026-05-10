<?php

namespace App\Filament\Widgets;

use App\Models\TransactionItem;
use Filament\Widgets\ChartWidget;

class TopProductsWidget extends ChartWidget
{
    protected static ?int    $sort    = 3;
    protected ?string $heading = 'Produk Terlaris';
    protected int | string |array  $columnSpan = 1;
    protected ?string $maxHeight = '200px';
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
        $query = TransactionItem::selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(10);

        // Join ke transactions untuk filter tanggal
        $query->whereHas('transaction', function ($q) {
            match ($this->filter) {
                'this_month' => $q->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year),
                'last_month' => $q->whereMonth('transaction_date', now()->subMonth()->month)->whereYear('transaction_date', now()->subMonth()->year),
                'this_year'  => $q->whereYear('transaction_date', now()->year),
                default      => null,
            };
        });

        $results = $query->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Qty Terjual',
                    'data'            => $results->pluck('total_qty')->map(fn($v) => (float)$v)->toArray(),
                    'backgroundColor' => 'rgba(59,130,246,0.8)',
                    'borderColor'     => '#3b82f6',
                    'borderWidth'     => 1,
                    'yAxisID'         => 'y',
                ],
                [
                    'label'           => 'Revenue',
                    'data'            => $results->pluck('total_revenue')->map(fn($v) => (float)$v)->toArray(),
                    'backgroundColor' => 'rgba(16,185,129,0.8)',
                    'borderColor'     => '#10b981',
                    'borderWidth'     => 1,
                    'yAxisID'         => 'y1',
                ],
            ],
            'labels' => $results->pluck('product_name')
                ->map(fn($n) => strlen($n) > 20 ? substr($n, 0, 20) . '…' : $n)
                ->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // horizontal bar
            'scales'    => [
                'y'  => ['beginAtZero' => true],
                'y1' => [
                    'position'          => 'right',
                    'beginAtZero'       => true,
                    'grid'              => ['drawOnChartArea' => false],
                    'ticks'             => ['callback' => "function(v){return 'Rp '+v.toLocaleString('id-ID')}"],
                ],
            ],
            'plugins'   => ['legend' => ['position' => 'top']],
        ];
    }
}
