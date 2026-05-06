<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use Filament\Schemas\Schema;

class DeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Infolists\Components\Section::make('Delivery Order')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('do_number')
                            ->label('No. DO')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('transaction.invoice_number')
                            ->label('No. Invoice'),

                        Infolists\Components\TextEntry::make('do_date')
                            ->label('Tanggal DO')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('transaction.customer.name')
                            ->label('Customer')
                            ->default('-'),

                        Infolists\Components\TextEntry::make('status')
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

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Dibuat oleh'),
                    ]),

                Infolists\Components\Section::make('Item Delivery')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Produk'),
                                Infolists\Components\TextEntry::make('qty_ordered')
                                    ->label('Qty Pesan'),
                                Infolists\Components\TextEntry::make('qty_delivered')
                                    ->label('Qty Terkirim')
                                    ->color(fn($record) => $record->isFullyDelivered() ? 'success' : 'warning'),
                                Infolists\Components\TextEntry::make('qty_remaining')
                                    ->label('Sisa')
                                    ->getStateUsing(fn($record) => $record->qtyRemaining())
                                    ->color(fn($record) => $record->qtyRemaining() > 0 ? 'danger' : 'success'),
                            ])
                            ->columns(4),
                    ]),

                Infolists\Components\Section::make('Riwayat Pengiriman')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('shipments')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('shipment_number')
                                    ->label('No. Pengiriman'),
                                Infolists\Components\TextEntry::make('shipment_date')
                                    ->label('Tanggal')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('driver_name')
                                    ->label('Driver')
                                    ->default('-'),
                                Infolists\Components\TextEntry::make('vehicle_number')
                                    ->label('Kendaraan')
                                    ->default('-'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
