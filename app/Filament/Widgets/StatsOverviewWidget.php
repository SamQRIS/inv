<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    // 5 kolom = semua stat tampil 1 baris di desktop
    // protected int | string $columns = 5;
    #[Override]
    public function getColumns(): int | array
    {
        return [
            'md' => 3,
            'xl' => 5,
        ];
    }

    protected int | string | array $columnSpan = 'full';
    // protected int | string | array $columnSpan = [
    //     'md' => 3,
    //     'xl' => 5,
    // ];

    protected function getStats(): array
    {
        $today    = today();
        $thisMonth = now()->startOfMonth();

        // Transaksi hari ini
        // ✅ SESUDAH — tambah whereNotIn di setiap query:
        $todaySales = Transaction::whereDate('transaction_date', $today)
            ->whereNotIn('payment_status', ['void', 'cancelled'])
            ->sum('grand_total');

        $todayCount = Transaction::whereDate('transaction_date', $today)
            ->whereNotIn('payment_status', ['void', 'cancelled'])
            ->count();

        $monthSales = Transaction::where('transaction_date', '>=', $thisMonth)
            ->whereNotIn('payment_status', ['void', 'cancelled'])
            ->sum('grand_total');

        $piutang = Transaction::whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('amount_remaining');

        // Stok menipis
        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'minimum_stock')->where('is_active', true)->count();

        // DO pending
        $pendingDO = \App\Models\Delivery::where('status', '!=', 'completed')
            ->whereHas(
                'transaction',
                fn($q) =>
                $q->whereNotIn('payment_status', ['void', 'cancelled'])
            )
            ->count();

        return [
            Stat::make('Penjualan Hari Ini', 'Rp ' . number_format($todaySales, 0, ',', '.'))
                ->description("{$todayCount} transaksi")
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Penjualan Bulan Ini', 'Rp ' . number_format($monthSales, 0, ',', '.'))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Total Piutang', 'Rp ' . number_format($piutang, 0, ',', '.'))
                ->description('Belum + Sebagian bayar')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($piutang > 0 ? 'danger' : 'success'),

            Stat::make('Stok Menipis', $lowStockCount . ' produk')
                ->description('Di bawah minimum stok')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),

            Stat::make('DO Belum Selesai', $pendingDO . ' delivery')
                ->description('Pending + Sebagian terkirim')
                ->descriptionIcon('heroicon-m-truck')
                ->color($pendingDO > 0 ? 'warning' : 'success'),
        ];
    }
}
