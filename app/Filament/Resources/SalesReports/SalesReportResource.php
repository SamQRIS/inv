<?php

namespace App\Filament\Resources\SalesReports;

use App\Filament\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Resources\SalesReports\Schemas\SalesReportForm;
use App\Filament\Resources\SalesReports\Tables\SalesReportsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalesReportResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($r): bool
    {
        return false;
    }
    public static function canDelete($r): bool
    {
        return false;
    }

    // SalesReportResource.php
    // SalesReportResource.php
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Transaction::query()
            ->whereBetween('transaction_date', [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ]);
    }
    public static function form(Schema $schema): Schema
    {
        return SalesReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReports::route('/'),
        ];
    }
}
