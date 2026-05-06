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

            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nama')->weight('bold')->columnSpan(2),
                    TextEntry::make('type')
                        ->label('Tipe')->badge()
                        ->color(fn($state) => $state === 'do' ? 'primary' : 'gray')
                        ->formatStateUsing(fn($state) => $state === 'do' ? 'DO / Tempo' : 'End User'),
                    TextEntry::make('phone')->label('No. HP')->placeholder('—'),
                    TextEntry::make('address')->label('Alamat')->placeholder('—')->columnSpanFull(),
                    IconEntry::make('is_active')->label('Status')->boolean(),
                ]),

            // ── Panel Kredit ─────────────────────────────────────────
            Section::make('Informasi Kredit')
                ->visible(fn($record) => $record->type === 'do')
                ->columns(4)
                ->schema([
                    TextEntry::make('credit_limit')
                        ->label('Limit Kredit')->money('IDR')
                        ->color('primary')->weight('bold'),

                    TextEntry::make('credit_used')
                        ->label('Terpakai')->money('IDR')
                        ->color(fn($record) => $record->credit_used > 0 ? 'warning' : 'gray'),

                    TextEntry::make('available_credit')
                        ->label('Sisa Kredit')
                        ->getStateUsing(fn($record) => $record->availableCredit())
                        ->money('IDR')
                        ->color(fn($record) => $record->availableCredit() <= 0 ? 'danger' : 'success')
                        ->weight('bold'),

                    TextEntry::make('credit_usage_percent')
                        ->label('Penggunaan')
                        ->getStateUsing(fn($record) => $record->creditUsagePercent() . '%')
                        ->color(fn($record) => match (true) {
                            $record->creditUsagePercent() >= 90 => 'danger',
                            $record->creditUsagePercent() >= 60 => 'warning',
                            default                             => 'success',
                        }),

                    TextEntry::make('default_discount_display')
                        ->label('Diskon Default')
                        ->getStateUsing(fn($record) => $discountService->formatSummary($record->default_discount ?? []))
                        ->placeholder('Tidak ada diskon default')
                        ->columnSpan(4),
                ]),

            // ── Riwayat Kredit ───────────────────────────────────────
            Section::make('Riwayat Kredit')
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
                                    fn($record) => ($record->isCredit() ? '+ ' : '- ') .
                                        'Rp ' . number_format($record->amount, 0, ',', '.')
                                )
                                ->color(fn($record) => $record->isCredit() ? 'success' : 'danger'),

                            TextEntry::make('credit_before')
                                ->label('Sebelum')->money('IDR'),

                            TextEntry::make('credit_after')
                                ->label('Sesudah')->money('IDR')->weight('bold'),

                            TextEntry::make('user.name')
                                ->label('Oleh'),

                            TextEntry::make('notes')
                                ->label('Keterangan')->placeholder('—')->columnSpanFull(),
                        ])
                        ->columns(3),
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
