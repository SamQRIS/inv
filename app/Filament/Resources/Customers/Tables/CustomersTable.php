<?php

namespace App\Filament\Resources\Customers\Tables;


use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CreditService;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->formatStateUsing(fn($state) => $state === 'do' ? 'DO / Tempo' : 'End User'),
 
                TextColumn::make('phone')
                    ->label('No. HP')->searchable()->placeholder('—'),
 
                TextColumn::make('credit_limit')
                    ->label('Limit Kredit')->money('IDR')
                    ->visibleFrom('lg'),
 
                TextColumn::make('credit_used')
                    ->label('Terpakai')->money('IDR')
                    ->color(fn($record) => $record->credit_used > 0 ? 'warning' : 'gray')
                    ->visibleFrom('lg'),
 
                TextColumn::make('available_credit')
                    ->label('Sisa Kredit')
                    ->getStateUsing(fn($record) => $record->availableCredit())
                    ->money('IDR')
                    ->color(fn($record) => $record->availableCredit() <= 0 ? 'danger' : 'success')
                    ->visibleFrom('xl'),
 
                TextColumn::make('transactions_count')
                    ->label('Transaksi')->sortable(),
 
                IconColumn::make('is_active')
                    ->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(['do' => 'DO / Tempo', 'end_user' => 'End User']),
 
                TernaryFilter::make('is_active')->label('Status'),
 
                Filter::make('low_credit')
                    ->label('Kredit Hampir Habis (≥80%)')
                    ->query(fn($query) => $query
                        ->where('type', 'do')
                        ->where('credit_limit', '>', 0)
                        ->whereRaw('credit_used / credit_limit >= 0.8')
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
 
                ActionGroup::make([
                    Action::make('topup_credit')
                        ->label('Top Up Kredit')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('success')
                        ->modalHeading('Top Up Credit Limit')
                        ->visible(fn(Customer $record) => $record->type === 'do')
                        ->form(fn(Customer $record) => self::creditForm($record, 'topup'))
                        ->action(fn(Customer $record, array $data) => self::handleCredit($record, $data, 'topup')),
 
                    Action::make('deduct_credit')
                        ->label('Kurangi Kredit')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->color('danger')
                        ->modalHeading('Kurangi Credit Limit')
                        ->visible(fn(Customer $record) => $record->type === 'do' && $record->credit_limit > 0)
                        ->form(fn(Customer $record) => self::creditForm($record, 'deduct'))
                        ->action(fn(Customer $record, array $data) => self::handleCredit($record, $data, 'deduct')),
                ])->label('Kredit')->icon('heroicon-m-banknotes'),
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

    public static function creditForm(Customer $record, string $type): array
    {
        $isTopup = $type === 'topup';
 
        return [
            Placeholder::make('info_kredit')
                ->label('Informasi Kredit Saat Ini')
                ->content(
                    'Limit: Rp '    . number_format($record->credit_limit, 0, ',', '.') .
                    ' | Terpakai: Rp ' . number_format($record->credit_used, 0, ',', '.') .
                    ' | Sisa: Rp '  . number_format($record->availableCredit(), 0, ',', '.')
                ),
 
            TextInput::make('amount')
                ->label($isTopup ? 'Jumlah Top Up' : 'Jumlah Pengurangan')
                ->numeric()->prefix('Rp')->required()->minValue(1)
                ->maxValue($isTopup ? null : $record->credit_limit)
                ->helperText($isTopup
                    ? 'Credit limit akan bertambah sebesar jumlah ini.'
                    : 'Maks: Rp ' . number_format($record->credit_limit, 0, ',', '.')
                ),
 
            Textarea::make('notes')
                ->label('Keterangan / Alasan')
                ->placeholder('Contoh: penambahan limit bulanan, koreksi, dll')
                ->required()
                ->rows(2),
        ];
    }
 
    public static function handleCredit(Customer $record, array $data, string $type): void
    {
        $service = app(CreditService::class);
        $amount  = (float) $data['amount'];
        $notes   = $data['notes'];
 
        if ($type === 'topup') {
            $service->topup($record, $amount, $notes);
            Notification::make()
                ->success()
                ->title('Top Up Berhasil')
                ->body('+ Rp ' . number_format($amount, 0, ',', '.') . ' ditambahkan ke limit kredit ' . $record->name)
                ->send();
        } else {
            $service->deduct($record, $amount, $notes);
            Notification::make()
                ->warning()
                ->title('Pengurangan Kredit Berhasil')
                ->body('- Rp ' . number_format($amount, 0, ',', '.') . ' dikurangi dari limit kredit ' . $record->name)
                ->send();
        }
    }
}
