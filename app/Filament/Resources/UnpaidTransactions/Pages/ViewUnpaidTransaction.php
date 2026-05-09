<?php

namespace App\Filament\Resources\UnpaidTransactions\Pages;

use App\Filament\Resources\UnpaidTransactions\UnpaidTransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnpaidTransaction extends ViewRecord
{
    protected static string $resource = UnpaidTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
