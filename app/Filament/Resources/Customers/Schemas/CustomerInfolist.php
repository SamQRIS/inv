<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Services\DiscountService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $discountService = app(DiscountService::class);

        return $schema->schema([

            // ── Info dasar ───────────────────────────────────────────
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nama')->weight('bold')->columnSpan(2),
                    TextEntry::make('type')
                        ->label('Tipe')->badge()
                        ->color(fn($state) => $state === 'do' ? 'primary' : 'gray')
                        ->formatStateUsing(fn($state) => $state === 'do' ? 'DO' : 'End User'),
                    TextEntry::make('phone')->label('No. HP')->placeholder('—'),
                    TextEntry::make('address')->label('Alamat')->placeholder('—')->columnSpanFull(),
                    IconEntry::make('is_active')->label('Status')->boolean(),
                ]),

            // ── Panel Deposit (khusus DO) ────────────────────────────
            Section::make('Saldo Deposit')
                ->visible(fn($record) => $record->type === 'do')
                ->columns(3)
                ->schema([
                    TextEntry::make('deposit_balance')
                        ->label('Saldo Deposit')
                        ->money('IDR')
                        ->color(fn($record) => match (true) {
                            $record->deposit_balance <= 0     => 'danger',
                            $record->deposit_balance < 500000 => 'warning',
                            default                           => 'success',
                        })
                        ->weight('bold')
                        ->size('xl')
                        ->helperText(fn($record) => match (true) {
                            $record->deposit_balance <= 0     => '⚠ Deposit habis — customer tidak bisa order.',
                            $record->deposit_balance < 500000 => '⚠ Saldo menipis.',
                            default                           => 'Deposit aktif.',
                        }),

                    TextEntry::make('default_discount_display')
                        ->label('Diskon Default')
                        ->getStateUsing(fn($record) => $discountService->formatSummary($record->default_discount ?? []))
                        ->placeholder('Tidak ada diskon default')
                        ->columnSpan(2),
                ]),

            // ── Riwayat Deposit ─────────────────────────────────────
            Section::make('Riwayat Deposit')
                ->visible(fn($record) => $record->type === 'do')
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('creditLogs')
                        ->label('')
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Waktu')->dateTime('d/m/Y H:i'),

                            TextEntry::make('type')
                                ->label('Tipe')->badge()
                                ->color(fn($record) => $record->typeColor())
                                ->formatStateUsing(fn($record) => $record->typeLabel()),

                            TextEntry::make('amount')
                                ->label('Jumlah')
                                ->formatStateUsing(
                                    fn($record) => ($record->isDebit() ? '+ ' : '- ') .
                                        'Rp ' . number_format($record->amount, 0, ',', '.')
                                )
                                ->color(fn($record) => $record->isDebit() ? 'success' : 'danger'),

                            TextEntry::make('credit_before')
                                ->label('Saldo Sebelum')
                                ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),

                            TextEntry::make('credit_after')
                                ->label('Saldo Sesudah')
                                ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                ->weight('bold'),

                            TextEntry::make('paymentMethod.name')
                                ->label('Metode Bayar')
                                ->placeholder('—'),

                            TextEntry::make('reference_number')
                                ->label('No. Referensi')
                                ->placeholder('—'),

                            TextEntry::make('user.name')
                                ->label('Oleh'),

                            TextEntry::make('notes')
                                ->label('Keterangan')->placeholder('—')->columnSpanFull(),
                        ])
                        ->columns(4),
                ]),

            // ── Riwayat Transaksi ────────────────────────────────────
            Section::make('Riwayat Transaksi')
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('transactions')
                        ->label('')
                        ->schema([
                            TextEntry::make('invoice_number')
                                ->label('Invoice')->copyable(),
                            TextEntry::make('transaction_date')
                                ->label('Tanggal')->date('d/m/Y'),
                            TextEntry::make('grand_total')
                                ->label('Total')->money('IDR'),
                            TextEntry::make('amount_remaining')
                                ->label('Sisa')->money('IDR')
                                ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success'),
                            TextEntry::make('payment_status')
                                ->label('Status')->badge()
                                ->color(fn($state) => match ($state) {
                                    'unpaid'  => 'danger',
                                    'partial' => 'warning',
                                    'paid'    => 'success',
                                    default   => 'gray',
                                })
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'unpaid'  => 'Belum Bayar',
                                    'partial' => 'Sebagian',
                                    'paid'    => 'Lunas',
                                    default   => $state,
                                }),
                        ])
                        ->columns(5),
                ]),
        ]);
    }
}
