<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\DepositService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->withCount('transactions'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')->searchable()->sortable(),

                BadgeColumn::make('type')
                    ->label('Tipe')
                    ->colors(['primary' => 'do', 'gray' => 'end_user'])
                    ->formatStateUsing(fn($state) => $state === 'do' ? 'DO' : 'End User'),

                TextColumn::make('phone')
                    ->label('No. HP')->searchable()->placeholder('—'),

                TextColumn::make('deposit_balance')
                    ->label('Saldo Deposit')
                    ->money('IDR')
                    ->color(fn($record) => match (true) {
                        $record->type !== 'do'            => 'gray',
                        $record->deposit_balance <= 0     => 'danger',
                        $record->deposit_balance < 500000 => 'warning',
                        default                           => 'success',
                    })
                    ->visibleFrom('lg'),

                TextColumn::make('transactions_count')
                    ->label('Transaksi')->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(['do' => 'DO', 'end_user' => 'End User']),

                TernaryFilter::make('is_active')->label('Status'),

                Filter::make('low_deposit')
                    ->label('Deposit Menipis / Habis')
                    ->query(
                        fn($query) => $query
                            ->where('type', 'do')
                            ->where('deposit_balance', '<', 500000)
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                // ── TOP UP DEPOSIT ────────────────────────────────────
                Action::make('topup_deposit')
                    ->label('Top Up Deposit')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn(Customer $record) => $record->type === 'do')
                    ->form(fn(Customer $record) => [
                        Placeholder::make('info_deposit')
                            ->label('Saldo Deposit Saat Ini')
                            ->content(
                                $record->deposit_balance > 0
                                    ? 'Rp ' . number_format($record->deposit_balance, 0, ',', '.')
                                    : '⚠ Rp 0 — deposit habis'
                            ),

                        TextInput::make('amount')
                            ->label('Jumlah Top Up')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->required(),

                        Select::make('payment_method_id')
                            ->label('Metode Pembayaran')
                            ->options(PaymentMethod::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('reference_number')
                            ->label('No. Referensi / Transfer')
                            ->placeholder('Contoh: TRF-20260516-001')
                            ->nullable(),

                        Textarea::make('notes')
                            ->label('Keterangan')
                            ->rows(2)
                            ->placeholder('Opsional — contoh: Transfer BCA a/n Budi Santoso')
                            ->nullable(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        app(DepositService::class)->topup(
                            customer: $record,
                            amount: (float) $data['amount'],
                            paymentMethodId: (int) $data['payment_method_id'],
                            referenceNumber: $data['reference_number'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                        // Notifikasi sudah dikirim dari dalam DepositService::topup()
                    })
                    ->modalHeading(fn(Customer $record) => 'Top Up Deposit — ' . $record->name)
                    ->modalSubmitActionLabel('Simpan Top Up')
                    ->modalWidth('lg'),
            ])
            ->defaultSort('name')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
