<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Override;

class SalesChartWidget extends ChartWidget
{
    protected static ?int    $sort    = 2;
    // protected static ?string $heading = null; // kita set manual di getHeading()
    protected int | string | array $columnSpan = 'full';

    // Filter: bisa dipilih user langsung dari widget
    public ?string $filter = 'this_month';

    protected ?string $maxHeight = '200px';

    // #[Override]
    public function getHeading(): string
    {
        return match ($this->filter) {
            'this_month' => 'Penjualan Harian — ' . now()->translatedFormat('F Y'),
            'last_month' => 'Penjualan Harian — ' . now()->subMonth()->translatedFormat('F Y'),
            'this_year'  => 'Penjualan Bulanan — ' . now()->format('Y'),
            'last_year'  => 'Penjualan Bulanan — ' . now()->subYear()->format('Y'),
            default      => 'Grafik Penjualan',
        };
    }

    protected function getFilters(): ?array
    {
        return [
            'this_month' => 'Bulan Ini',
            'last_month' => 'Bulan Lalu',
            'this_year'  => 'Tahun Ini',
            'last_year'  => 'Tahun Lalu',
        ];
    }

    protected function getData(): array
    {
        return match ($this->filter) {
            'this_month', 'last_month' => $this->getDailyData(),
            'this_year',  'last_year'  => $this->getMonthlyData(),
            default                    => $this->getDailyData(),
        };
    }

    private function getDailyData(): array
    {
        $isThisMonth = $this->filter === 'this_month';
        $date        = $isThisMonth ? now() : now()->subMonth();
        $start       = $date->copy()->startOfMonth();
        $end         = $isThisMonth ? now() : $date->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        // Ambil data penjualan per hari
        $sales = Transaction::selectRaw('DAY(transaction_date) as day, SUM(grand_total) as total, COUNT(*) as count')
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->groupBy('day')
            ->pluck('total', 'day');

        // Ambil data pembayaran per hari
        $payments = Payment::selectRaw('DAY(payment_date) as day, SUM(amount) as total')
            ->whereMonth('payment_date', $date->month)
            ->whereYear('payment_date', $date->year)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels       = [];
        $salesData    = [];
        $paymentData  = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[]      = $day;
            $salesData[]   = (float) ($sales[$day] ?? 0);
            $paymentData[] = (float) ($payments[$day] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Penjualan (Grand Total)',
                    'data'            => $salesData,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'tension'         => 0.3,
                    'fill'            => true,
                    'yAxisID'         => 'y',
                ],
                [
                    'label'           => 'Pembayaran Diterima',
                    'data'            => $paymentData,
                    'borderColor'     => '#10b981',
                    'backgroundColor' => 'rgba(16,185,129,0.1)',
                    'tension'         => 0.3,
                    'fill'            => false,
                    'yAxisID'         => 'y',
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getMonthlyData(): array
    {
        $isThisYear = $this->filter === 'this_year';
        $year       = $isThisYear ? now()->year : now()->subYear()->year;

        $sales = Transaction::selectRaw('MONTH(transaction_date) as month, SUM(grand_total) as total, COUNT(*) as count')
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->pluck('total', 'month');

        $prevSales = Transaction::selectRaw('MONTH(transaction_date) as month, SUM(grand_total) as total')
            ->whereYear('transaction_date', $year - 1)
            ->groupBy('month')
            ->pluck('total', 'month');

        $labels      = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $salesData   = [];
        $prevData    = [];

        for ($m = 1; $m <= 12; $m++) {
            $salesData[] = (float) ($sales[$m] ?? 0);
            $prevData[]  = (float) ($prevSales[$m] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label'           => "Penjualan {$year}",
                    'data'            => $salesData,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.15)',
                    'tension'         => 0.3,
                    'fill'            => true,
                    'yAxisID'         => 'y',
                ],
                [
                    'label'           => "Penjualan " . ($year - 1),
                    'data'            => $prevData,
                    'borderColor'     => '#94a3b8',
                    'backgroundColor' => 'rgba(148,163,184,0.1)',
                    'tension'         => 0.3,
                    'fill'            => false,
                    'borderDash'      => [5, 5],
                    'yAxisID'         => 'y',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'interaction'         => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend'  => ['position' => 'top'],
                'tooltip' => [
                    'callbacks' => [
                        // Format angka Rupiah di tooltip — dihandle via JS di bawah
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'position'    => 'left',
                    'beginAtZero' => true,
                    'ticks'       => [
                        'callback' => "function(v){return 'Rp '+v.toLocaleString('id-ID')}",
                    ],
                ],
            ],
        ];
    }
}
