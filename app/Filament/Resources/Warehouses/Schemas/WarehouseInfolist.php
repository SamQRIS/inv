<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Gudang')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('code')->label('Kode')->badge()->color('gray'),
                    TextEntry::make('pic')->label('PIC')->placeholder('-'),
                    TextEntry::make('phone')->label('Telepon')->placeholder('-'),
                    IconEntry::make('is_default')->label('Default')->boolean(),
                    IconEntry::make('is_active')->label('Aktif')->boolean(),
                    TextEntry::make('address')->label('Alamat')
                        ->columnSpanFull()->placeholder('-'),
                ]),
 
            Section::make('Stok Produk di Gudang Ini')
                ->schema([
                    RepeatableEntry::make('productStocks')
                        ->label('')
                        ->schema([
                            TextEntry::make('product.name')
                                ->label('Produk'),
                            TextEntry::make('product.sku')
                                ->label('SKU')
                                ->badge()->color('gray'),
                            TextEntry::make('quantity')
                                ->label('Stok')
                                ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                                ->weight('bold'),
                            TextEntry::make('minimum_stock')
                                ->label('Min. Stok'),
                            TextEntry::make('product.unit.symbol')
                                ->label('Satuan'),
                        ])
                        ->columns(5),
                ]),
            ]);
    }
}
