<?php

namespace App\Filament\Resources\Displays\Pages;

use App\Filament\Resources\Displays\DisplayResource;
use App\Models\TransactionItem;
use Filament\Resources\Pages\ListRecords; 
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDisplays extends ListRecords
{
    protected static string $resource = DisplayResource::class;

    protected static ?string $title = 'Stok Display & Konsinyasi';

    public function getTableQuery(): ?Builder
    {
        $query = TransactionItem::query()
            ->with(['transaction.customer', 'product', 'product.unit'])
            ->where('is_display', true);

        return match ($this->activeTab) {
            'pending'  => (clone $query)->where('display_status', 'pending'),
            'sold'     => (clone $query)->where('display_status', 'sold'),
            'returned' => (clone $query)->where('display_status', 'returned'),
            default    => $query,
        };
    }

    public function getTabs(): array
    {
        $pending  = TransactionItem::where('is_display', true)->where('display_status', 'pending')->count();
        $sold     = TransactionItem::where('is_display', true)->where('display_status', 'sold')->count();
        $returned = TransactionItem::where('is_display', true)->where('display_status', 'returned')->count();

        return [
            'all'      => Tab::make('Semua')->badge($pending + $sold + $returned),
            'pending'  => Tab::make('Di Lokasi Display')->badge($pending)->badgeColor('warning'),
            'sold'     => Tab::make('Terjual')->badge($sold)->badgeColor('success'),
            'returned' => Tab::make('Diretur')->badge($returned)->badgeColor('info'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}