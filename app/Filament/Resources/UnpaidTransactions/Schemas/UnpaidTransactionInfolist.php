<?php

namespace App\Filament\Resources\UnpaidTransactions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnpaidTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Transaksi')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('No. Invoice')->copyable()->weight('bold'),
                        TextEntry::make('transaction_date')
                            ->label('Tgl Transaksi')->date('d/m/Y'),
                        TextEntry::make('customer.name')
                            ->label('Customer')->placeholder('—'),
                        TextEntry::make('delivery_date_display')
                            ->label('Jadwal Kirim')->placeholder('—'),

                        TextEntry::make('subtotal')
                            ->label('Subtotal')->money('IDR'),
                        TextEntry::make('discount_amount')
                            ->label('Diskon')->money('IDR')
                            ->color('danger')
                            ->visible(fn($record) => $record->discount_amount > 0),
                        TextEntry::make('grand_total')
                            ->label('Grand Total')->money('IDR')->weight('bold'),
                        TextEntry::make('payment_status')
                            ->label('Status')->badge()
                            ->color(fn($state) => match ($state) {
                                'unpaid'  => 'danger',
                                'partial' => 'warning',
                                default   => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'unpaid'  => 'Belum Bayar',
                                'partial' => 'Sebagian',
                                default   => $state,
                            }),
                    ]),

                Section::make('Status Pembayaran')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount_paid')
                            ->label('Sudah Dibayar')->money('IDR')->color('success'),
                        TextEntry::make('amount_remaining')
                            ->label('Sisa Tagihan')->money('IDR')->color('danger')->weight('bold')->size('xl'),
                        TextEntry::make('due_days')
                            ->label('Umur Tagihan')
                            ->getStateUsing(fn($record) => $record->transaction_date->diffInDays(today()) . ' hari')
                            ->color(fn($record) => $record->transaction_date->diffInDays(today()) > 30 ? 'danger' : 'warning'),
                    ]),

                Section::make('Riwayat Pembayaran')
                    ->visible(fn($record) => $record->payments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                TextEntry::make('payment_date')->label('Tanggal')->date('d/m/Y'),
                                TextEntry::make('paymentMethod.name')->label('Metode')->badge(),
                                TextEntry::make('amount')->label('Jumlah')->money('IDR')->weight('bold'),
                                TextEntry::make('reference_number')->label('Referensi')->placeholder('—'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
