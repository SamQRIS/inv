<?php

namespace App\Filament\Resources\UnpaidTransactions\Pages;

use App\Filament\Resources\UnpaidTransactions\UnpaidTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnpaidTransaction extends CreateRecord
{
    protected static string $resource = UnpaidTransactionResource::class;
}
