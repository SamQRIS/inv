<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Models\ProductionOrder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('No. Pesanan')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(ProductionOrder $record) => $record->statusColor())
                            ->formatStateUsing(fn(ProductionOrder $record) => $record->statusLabel()),

                        TextEntry::make('order_date')
                            ->label('Tanggal Pesan')
                            ->date('d/m/Y'),

                        TextEntry::make('customer.name')
                            ->label('Pemesan')
                            ->placeholder('—'),

                        TextEntry::make('target_date')
                            ->label('Target Selesai')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('delivery_address')
                            ->label('Alamat Pengiriman')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('customer_notes')
                            ->label('Catatan dari Customer')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('production_notes')
                            ->label('Catatan untuk Tim Produksi')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Daftar Pesanan')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextEntry::make('display_name')
                                        ->label('Produk')
                                        ->getStateUsing(fn($record) => $record->displayName())
                                        ->columnSpan(4),

                                    TextEntry::make('spec_summary')
                                        ->label('Spesifikasi')
                                        ->getStateUsing(fn($record) => $record->specSummary() ?: '—')
                                        ->columnSpan(4),

                                    TextEntry::make('quantity')
                                        ->label('Qty')
                                        ->columnSpan(1),

                                    TextEntry::make('item_notes')
                                        ->label('Keterangan')
                                        ->placeholder('—')
                                        ->columnSpan(3),
                                ]),
                            ]),
                    ]),

                Section::make('Transaksi Terkait')
                    ->visible(fn(ProductionOrder $record) => $record->transactions->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('transactions')
                            ->label('')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextEntry::make('invoice_number')
                                        ->label('No. Invoice')
                                        ->columnSpan(4),

                                    TextEntry::make('transaction_date')
                                        ->label('Tanggal')
                                        ->date('d/m/Y')
                                        ->columnSpan(3),

                                    TextEntry::make('grand_total')
                                        ->label('Grand Total')
                                        ->money('IDR')
                                        ->columnSpan(3),

                                    TextEntry::make('payment_status')
                                        ->label('Status Bayar')
                                        ->badge()
                                        ->columnSpan(2),
                                ]),
                            ]),
                    ]),
            ]);
    }
}