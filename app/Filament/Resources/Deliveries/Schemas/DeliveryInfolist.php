<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Delivery Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('do_number')
                            ->label('No. DO')
                            ->copyable(),

                        TextEntry::make('transaction.invoice_number')
                            ->label('No. Invoice'),

                        TextEntry::make('do_date')
                            ->label('Tanggal DO')
                            ->date('d/m/Y'),

                        TextEntry::make('transaction.customer.name')
                            ->label('Customer')
                            ->default('-'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'pending'   => 'gray',
                                'partial'   => 'warning',
                                'completed' => 'success',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'pending'   => 'Menunggu',
                                'partial'   => 'Sebagian',
                                'completed' => 'Selesai',
                            }),

                        TextEntry::make('user.name')
                            ->label('Dibuat oleh'),
                    ]),

                Section::make('Item Delivery')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Produk'),
                                TextEntry::make('qty_ordered')
                                    ->label('Qty Pesan'),
                                TextEntry::make('qty_delivered')
                                    ->label('Qty Terkirim')
                                    ->color(fn($record) => $record->isFullyDelivered() ? 'success' : 'warning'),
                                TextEntry::make('qty_remaining')
                                    ->label('Sisa')
                                    ->getStateUsing(fn($record) => $record->qtyRemaining())
                                    ->color(fn($record) => $record->qtyRemaining() > 0 ? 'danger' : 'success'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Riwayat Pengiriman')
                    ->schema([
                        RepeatableEntry::make('shipments')
                            ->label('')
                            ->schema([
                                TextEntry::make('shipment_number')
                                    ->label('No. Pengiriman'),
                                TextEntry::make('shipment_date')
                                    ->label('Tanggal')
                                    ->date('d/m/Y'),
                                TextEntry::make('driver_name')
                                    ->label('Driver')
                                    ->default('-'),
                                TextEntry::make('vehicle_number')
                                    ->label('Kendaraan')
                                    ->default('-'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
