<?php

namespace App\Filament\Resources\Trashes\Tables;

use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrashesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->modifyQueryUsing(fn(Builder $q) => $q->with(['customer', 'user']))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('transaction_date')
                    ->label('Tgl Transaksi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('—'),

                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('IDR'),

                TextColumn::make('user.name')
                    ->label('Dibuat oleh'),

                TextColumn::make('deleted_at')
                    ->label('Dihapus pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('danger'),
            ])
            ->filters([
                Filter::make('deleted_at')
                    ->label('Tgl Dihapus')
                    ->form([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['from']))  $query->whereDate('deleted_at', '>=', $data['from']);
                        if (!empty($data['until'])) $query->whereDate('deleted_at', '<=', $data['until']);
                    }),
            ])
            ->recordActions([
                // Restore
                Action::make('restore')
                    ->label('Pulihkan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pulihkan Transaksi?')
                    ->modalDescription('Transaksi ini akan dikembalikan dan bisa diakses kembali.')
                    ->action(function (Transaction $record) {
                        $record->restore();

                        // Log restore
                        // \App\Services\ActivityLogger::restored(
                        //     $record,
                        //     $record->invoice_number,
                        // );

                        Notification::make()
                            ->success()
                            ->title("Transaksi {$record->invoice_number} berhasil dipulihkan.")
                            ->send();
                    }),

                // Force delete (hapus permanen)
                Action::make('force_delete')
                    ->label('Hapus Permanen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Permanen?')
                    ->modalDescription('Data akan dihapus PERMANEN dan tidak bisa dipulihkan lagi.')
                    ->action(function (Transaction $record) {
                        $invoice = $record->invoice_number;
                        $record->forceDelete();

                        Notification::make()
                            ->warning()
                            ->title("Transaksi {$invoice} dihapus permanen.")
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make()
                        ->after(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $r) {
                                \App\Services\ActivityLogger::restored($r, $r->invoice_number);
                            }
                            Notification::make()->success()->title(count($records) . ' transaksi berhasil dipulihkan.')->send();
                        }),

                    ForceDeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('deleted_at', 'desc')
            ->emptyStateHeading('Tempat sampah kosong')
            ->emptyStateDescription('Tidak ada data yang dihapus.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
