<?php

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
               TextColumn::make('moved_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
 
               TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),
 
               TextColumn::make('product.sku')
                    ->label('SKU'),
 
               BadgeColumn::make('type')
                    ->label('Tipe')
                    ->colors([
                        'success' => 'in',
                        'danger'  => 'out',
                        'warning' => 'adjustment',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'in'         => 'Masuk',
                        'out'        => 'Keluar',
                        'adjustment' => 'Penyesuaian',
                        default      => $state,
                    }),
 
               TextColumn::make('quantity')
                    ->label('Qty')
                    ->color(fn($record) => $record->type === 'in' ? 'success' : 'danger')
                    ->formatStateUsing(fn($record) => ($record->type === 'in' ? '+' : '-') . $record->quantity),
 
               TextColumn::make('stock_before')
                    ->label('Stok Sebelum'),
 
               TextColumn::make('stock_after')
                    ->label('Stok Sesudah')
                    ->weight('bold'),
 
               TextColumn::make('reference_type')
                    ->label('Referensi')
                    ->badge()
                    ->color('gray'),
 
               TextColumn::make('user.name')
                    ->label('Oleh'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(['in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Penyesuaian']),
 
                Filter::make('moved_at')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'],  fn($q) => $q->whereDate('moved_at', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('moved_at', '<=', $data['until']));
                    }),
 
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('moved_at', 'desc');
    }
}
