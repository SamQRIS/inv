<?php

namespace App\Filament\Resources\ProductFabrics\Pages;

use App\Filament\Resources\ProductFabrics\ProductFabricResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductFabrics extends ManageRecords
{
    protected static string $resource = ProductFabricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
