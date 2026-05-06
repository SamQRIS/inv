<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\TrashedFilter;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['category', 'unit', 'productStocks.warehouse']))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')->searchable()->sortable()->copyable()->width('120px'),

                TextColumn::make('name')
                    ->label('Nama Produk')->searchable()->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')->badge()->sortable(),

                TextColumn::make('selling_price')
                    ->label('Harga Jual')->money('IDR')->sortable(),

                TextColumn::make('stock_quantity')
                    ->label('Stok Total')
                    ->sortable()
                    ->color(fn(Product $r) => $r->isLowStock() ? 'danger' : 'success')
                    ->weight(fn(Product $r) => $r->isLowStock() ? 'bold' : 'normal')
                    ->suffix(fn(Product $r) => ' ' . $r->unit->symbol),

                TextColumn::make('warehouse_stocks')
                    ->label('Per Gudang')
                    ->getStateUsing(function (Product $record) {
                        // Relasi sudah di-eager load via modifyQueryUsing
                        return $record->productStocks
                            ->map(fn($ps) => "{$ps->warehouse->code}: {$ps->quantity}")
                            ->join(' | ');
                    })
                    ->placeholder('-')
                    ->wrap()
                    ->visibleFrom('xl'),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')->options(Category::pluck('name', 'id')),
 
                SelectFilter::make('warehouse')
                    ->label('Ada di Gudang')
                    ->options(Warehouse::active()->pluck('name', 'id'))
                    ->query(fn($query, array $data) =>
                        $data['value']
                            ? $query->whereHas('productStocks', fn($q) =>
                                $q->where('warehouse_id', $data['value'])->where('quantity', '>', 0))
                            : $query
                    ),
 
                Filter::make('low_stock')
                    ->label('Stok Menipis (Global)')
                    ->query(fn($q) => $q->whereColumn('stock_quantity', '<=', 'minimum_stock')),
 
                TernaryFilter::make('is_active')->label('Status'),
            ])
            ->recordActions([
                ViewAction::make(),
 
                Action::make('mutasi_stok')
                    ->label('Mutasi Stok')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form(function (Product $record) {
                        return [
                            Select::make('warehouse_id')
                                ->label('Gudang')
                                ->options(Warehouse::active()->pluck('name', 'id'))
                                ->required()->live()
                                ->afterStateUpdated(fn($state, Set $set) =>
                                    $set('current_stock', $state ? $record->stockAt((int) $state) : null)
                                ),
 
                            Placeholder::make('current_stock')
                                ->label('Stok Saat Ini')
                                ->content(fn(Get $get) =>
                                    ($get('current_stock') ?? '-') . ' ' . $record->unit->symbol
                                ),
 
                            Select::make('type')
                                ->label('Tipe Mutasi')
                                ->options([
                                    'in'         => 'Stok Masuk',
                                    'out'        => 'Stok Keluar',
                                    'adjustment' => 'Opname / Penyesuaian',
                                ])
                                ->required()->live(),
 
                            TextInput::make('quantity')
                                ->label(fn(Get $get) => $get('type') === 'adjustment'
                                    ? 'Set Stok Menjadi'
                                    : 'Jumlah')
                                ->numeric()->minValue(0)->required(),
 
                            Textarea::make('notes')
                                ->label('Keterangan')->required(),
                        ];
                    })
                    ->action(function (Product $record, array $data, StockService $stockService) {
                        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
                        match ($data['type']) {
                            'in'         => $stockService->addStock($record, (int)$data['quantity'], $warehouse, 'manual', null, $data['notes']),
                            'out'        => $stockService->reduceStock($record, (int)$data['quantity'], $warehouse, 'manual', 0, $data['notes']),
                            'adjustment' => $stockService->adjustStock($record, $warehouse, (int)$data['quantity'], $data['notes']),
                        };
                        Notification::make()->success()->title('Mutasi stok berhasil.')->send();
                    }),
 
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
