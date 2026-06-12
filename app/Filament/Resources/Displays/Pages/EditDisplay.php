<?php

namespace App\Filament\Resources\Displays\Pages;

use App\Filament\Resources\Displays\DisplayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDisplay extends EditRecord
{
    protected static string $resource = DisplayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
