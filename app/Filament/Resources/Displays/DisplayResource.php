<?php

namespace App\Filament\Resources\Displays;

use App\Filament\Resources\Displays\Pages\CreateDisplay;
use App\Filament\Resources\Displays\Pages\EditDisplay;
use App\Filament\Resources\Displays\Pages\ListDisplays;
use App\Filament\Resources\Displays\Schemas\DisplayForm;
use App\Filament\Resources\Displays\Tables\DisplaysTable;
use App\Models\Display;
use App\Models\TransactionItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DisplayResource extends Resource
{
    protected static ?string $model = TransactionItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Stok Display';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Item Display';

    protected static ?string $pluralModelLabel = 'Stok Display';

    protected static ?string $slug = 'displays';


    // Badge: jumlah item display yang masih pending
    public static function getNavigationBadge(): ?string
    {
        $count = TransactionItem::where('is_display', true)
            ->where('display_status', 'pending')
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return DisplayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisplaysTable::configure($table);
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
            'index' => ListDisplays::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }
}
