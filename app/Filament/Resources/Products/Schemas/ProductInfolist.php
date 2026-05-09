<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Produk')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nama Produk')->columnSpan(2),
                    TextEntry::make('sku')->label('SKU')->badge()->color('gray'),
                    TextEntry::make('category.name')->label('Kategori')->badge(),
                    TextEntry::make('unit.name')->label('Satuan'),
                    TextEntry::make('supplier.name')->label('Supplier')->placeholder('-'),
                    TextEntry::make('cost_price')->label('Harga Modal')->money('IDR'),
                    TextEntry::make('selling_price')->label('Harga Jual')->money('IDR'),
                    TextEntry::make('minimum_stock')->label('Min. Stok Global'),
                    IconEntry::make('is_active')->label('Aktif')->boolean(),
                ]),
 
            Section::make('Stok per Gudang')
                ->schema([
                    RepeatableEntry::make('productStocks')
                        ->label('')
                        ->schema([
                            TextEntry::make('warehouse.name')->label('Gudang'),
                            TextEntry::make('warehouse.code')->label('Kode')->badge()->color('gray'),
                            TextEntry::make('quantity')
                                ->label('Stok')
                                ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                                ->weight('bold'),
                            TextEntry::make('minimum_stock')->label('Min.'),
                            TextEntry::make('status_label')
                                ->label('Status')
                                ->getStateUsing(fn($record) => $record->isLowStock() ? '⚠️ Menipis' : '✅ Aman'),
                        ])
                        ->columns(5),
 
                    TextEntry::make('stock_quantity')
                        ->label('Total Stok (Semua Gudang)')
                        ->weight('bold')
                        ->size('xl')
                        ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                        ->suffix(fn($record) => ' ' . $record->unit?->symbol),
                ]),
            ]);
    }
}
