<?php

namespace App\Filament\Resources\UnpaidTransactions\Pages;

use App\Filament\Resources\UnpaidTransactions\UnpaidTransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUnpaidTransactions extends ListRecords
{
    protected static string $resource = UnpaidTransactionResource::class;

    // ✅ Handle filter tab di sini, bukan di Tab::make()
    public function getTableQuery(): ?Builder
    {
        $query = Transaction::query()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('transaction_date');

        return match ($this->activeTab) {
            'unpaid'  => (clone $query)->where('payment_status', 'unpaid'),
            'partial' => (clone $query)->where('payment_status', 'partial'),
            'overdue' => (clone $query)->where('transaction_date', '<=', now()->subDays(30)),
            default   => $query,
        };
    }

    public function getTabs(): array
    {
        $unpaidCount  = Transaction::where('payment_status', 'unpaid')->count();
        $partialCount = Transaction::where('payment_status', 'partial')->count();
        $overdueCount = Transaction::whereIn('payment_status', ['unpaid', 'partial'])
            ->where('transaction_date', '<=', now()->subDays(30))
            ->count();

        return [
            // ✅ Tab hanya untuk UI — tidak ada query di sini
            'all'     => Tab::make('Semua')->badge($unpaidCount + $partialCount),
            'unpaid'  => Tab::make('Belum Bayar')->badge($unpaidCount)->badgeColor('danger'),
            'partial' => Tab::make('Sebagian')->badge($partialCount)->badgeColor('warning'),
            'overdue' => Tab::make('> 30 Hari')->badge($overdueCount)->badgeColor('danger'),
        ];
    }
}