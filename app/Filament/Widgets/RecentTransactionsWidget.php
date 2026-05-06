<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentTransactionsWidget extends TableWidget
{
    protected static ?int $sort    = 3;
    // protected int | string $columnSpan = 'full';

    
 
    public function table(Table $table): Table
    {
        return $table
            ->heading('Transaksi Terbaru')
            ->query(
                Transaction::with(['customer', 'payments'])
                    ->latest('transaction_date')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable(),
 
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d/m/Y'),
 
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
 
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR'),
 
                BadgeColumn::make('payment_status')
                    ->label('Status Bayar')
                    ->colors([
                        'danger'  => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'unpaid'  => 'Belum',
                        'partial' => 'Sebagian',
                        'paid'    => 'Lunas',
                        default   => $state,
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-m-eye')
                    ->url(fn(Transaction $record) => TransactionResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
