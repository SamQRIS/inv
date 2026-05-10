<?php

namespace App\Filament\Resources\SalesReports\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Customer;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->modifyQueryUsing(
            //     fn(Builder $q) => $q
            //         ->with(['customer', 'payments.paymentMethod'])
            //         ->whereBetween('transaction_date', [
            //             now()->startOfMonth()->toDateString(),
            //             now()->toDateString(),
            //         ])
            // )
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('discount_amount')
                    ->label('Diskon')
                    ->money('IDR')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('amount_paid')
                    ->label('Dibayar')
                    ->money('IDR')
                    ->color('success'),

                TextColumn::make('amount_remaining')
                    ->label('Sisa')
                    ->money('IDR')
                    ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success'),
                TextColumn::make('payment_status')
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
            ])
            ->filters([
                // Filter periode — WAJIB ada di laporan
                Filter::make('period')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->default(now()->startOfMonth())
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if (!blank($data['from'] ?? null)) {
                            $query->whereDate('transaction_date', '>=', $data['from']);
                        }
                        if (!blank($data['until'] ?? null)) {
                            $query->whereDate('transaction_date', '<=', $data['until']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if (!blank($data['from'] ?? null))  $i[] = 'Dari: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        if (!blank($data['until'] ?? null)) $i[] = 'Sampai: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        return $i;
                    }),

                SelectFilter::make('payment_status')
                    ->label('Status Bayar')
                    ->options(['unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas']),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->options(Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([
                // Export Excel
                Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()
                            ->with(['customer', 'items.product', 'payments.paymentMethod'])
                            ->get();
                        return self::exportSalesReport($records);
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->url(fn(Transaction $r) => TransactionResource::getUrl('view', ['record' => $r])),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->striped()
            // Summary total di bawah tabel
            ->paginated([25, 50, 100, 'all'])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function exportSalesReport(\Illuminate\Database\Eloquent\Collection $records): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'laporan-penjualan-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            // Header
            fputcsv($handle, ['No. Invoice', 'Tanggal', 'Customer', 'Subtotal', 'Diskon', 'Grand Total', 'Dibayar', 'Sisa', 'Status']);

            $totalSubtotal    = 0;
            $totalDiskon      = 0;
            $totalGrandTotal  = 0;
            $totalPaid        = 0;
            $totalRemaining   = 0;

            foreach ($records as $trx) {
                fputcsv($handle, [
                    $trx->invoice_number,
                    $trx->transaction_date->format('d/m/Y'),
                    $trx->customer?->name ?? '—',
                    $trx->subtotal,
                    $trx->discount_amount,
                    $trx->grand_total,
                    $trx->amount_paid,
                    $trx->amount_remaining,
                    match ($trx->payment_status) {
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid' => 'Lunas',
                        default => $trx->payment_status,
                    },
                ]);

                $totalSubtotal   += $trx->subtotal;
                $totalDiskon     += $trx->discount_amount;
                $totalGrandTotal += $trx->grand_total;
                $totalPaid       += $trx->amount_paid;
                $totalRemaining  += $trx->amount_remaining;
            }

            // Baris total
            fputcsv($handle, []);
            fputcsv($handle, ['TOTAL', '', '', $totalSubtotal, $totalDiskon, $totalGrandTotal, $totalPaid, $totalRemaining, '']);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
