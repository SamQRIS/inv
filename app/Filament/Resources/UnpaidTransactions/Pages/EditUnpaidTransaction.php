<?php

namespace App\Filament\Resources\UnpaidTransactions\Pages;

use App\Filament\Resources\UnpaidTransactions\UnpaidTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUnpaidTransaction extends EditRecord
{
    protected static string $resource = UnpaidTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
