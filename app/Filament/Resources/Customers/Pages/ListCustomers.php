<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Daftar Pelanggan';

    public ?string $activeTab='do';

    public function getTableQuery(): ?Builder
    {
        $query = Customer::query();

        return match ($this->activeTab) {
            'do' => (clone $query)->where('type', 'do'),
            'end_user' => (clone $query)->where('type', 'end_user'),
            default    => $query,
        };
    }

    public function getTabs(): array
    {
        $do = Customer::where('type', 'do')->count();
        $endUser  = Customer::where('type', 'end_user')->count();
        $all      = $do + $endUser;

        return [
            'all'      => Tab::make('Semua')->badge($all),
            'do' => Tab::make('Customer DO')->badge($do)->badgeColor('primary'),
            'end_user' => Tab::make('End User')->badge($endUser)->badgeColor('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Customer Baru'),
        ];
    }
}