<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\SalesOrder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi SO')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('so_number')->label('No. SO')->weight('bold')->copyable(),
                        TextEntry::make('status')->label('Status')->badge()
                            ->color(fn(SalesOrder $record) => $record->statusColor())
                            ->formatStateUsing(fn(SalesOrder $record) => $record->statusLabel()),
                        TextEntry::make('order_date')->label('Tgl Order')->date('d/m/Y'),
                        TextEntry::make('customer.name')->label('Customer'),
                        TextEntry::make('requested_delivery_date')->label('Tgl Kirim Diminta')->date('d/m/Y')->placeholder('Belum ditentukan'),
                        TextEntry::make('customer.deposit_balance')->label('Saldo Deposit')->money('IDR')
                            ->color(fn($record) => $record->customer->deposit_balance > 0 ? 'success' : 'danger'),
                        TextEntry::make('grand_total')->label('Grand Total')->money('IDR')->weight('bold'),
                        TextEntry::make('transaction.invoice_number')->label('Invoice')->placeholder('Belum ada')
                            ->url(fn(SalesOrder $record) => $record->transaction_id
                                ? route('filament.admin.resources.transactions.view', ['record' => $record->transaction_id])
                                : null)
                            ->color('primary'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Item Pesanan')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product_name')->label('Produk'),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('unit_name')->label('Satuan'),
                                TextEntry::make('unit_price')->label('Harga')->money('IDR'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                                TextEntry::make('qty_reserved')->label('Qty Reserved')
                                    ->color(fn($record) => $record->isFullyReserved() ? 'success' : 'warning')
                                    ->formatStateUsing(fn($record) => $record->qty_reserved . ' / ' . $record->quantity),
                                TextEntry::make('warehouse.name')->label('Gudang'),
                            ])
                            ->columns(7),
                    ]),

                Section::make('Pembatalan')
                    ->visible(fn(SalesOrder $record) => $record->isCancelled())
                    ->schema([
                        TextEntry::make('cancellation_reason')->label('Alasan')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Waktu Batal')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
