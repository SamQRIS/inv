<?php

namespace App\Filament\Resources\Trashes\Pages;

use App\Filament\Resources\Trashes\TrashResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrash extends EditRecord
{
    protected static string $resource = TrashResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
