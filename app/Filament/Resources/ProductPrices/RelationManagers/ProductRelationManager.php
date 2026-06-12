<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductFabric;
use App\Models\ProductSize;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Daftar Harga per Variasi';

    public function form(Schema $schema): Schema
    {
        $productType = $this->getOwnerRecord()->product_type;

        return $schema->components([

            Select::make('size_id')
                ->label('Ukuran')
                ->options(
                    ProductSize::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                )
                ->required()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Ukuran Baru')
                        ->required()
                        ->placeholder('Contoh: 200x200'),
                ])
                ->createOptionUsing(fn(array $data) => ProductSize::create([
                    'name'       => $data['name'],
                    'sort_order' => ProductSize::max('sort_order') + 1,
                    'is_active'  => true,
                ])->id),

            Select::make('fabric_id')
                ->label('Jenis Kain')
                ->options(
                    ProductFabric::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                )
                ->nullable()
                ->searchable()
                ->visible($productType === 'divan')
                ->placeholder('— Pilih kain —')
                ->helperText('Kosongkan jika harga sama untuk semua kain')
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nama Kain Baru')
                        ->required()
                        ->placeholder('Velvet, Kanvas, dll'),
                ])
                ->createOptionUsing(fn(array $data) => ProductFabric::create([
                    'name'       => strtoupper($data['name']),
                    'sort_order' => ProductFabric::max('sort_order') + 1,
                    'is_active'  => true,
                ])->id),

            TextInput::make('price')
                ->label('Harga')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        $productType = $this->getOwnerRecord()->product_type;

        return $table
            ->columns([
                TextColumn::make('size.name')
                    ->label('Ukuran')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('fabric.name')
                    ->label('Jenis Kain')
                    ->badge()
                    ->color('gray')
                    ->placeholder('— Semua Kain —')
                    ->visible($productType === 'divan'),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('+ Tambah Harga'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('size_id')
            ->emptyStateHeading('Belum ada harga')
            ->emptyStateDescription(
                $productType === 'flat'
                    ? 'Produk tipe flat menggunakan harga dari tab Harga di atas.'
                    : 'Tambahkan harga per ukuran' . ($productType === 'divan' ? ' dan jenis kain' : '') . '.'
            )
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}