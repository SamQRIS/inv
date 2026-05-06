<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Product;
use App\Models\ProductStock;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width('40px')
                    ->sortable(),
 
                TextColumn::make('name')
                    ->label('Nama Gudang')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Warehouse $r) => $r->address),
 
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('gray'),
 
                TextColumn::make('pic')
                    ->label('PIC')
                    ->placeholder('-'),
 
                TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('-'),
 
                // Total produk yang punya stok di gudang ini
                TextColumn::make('productStocks_count')
                    ->label('Jenis Produk')
                    ->counts('productStocks')
                    ->suffix(' produk'),
 
                // Total stok semua produk di gudang ini
                TextColumn::make('total_stock')
                    ->label('Total Stok')
                    ->getStateUsing(fn(Warehouse $r) => number_format($r->productStocks()->sum('quantity')) . ' unit'),
 
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueColor('warning'),
 
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                 // Action: Mutasi Stok — tambah/kurangi/opname di gudang ini
                Action::make('mutasi_stok')
                    ->label('Mutasi Stok')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form(function (Warehouse $record) {
                        return [
                            Select::make('product_id')
                                ->label('Produk')
                                ->options(Product::active()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) use ($record) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        $set('current_stock', $product?->stockAt($record) ?? 0);
                                    }
                                }),
 
                            Placeholder::make('current_stock')
                                ->label('Stok Saat Ini di Gudang Ini')
                                ->content(fn(Get $get) => $get('current_stock') ?? '-'),
 
                            Select::make('type')
                                ->label('Tipe Mutasi')
                                ->options([
                                    'in'         => '📥 Stok Masuk',
                                    'out'        => '📤 Stok Keluar',
                                    'adjustment' => '🔧 Opname / Penyesuaian',
                                ])
                                ->required()
                                ->live(),
 
                            TextInput::make('quantity')
                                ->label(fn(Get $get) => $get('type') === 'adjustment' ? 'Stok Baru (set ke angka ini)' : 'Jumlah')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
 
                            Textarea::make('notes')
                                ->label('Keterangan')
                                ->required(),
                        ];
                    })
                    ->action(function (Warehouse $record, array $data, StockService $stockService) {
                        $product = Product::findOrFail($data['product_id']);
 
                        match ($data['type']) {
                            'in'  => $stockService->addStock(
                                $product, $data['quantity'], $record, 'manual', null, $data['notes']
                            ),
                            'out' => $stockService->reduceStock(
                                $product, $data['quantity'], $record, 'manual', 0, $data['notes']
                            ),
                            'adjustment' => $stockService->adjustStock(
                                $product, $record, $data['quantity'], $data['notes']
                            ),
                        };
 
                        Notification::make()->success()->title('Mutasi stok berhasil disimpan.')->send();
                    }),
 
                // Action: Transfer Antar Gudang
                Action::make('transfer_stok')
                    ->label('Transfer ke Gudang Lain')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->form(function (Warehouse $record) {
                        return [
                            Select::make('product_id')
                                ->label('Produk')
                                ->options(
                                    // Hanya produk yang punya stok di gudang ini
                                    ProductStock::where('warehouse_id', $record->id)
                                        ->where('quantity', '>', 0)
                                        ->with('product')
                                        ->get()
                                        ->mapWithKeys(fn($ps) => [
                                            $ps->product_id => "{$ps->product->name} (Stok: {$ps->quantity})"
                                        ])
                                )
                                ->searchable()
                                ->required(),
 
                            Select::make('to_warehouse_id')
                                ->label('Ke Gudang')
                                ->options(
                                    Warehouse::active()
                                        ->where('id', '!=', $record->id)
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required(),
 
                            TextInput::make('quantity')
                                ->label('Jumlah Transfer')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
 
                            Textarea::make('notes')
                                ->label('Keterangan'),
                        ];
                    })
                    ->action(function (Warehouse $record, array $data, StockService $stockService) {
                        $product     = Product::findOrFail($data['product_id']);
                        $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);
 
                        $stockService->transferStock(
                            $product, $data['quantity'],
                            $record, $toWarehouse,
                            $data['notes'] ?? null
                        );
 
                        Notification::make()->success()
                            ->title("Transfer {$data['quantity']} unit {$product->name} ke {$toWarehouse->name} berhasil.")
                            ->send();
                    }),
 
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Warehouse $record) {
                        if ($record->productStocks()->where('quantity', '>', 0)->exists()) {
                            Notification::make()->danger()
                                ->title('Tidak bisa hapus gudang yang masih memiliki stok.')
                                ->send();
                            $this->halt();
                        }
                    }),
            ])
            ->defaultSort('sort_order')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
