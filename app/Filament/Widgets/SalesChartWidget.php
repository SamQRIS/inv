<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class SalesChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;
    protected ?string $heading = 'Grafik Penjualan 30 Hari';

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '200px';
    // protected int | string $columnSpan = 'full';
 
    protected function getData(): array
    {
        $data = Transaction::selectRaw('DATE(transaction_date) as date, SUM(grand_total) as total, COUNT(*) as count')
            ->where('transaction_date', '>=', now()->subDays(29))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
 
        $labels = [];
        $totals = [];
        $counts = [];
 
        for ($i = 29; $i >= 0; $i--) {
            $date    = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d/m');
            $totals[] = $data->get($date)?->total ?? 0;
            $counts[] = $data->get($date)?->count ?? 0;
        }
 
        return [
            'datasets' => [
                [
                    'label'           => 'Penjualan (Rp)',
                    'data'            => $totals,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.08)',
                    'tension'         => 0.3,
                    'fill'            => true,
                    'yAxisID'         => 'y',
                ],
                [
                    'label'           => 'Jumlah Transaksi',
                    'data'            => $counts,
                    'borderColor'     => '#10b981',
                    'backgroundColor' => 'rgba(16,185,129,0.08)',
                    'tension'         => 0.3,
                    'fill'            => false,
                    'yAxisID'         => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y'  => ['position' => 'left',  'beginAtZero' => true],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }
}
