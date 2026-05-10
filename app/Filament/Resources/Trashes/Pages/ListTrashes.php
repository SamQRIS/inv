<?php

namespace App\Filament\Resources\Trashes\Pages;

use App\Filament\Resources\Trashes\TrashResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrashes extends ListRecords
{
    protected static string $resource = TrashResource::class;

    protected static ?string $title = 'Riwayat';

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make(),
    //     ];
    // }
}
