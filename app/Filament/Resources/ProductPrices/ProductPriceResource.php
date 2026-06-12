<?php

namespace App\Filament\Resources\ProductPrices;

use App\Filament\Resources\ProductPrices\Pages\ManageProductPrices;
use App\Models\Product;
use App\Models\ProductFabric;
use App\Models\ProductPrice;
use App\Models\ProductSize;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductPriceResource extends Resource
{
    protected static ?string $model = ProductPrice::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Harga Produk';

    protected static ?string $modelLabel = 'Harga';

    protected static ?string $pluralModelLabel = 'Daftar Harga Produk';

    protected static ?string $slug = 'product-prices';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Produk')
                    ->options(
                        Product::where('is_active', true)
                            ->whereIn('product_type', ['divan', 'kasur'])
                            ->with('category')
                            ->get()
                            ->mapWithKeys(fn($p) => [$p->id => "[{$p->category->name}] {$p->name}"])
                    )
                    ->searchable()->required()->live(),

                Select::make('size_id')
                    ->label('Ukuran')
                    ->options(ProductSize::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->required()->searchable(),

                Select::make('fabric_id')
                    ->label('Jenis Kain')
                    ->options(ProductFabric::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->nullable()
                    ->placeholder('— (Khusus kasur, kosongkan) —')
                    ->helperText('Isi untuk divan, kosongkan untuk kasur')
                    ->visible(function (Get $get) {
                        $id = $get('product_id');
                        if (!$id) return false;
                        return Product::find($id)?->product_type === 'divan';
                    }),

                TextInput::make('price')
                    ->label('Harga')
                    ->numeric()->prefix('Rp')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Produk')->searchable()->sortable(),
                TextColumn::make('size.name')->label('Ukuran')->badge(),
                TextColumn::make('fabric.name')->label('Kain')->placeholder('—')->badge()->color('gray'),
                TextColumn::make('price')->label('Harga')->money('IDR')->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->options(Product::whereIn('product_type', ['divan', 'kasur'])->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('product_id')
            ->recordActions([EditAction::make()])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductPrices::route('/'),
        ];
    }
}
