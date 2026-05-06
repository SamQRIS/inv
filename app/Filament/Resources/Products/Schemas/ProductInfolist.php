<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Infolists\Components\Section::make('Informasi Produk')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nama Produk')->columnSpan(2),
                    Infolists\Components\TextEntry::make('sku')->label('SKU')->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('category.name')->label('Kategori')->badge(),
                    Infolists\Components\TextEntry::make('unit.name')->label('Satuan'),
                    Infolists\Components\TextEntry::make('supplier.name')->label('Supplier')->placeholder('-'),
                    Infolists\Components\TextEntry::make('cost_price')->label('Harga Modal')->money('IDR'),
                    Infolists\Components\TextEntry::make('selling_price')->label('Harga Jual')->money('IDR'),
                    Infolists\Components\TextEntry::make('minimum_stock')->label('Min. Stok Global'),
                    Infolists\Components\IconEntry::make('is_active')->label('Aktif')->boolean(),
                ]),
 
            Infolists\Components\Section::make('Stok per Gudang')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('productStocks')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('warehouse.name')->label('Gudang'),
                            Infolists\Components\TextEntry::make('warehouse.code')->label('Kode')->badge()->color('gray'),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('Stok')
                                ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                                ->weight('bold'),
                            Infolists\Components\TextEntry::make('minimum_stock')->label('Min.'),
                            Infolists\Components\TextEntry::make('status_label')
                                ->label('Status')
                                ->getStateUsing(fn($record) => $record->isLowStock() ? '⚠️ Menipis' : '✅ Aman'),
                        ])
                        ->columns(5),
 
                    Infolists\Components\TextEntry::make('stock_quantity')
                        ->label('Total Stok (Semua Gudang)')
                        ->weight('bold')
                        ->size('xl')
                        ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                        ->suffix(fn($record) => ' ' . $record->unit?->symbol),
                ]),
            ]);
    }
}
