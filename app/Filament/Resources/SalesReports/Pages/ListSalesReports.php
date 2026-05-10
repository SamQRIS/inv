<?php

namespace App\Filament\Resources\SalesReports\Pages;

use App\Filament\Resources\SalesReports\SalesReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesReports extends ListRecords
{
    protected static string $resource = SalesReportResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make(),
    //     ];
    // }

    protected static ?string $title = 'Laporan Penjualan';
}
