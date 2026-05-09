<?php

namespace App\Filament\Resources\UnpaidTransactions;

use App\Filament\Resources\UnpaidTransactions\Pages\ListUnpaidTransactions;
use App\Filament\Resources\UnpaidTransactions\Pages\ViewUnpaidTransaction;
use App\Filament\Resources\UnpaidTransactions\Schemas\UnpaidTransactionForm;
use App\Filament\Resources\UnpaidTransactions\Schemas\UnpaidTransactionInfolist;
use App\Filament\Resources\UnpaidTransactions\Tables\UnpaidTransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UnpaidTransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;
    protected static ?string $navigationLabel = 'Tagihan Belum Lunas';
    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return UnpaidTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UnpaidTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnpaidTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Transaction::whereIn('payment_status', ['unpaid', 'partial'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = Transaction::whereIn('payment_status', ['unpaid', 'partial'])->count();
        return $count > 0 ? 'danger' : 'success';
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     $query = Transaction::query()
    //         ->whereIn('payment_status', ['unpaid', 'partial'])
    //         ->latest('transaction_date');

    //     // dd(get_class($query->getModel())); // ← tambah ini

    //     return $query;
    // }

    public static function getPages(): array
    {
        return [
            'index' => ListUnpaidTransactions::route('/'),
            'view'  => ViewUnpaidTransaction::route('/{record}'),
        ];
    }
}
