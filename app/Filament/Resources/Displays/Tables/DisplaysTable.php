<?php

namespace App\Filament\Resources\Displays\Tables;

use App\Models\TransactionItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DisplaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn($query) =>
                $query->with(['transaction.customer', 'product', 'product.unit'])
                    ->where('is_display', true)
            )
            ->columns([
                TextColumn::make('transaction.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->url(
                        fn(TransactionItem $record) =>
                        route('filament.admin.resources.transactions.view', ['record' => $record->transaction_id])
                    )
                    ->color('primary'),

                TextColumn::make('transaction.transaction_date')
                    ->label('Tgl Transaksi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('transaction.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('End User'),

                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('display_location')
                    ->label('Lokasi Display')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('quantity')
                    ->label('Qty Dikirim')
                    ->alignCenter(),

                TextColumn::make('qty_display_sold')
                    ->label('Terjual')
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('qty_display_returned')
                    ->label('Retur')
                    ->alignCenter()
                    ->color('info'),

                TextColumn::make('qty_remaining_display')
                    ->label('Sisa di Lokasi')
                    ->getStateUsing(fn(TransactionItem $record) => $record->qtyDisplayRemaining())
                    ->alignCenter()
                    ->color(
                        fn(TransactionItem $record) =>
                        $record->qtyDisplayRemaining() > 0 ? 'warning' : 'gray'
                    )
                    ->weight('bold'),

                BadgeColumn::make('display_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'sold',
                        'info'    => 'returned',
                    ])
                    ->formatStateUsing(fn(TransactionItem $record) => $record->displayStatusLabel()),

                TextColumn::make('display_confirmed_at')
                    ->label('Tgl Konfirmasi')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('display_status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Di Lokasi Display',
                        'sold'     => 'Terjual',
                        'returned' => 'Diretur',
                    ]),

                Filter::make('display_location')
                    ->label('Lokasi')
                    ->form([
                        TextInput::make('location')->label('Nama Lokasi')->placeholder('Cari lokasi...'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['location']
                            ? $query->where('display_location', 'like', '%' . $data['location'] . '%')
                            : $query
                    ),
            ])
            ->recordActions([
                // ── KONFIRMASI TERJUAL ────────────────────────────────
                Action::make('mark_sold')
                    ->label('Terjual')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(TransactionItem $record) =>
                        $record->display_status === 'pending' && $record->qtyDisplayRemaining() > 0
                    )
                    ->schema(fn(TransactionItem $record) => [
                        TextInput::make('qty_sold')
                            ->label('Qty Terjual')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue($record->qtyDisplayRemaining())
                            ->default($record->qtyDisplayRemaining())
                            ->required()
                            ->helperText('Sisa di lokasi: ' . $record->qtyDisplayRemaining() . ' ' . $record->product?->unit?->symbol),

                        DatePicker::make('confirmed_at')
                            ->label('Tanggal Konfirmasi')
                            ->default(today())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->modalHeading('Konfirmasi Terjual')
                    ->modalSubmitActionLabel('Simpan')
                    ->action(function (TransactionItem $record, array $data) {
                        $newSold = $record->qty_display_sold + (int) $data['qty_sold'];
                        $allSettled = ($newSold + $record->qty_display_returned) >= $record->quantity;

                        $record->update([
                            'qty_display_sold'    => $newSold,
                            'display_status'      => $allSettled ? 'sold' : 'pending',
                            'display_confirmed_at' => $data['confirmed_at'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Dikonfirmasi Terjual')
                            ->body($data['qty_sold'] . ' unit ' . $record->product_name . ' terjual di ' . $record->display_location)
                            ->send();
                    }),

                // ── KONFIRMASI RETUR ──────────────────────────────────
                Action::make('mark_returned')
                    ->label('Retur')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->visible(
                        fn(TransactionItem $record) =>
                        $record->display_status === 'pending' && $record->qtyDisplayRemaining() > 0
                    )
                    ->form(fn(TransactionItem $record) => [
                        TextInput::make('qty_returned')
                            ->label('Qty Diretur')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue($record->qtyDisplayRemaining())
                            ->default($record->qtyDisplayRemaining())
                            ->required()
                            ->helperText('Sisa di lokasi: ' . $record->qtyDisplayRemaining() . ' ' . $record->product?->unit?->symbol),

                        DatePicker::make('confirmed_at')
                            ->label('Tanggal Retur')
                            ->default(today())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->modalHeading('Konfirmasi Retur')
                    ->modalSubmitActionLabel('Simpan Retur')
                    ->action(function (TransactionItem $record, array $data) {
                        $newReturned  = $record->qty_display_returned + (int) $data['qty_returned'];
                        $allSettled   = ($record->qty_display_sold + $newReturned) >= $record->quantity;

                        $record->update([
                            'qty_display_returned' => $newReturned,
                            'display_status'       => $allSettled ? 'returned' : 'pending',
                            'display_confirmed_at' => $data['confirmed_at'],
                        ]);

                        // Kembalikan stok ke gudang
                        $product = $record->product;
                        if ($product) {
                            $warehouse = $record->transaction->items()
                                ->where('product_id', $product->id)
                                ->first()
                                ?->warehouse_id;

                            if ($warehouse) {
                                \App\Models\ProductStock::where('product_id', $product->id)
                                    ->where('warehouse_id', $warehouse)
                                    ->increment('quantity', (int) $data['qty_returned']);

                                $product->syncTotalStock();
                            }
                        }

                        Notification::make()
                            ->info()
                            ->title('Retur Dikonfirmasi')
                            ->body($data['qty_returned'] . ' unit ' . $record->product_name . ' diretur dari ' . $record->display_location . '. Stok sudah dikembalikan ke gudang.')
                            ->send();
                    }),
            ])
            ->defaultSort('transaction.transaction_date', 'desc')
            ->emptyStateHeading('Belum ada item display')
            ->emptyStateDescription('Item display muncul di sini saat membuat transaksi dengan toggle "Display" diaktifkan.')
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
