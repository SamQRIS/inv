<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Transaction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Invoice')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('No. Invoice')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('transaction_date')
                            ->label('Tanggal')
                            ->date('d/m/Y'),

                        TextEntry::make('payment_status')
                            ->label('Status Bayar')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'unpaid'  => 'danger',
                                'partial' => 'warning',
                                'paid'    => 'success',
                                default   => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'unpaid'  => 'Belum Bayar',
                                'partial' => 'Sebagian',
                                'paid'    => 'Lunas',
                                default   => $state,
                            }),

                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->default('Walk-in / End User'),

                        TextEntry::make('delivery_status')
                            ->label('Status Kirim')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pending'    => 'gray',
                                'processing' => 'warning',
                                'delivered'  => 'success',
                                'cancelled'  => 'danger',
                                default      => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'pending'    => 'Menunggu',
                                'processing' => 'Diproses',
                                'delivered'  => 'Terkirim',
                                'cancelled'  => 'Batal',
                                default      => $state,
                            }),

                        TextEntry::make('delivery_note')
                            ->label('No. Surat Jalan')
                            ->placeholder('—'),

                        // Info khusus DO
                        TextEntry::make('customer.deposit_balance')
                            ->label('Saldo Deposit Customer')
                            ->money('IDR')
                            ->visible(fn (?Transaction $record) =>
                                $record?->customer?->type === 'do'
                            )
                            ->color(fn (?Transaction $record) =>
                                ($record?->customer?->deposit_balance ?? 0) > 0
                                    ? 'success'
                                    : 'danger'
                            ),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Ringkasan Pembayaran')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('IDR'),

                        TextEntry::make('discount_amount')
                            ->label('Diskon')
                            ->money('IDR'),

                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('IDR')
                            ->weight('bold'),

                        TextEntry::make('amount_paid')
                            ->label('Sudah Dibayar')
                            ->money('IDR')
                            ->color('success'),

                        TextEntry::make('amount_remaining')
                            ->label('Sisa Tagihan')
                            ->money('IDR')
                            ->color(fn (Transaction $record) =>
                                $record->amount_remaining > 0
                                    ? 'danger'
                                    : 'success'
                            ),
                    ]),

                Section::make('Item')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product_name')->label('Produk'),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('unit_name')->label('Satuan'),
                                TextEntry::make('unit_price')->label('Harga Satuan')->money('IDR'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Riwayat Pembayaran')
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                TextEntry::make('payment_date')
                                    ->label('Tanggal')
                                    ->date('d/m/Y'),

                                TextEntry::make('paymentMethod.name')
                                    ->label('Metode'),

                                TextEntry::make('amount')
                                    ->label('Jumlah')
                                    ->money('IDR'),

                                TextEntry::make('reference_number')
                                    ->label('Referensi')
                                    ->placeholder('—'),

                                TextEntry::make('notes')
                                    ->label('Keterangan')
                                    ->placeholder('—'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}