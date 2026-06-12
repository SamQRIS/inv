<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }

    protected static ?string $title   = 'Invoice';
 
    // ── 2 Tab: DO dan End User ───────────────────────────────
    public function getTabs(): array
    {
        $unpaidDo      = Transaction::whereHas('customer', fn($q) => $q->where('type', 'do'))->where('payment_status', 'unpaid')->count();
        $unpaidEndUser = Transaction::whereHas('customer', fn($q) => $q->where('type', 'end_user'))->where('payment_status', 'unpaid')->count();
        $noCustomer    = Transaction::whereNull('customer_id')->where('payment_status', 'unpaid')->count();
 
        return [
            'do' => Tab::make('Invoice DO')
                ->icon('heroicon-o-building-office')
                ->badge($unpaidDo > 0 ? $unpaidDo : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) =>
                    $query->whereHas('customer', fn($q) => $q->where('type', 'do'))
                ),
 
            'end_user' => Tab::make('Invoice End User')
                ->icon('heroicon-o-user')
                ->badge(($unpaidEndUser + $noCustomer) > 0 ? ($unpaidEndUser + $noCustomer) : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) =>
                    $query->where(function ($q) {
                        $q->whereNull('customer_id')
                          ->orWhereHas('customer', fn($q2) => $q2->where('type', 'end_user'));
                    })
                ),
        ];
    }
}
