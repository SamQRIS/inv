<?php

namespace App\Filament\Resources\UnpaidTransactions\Schemas;

use Filament\Schemas\Schema;

class UnpaidTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }
}
