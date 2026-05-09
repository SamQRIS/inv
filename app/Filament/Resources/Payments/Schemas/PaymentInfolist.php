<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaksi')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('transaction.invoice_number')
                            ->label('No. Invoice')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('transaction.customer.name')
                            ->label('Customer')
                            ->placeholder('—'),

                        TextEntry::make('transaction.transaction_date')
                            ->label('Tgl Transaksi')
                            ->date('d/m/Y'),

                        TextEntry::make('transaction.grand_total')
                            ->label('Grand Total')
                            ->money('IDR'),

                        TextEntry::make('transaction.amount_paid')
                            ->label('Total Dibayar')
                            ->money('IDR')
                            ->color('success'),

                        TextEntry::make('transaction.amount_remaining')
                            ->label('Sisa Tagihan')
                            ->money('IDR')
                            ->color(fn($record) => $record->transaction->amount_remaining > 0 ? 'danger' : 'success'),
                    ]),

                Section::make('Detail Pembayaran')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('paymentMethod.name')
                            ->label('Metode')
                            ->badge(),

                        TextEntry::make('amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->weight('bold')
                            ->size('xl')
                            ->color('success'),

                        TextEntry::make('payment_date')
                            ->label('Tanggal Bayar')
                            ->date('d/m/Y'),

                        TextEntry::make('reference_number')
                            ->label('No. Referensi')
                            ->placeholder('—')
                            ->copyable(),

                        TextEntry::make('user.name')
                            ->label('Dicatat oleh')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Dicatat pada')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                // Detail cicilan jika ada
                Section::make('Detail Cicilan')
                    ->visible(fn($record) => !empty($record->installment_detail))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('installment_detail.provider')
                            ->label('Provider'),
                        TextEntry::make('installment_detail.tenor')
                            ->label('Tenor')
                            ->suffix(' bulan'),
                        TextEntry::make('installment_detail.contract_number')
                            ->label('No. Kontrak'),
                        TextEntry::make('installment_detail.monthly_amount')
                            ->label('Cicilan/Bulan')
                            ->money('IDR'),
                    ]),
            ]);
    }
}
