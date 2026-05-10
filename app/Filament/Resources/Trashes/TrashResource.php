<?php

namespace App\Filament\Resources\Trashes;

use App\Filament\Resources\Trashes\Pages\CreateTrash;
use App\Filament\Resources\Trashes\Pages\EditTrash;
use App\Filament\Resources\Trashes\Pages\ListTrashes;
use App\Filament\Resources\Trashes\Schemas\TrashForm;
use App\Filament\Resources\Trashes\Tables\TrashesTable;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Trash;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TrashResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Trash;

    protected static ?string $navigationLabel = 'Tempat Sampah';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 4;

    // Badge: jumlah data yang dihapus
    public static function getNavigationBadge(): ?string
    {
        $count = Transaction::onlyTrashed()->count()
            + Product::onlyTrashed()->count()
            + Customer::onlyTrashed()->count()
            + Delivery::onlyTrashed()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($r): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Tampilkan hanya yang sudah di-soft delete
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->onlyTrashed();
    }

    public static function form(Schema $schema): Schema
    {
        return TrashForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrashesTable::configure($table);
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
            'index' => ListTrashes::route('/'),
            'create' => CreateTrash::route('/create'),
            'edit' => EditTrash::route('/{record}/edit'),
        ];
    }
}
