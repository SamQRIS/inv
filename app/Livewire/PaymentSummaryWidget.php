<?php

namespace App\Livewire;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentSummaryWidget extends StatsOverviewWidget
{
    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected int | string | array $columnSpan = 'full';
 
    protected function getStats(): array
    {
        $today     = today();
        $thisMonth = now()->startOfMonth();
 
        $totalToday = Payment::whereDate('payment_date', $today)->sum('amount');
        $countToday = Payment::whereDate('payment_date', $today)->count();
 
        $totalMonth = Payment::where('payment_date', '>=', $thisMonth)->sum('amount');
        $countMonth = Payment::where('payment_date', '>=', $thisMonth)->count();
 
        $totalAll   = Payment::sum('amount');
        $countAll   = Payment::count();
 
        // Pembayaran pending (transaksi masih unpaid/partial)
        $pendingCount = \App\Models\Transaction::whereIn('payment_status', ['unpaid', 'partial'])->count();
        $pendingAmount = \App\Models\Transaction::whereIn('payment_status', ['unpaid', 'partial'])->sum('amount_remaining');
 
        return [
            Stat::make('Pembayaran Hari Ini', 'Rp ' . number_format($totalToday, 0, ',', '.'))
                ->description("{$countToday} transaksi")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
 
            Stat::make('Pembayaran Bulan Ini', 'Rp ' . number_format($totalMonth, 0, ',', '.'))
                ->description("{$countMonth} transaksi")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),
 
            Stat::make('Total Semua Pembayaran', 'Rp ' . number_format($totalAll, 0, ',', '.'))
                ->description("{$countAll} total transaksi")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
 
            Stat::make('Piutang Belum Lunas', 'Rp ' . number_format($pendingAmount, 0, ',', '.'))
                ->description("{$pendingCount} transaksi menunggu")
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($pendingAmount > 0 ? 'danger' : 'success'),
        ];
    }
}
