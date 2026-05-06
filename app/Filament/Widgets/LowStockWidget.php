<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockWidget extends TableWidget
{
    protected static ?int $sort    = 4;
    // protected int | string $columnSpan = 'full';
 
    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk Stok Menipis')
            ->query(
                Product::with(['unit', 'category'])
                    ->whereColumn('stock_quantity', '<=', 'minimum_stock')
                    ->where('is_active', true)
                    ->orderBy('stock_quantity')
            )
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU'),
 
                TextColumn::make('name')
                    ->label('Produk'),
 
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge(),
 
                TextColumn::make('stock_quantity')
                    ->label('Stok')
                    ->color('danger')
                    ->weight('bold')
                    ->suffix(fn($record) => ' ' . $record->unit->symbol),
 
                TextColumn::make('minimum_stock')
                    ->label('Minimum'),
            ])
            ->emptyStateHeading('Semua stok aman')
            ->emptyStateIcon('heroicon-o-check-circle')
            ;
    }
}
