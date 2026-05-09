<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\PaymentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;


class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->with(['transaction.customer', 'paymentMethod'])
                    ->latest('payment_date')
            )
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tgl Bayar')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('transaction.invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->copyable()
                    ->url(fn($record) => TransactionResource::getUrl('view', ['record' => $record->transaction_id]))
                    ->color('primary'),

                TextColumn::make('transaction.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('paymentMethod.name')
                    ->label('Metode')
                    ->badge()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('reference_number')
                    ->label('Referensi')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('transaction.payment_status')
                    ->label('Status Transaksi')
                    ->badge()
                    ->colors([
                        'danger'  => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'unpaid'  => 'Belum Lunas',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                        default   => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('payment_date')
                    ->label('Tanggal Pembayaran')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y')->default(now()->startOfMonth()),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y')->default(now()),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if (!blank($data['from'] ?? null)) {
                            $query->whereDate('payment_date', '>=', $data['from']);
                        }
                        if (!blank($data['until'] ?? null)) {
                            $query->whereDate('payment_date', '<=', $data['until']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'])  $indicators[] = 'Dari: ' . Carbon::parse($data['from'])->format('d/m/Y');
                        if ($data['until']) $indicators[] = 'Sampai: ' . Carbon::parse($data['until'])->format('d/m/Y');
                        return $indicators;
                    }),

                SelectFilter::make('payment_method_id')
                    ->label('Metode Pembayaran')
                    ->options(PaymentMethod::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('transaction_status')
                    ->label('Status Transaksi')
                    ->options([
                        'unpaid'  => 'Belum Lunas',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) return $query;
                        return $query->whereHas(
                            'transaction',
                            fn(Builder $q) => $q->where('payment_status', $data['value'])
                        );
                    }),

                SelectFilter::make('customer')
                    ->label('Customer')
                    ->options(
                        \App\Models\Customer::where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) return $query;
                        return $query->whereHas(
                            'transaction',
                            fn(Builder $q) => $q->where('customer_id', $data['value'])
                        );
                    }),

                TernaryFilter::make('is_installment')
                    ->label('Cicilan')
                    ->modifyQueryUsing(function (Builder $query, ?bool $value) {
                        if (is_null($value)) return $query;
                        return $query->whereHas(
                            'paymentMethod',
                            fn(Builder $q) => $q->where('is_installment', $value)
                        );
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)

            // ── HEADER ACTIONS (export) ──────────────────────────────
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        return self::exportToExcel($query);
                    }),

                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        return self::exportToCsv($query);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->after(function (Payment $record) {
                        // Recalculate payment status setelah edit
                        app(PaymentService::class)->recalculateStatus($record->transaction);
                    }),
                DeleteAction::make()
                    ->before(function (Payment $record) {
                        // Simpan transaction sebelum payment dihapus
                        $record->transaction_id_backup = $record->transaction_id;
                    })
                    ->after(function (Payment $record) {
                        // Recalculate setelah hapus
                        $transaction = Transaction::find($record->transaction_id);
                        if ($transaction) {
                            app(PaymentService::class)->recalculateStatus($transaction);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalDescription('Menghapus pembayaran ini akan mengupdate status transaksi secara otomatis. Lanjutkan?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->after(function (Collection $records) {
                            // Recalculate semua transaksi terdampak
                            $transactionIds = $records->pluck('transaction_id')->unique();
                            Transaction::whereIn('id', $transactionIds)->each(function ($trx) {
                                app(PaymentService::class)->recalculateStatus($trx);
                            });
                        }),
                ]),
            ])
            ->defaultSort('payment_date', 'desc')
            ->striped()
            ->poll('30s');
    }

    // =========================================================
    // EXPORT HELPERS
    // =========================================================

    private static function exportToExcel(Builder $query): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $payments = $query->with(['transaction.customer', 'paymentMethod'])->get();

        $filename = 'pembayaran-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');

            // BOM untuk Excel agar bisa baca UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($handle, [
                'Tgl Bayar',
                'No. Invoice',
                'Customer',
                'Metode',
                'Jumlah',
                'No. Referensi',
                'Status Transaksi',
                'Catatan',
                'Dicatat Pada',
            ]);

            // Rows
            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->payment_date?->format('d/m/Y'),
                    $payment->transaction?->invoice_number,
                    $payment->transaction?->customer?->name ?? '—',
                    $payment->paymentMethod?->name,
                    $payment->amount,
                    $payment->reference_number ?? '—',
                    match ($payment->transaction?->payment_status) {
                        'unpaid'  => 'Belum Lunas',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                        default   => '—',
                    },
                    $payment->notes ?? '—',
                    $payment->created_at?->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private static function exportToCsv(Builder $query): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Sama dengan Excel tapi tanpa BOM
        $payments = $query->with(['transaction.customer', 'paymentMethod'])->get();
        $filename = 'pembayaran-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Tgl Bayar', 'No. Invoice', 'Customer', 'Metode', 'Jumlah', 'No. Referensi', 'Status', 'Catatan']);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->payment_date?->format('d/m/Y'),
                    $payment->transaction?->invoice_number,
                    $payment->transaction?->customer?->name ?? '—',
                    $payment->paymentMethod?->name,
                    $payment->amount,
                    $payment->reference_number ?? '—',
                    $payment->transaction?->payment_status ?? '—',
                    $payment->notes ?? '—',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
