<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Livewire\PaymentSummaryWidget;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    // ✅ Handle filter tab di sini
    public function getTableQuery(): ?Builder
    {
        $query = Payment::query();

        return match ($this->activeTab) {
            'today'      => (clone $query)->whereDate('payment_date', today()),
            'this_month' => (clone $query)->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year),
            default      => $query,
        };
    }

    public function getTabs(): array
    {
        return [
            'all'        => Tab::make('Semua'),
            'today'      => Tab::make('Hari Ini'),
            'this_month' => Tab::make('Bulan Ini'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentSummaryWidget::class,
        ];
    }
}
