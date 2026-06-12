<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) =>
                $query->with(['customer', 'items'])->latest('transaction_date')
            )
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. Invoice')->searchable()->copyable()->weight('bold'),

                TextColumn::make('transaction_date')
                    ->label('Tanggal')->date('d/m/Y')->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->default('Walk-in / End User')
                    ->searchable(),

                TextColumn::make('grand_total')
                    ->label('Grand Total')->money('IDR')->sortable(),

                TextColumn::make('amount_paid')
                    ->label('Dibayar')->money('IDR')
                    ->color(fn($record) => $record->amount_paid > 0 ? 'success' : 'gray'),

                TextColumn::make('amount_remaining')
                    ->label('Sisa')->money('IDR')
                    ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success'),

                BadgeColumn::make('payment_status')
                    ->label('Bayar')
                    ->colors([
                        'danger'  => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'unpaid'  => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                        default   => $state,
                    }),

                BadgeColumn::make('delivery_status')
                    ->label('Kirim')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'processing',
                        'success' => 'delivered',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending'    => 'Menunggu',
                        'processing' => 'Diproses',
                        'delivered'  => 'Terkirim',
                        'cancelled'  => 'Batal',
                        default      => $state,
                    }),

                // Khusus DO: tampilkan asal SO jika ada
                TextColumn::make('notes')
                    ->label('Dari SO')
                    ->formatStateUsing(
                        fn($state) => str($state)->contains('Dari SO')
                            ? str($state)->match('/Dari SO (SO-\d+-\d+)/')->value()
                            : null
                    )
                    ->placeholder('—')
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Status Bayar')
                    ->options([
                        'unpaid'  => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                    ]),

                SelectFilter::make('delivery_status')
                    ->label('Status Kirim')
                    ->options([
                        'pending'    => 'Menunggu',
                        'processing' => 'Diproses',
                        'delivered'  => 'Terkirim',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
