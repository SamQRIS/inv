<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Schemas\Schema;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Infolists\Components\Section::make('Detail Gudang')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nama'),
                    Infolists\Components\TextEntry::make('code')->label('Kode')->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('pic')->label('PIC')->placeholder('-'),
                    Infolists\Components\TextEntry::make('phone')->label('Telepon')->placeholder('-'),
                    Infolists\Components\IconEntry::make('is_default')->label('Default')->boolean(),
                    Infolists\Components\IconEntry::make('is_active')->label('Aktif')->boolean(),
                    Infolists\Components\TextEntry::make('address')->label('Alamat')
                        ->columnSpanFull()->placeholder('-'),
                ]),
 
            Infolists\Components\Section::make('Stok Produk di Gudang Ini')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('productStocks')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')
                                ->label('Produk'),
                            Infolists\Components\TextEntry::make('product.sku')
                                ->label('SKU')
                                ->badge()->color('gray'),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('Stok')
                                ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                                ->weight('bold'),
                            Infolists\Components\TextEntry::make('minimum_stock')
                                ->label('Min. Stok'),
                            Infolists\Components\TextEntry::make('product.unit.symbol')
                                ->label('Satuan'),
                        ])
                        ->columns(5),
                ]),
            ]);
    }
}
