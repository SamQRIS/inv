<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['customer', 'items', 'transaction']))
            ->columns([
                TextColumn::make('so_number')
                    ->label('No. SO')->searchable()->sortable()->copyable()->weight('bold'),

                TextColumn::make('order_date')
                    ->label('Tgl Order')->date('d/m/Y')->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')->searchable(),

                TextColumn::make('items_summary')
                    ->label('Item')
                    ->getStateUsing(
                        fn(SalesOrder $record) =>
                        $record->items->count() . ' item'
                    ),

                TextColumn::make('grand_total')
                    ->label('Est. Total')
                    ->formatStateUsing(
                        fn($state) =>
                        $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '—'
                    ),

                TextColumn::make('estimated_delivery_date')
                    ->label('Est. Kirim')->date('d/m/Y')->placeholder('Belum ditentukan'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'draft',
                        'info'    => 'confirmed',
                        'success' => 'converted',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn(SalesOrder $record) => $record->statusLabel()),

                TextColumn::make('transaction.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
                    ->url(
                        fn(SalesOrder $record) => $record->transaction_id
                            ? \App\Filament\Resources\Transactions\TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                            : null
                    )
                    ->color('primary'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft'     => 'Draft',
                        'confirmed' => 'Dikonfirmasi',
                        'converted' => 'Sudah Jadi Transaksi',
                        'cancelled' => 'Dibatalkan',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),

                ActionGroup::make([
                    // ── KONFIRMASI ────────────────────────────────────
                    Action::make('confirm')
                        ->label('Konfirmasi Order')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->visible(fn(SalesOrder $record) => $record->canConfirm())
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Sales Order')
                        ->modalDescription('Tandai bahwa order ini sudah dikonfirmasi ke customer.')
                        ->action(function (SalesOrder $record) {
                            try {
                                app(SalesOrderService::class)->confirm($record);
                            } catch (\Exception $e) {
                                Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                            }
                        }),

                    // ── CONVERT KE TRANSAKSI ─────────────────────────
                    Action::make('convert')
                        ->label('Buat Transaksi')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('success')
                        ->visible(fn(SalesOrder $record) => $record->canConvert())
                        ->schema(fn(SalesOrder $record) => self::convertForm($record))
                        ->modalHeading('Buat Transaksi dari SO')
                        ->modalDescription('Lengkapi harga jika belum diisi. Transaksi akan dibuat dan deposit customer terpotong otomatis.')
                        ->modalSubmitActionLabel('Buat Transaksi')
                        ->modalWidth('4xl')
                        ->action(function (SalesOrder $record, array $data) {
                            try {
                                $transaction = app(SalesOrderService::class)->convertToTransaction($record, $data);
                                Notification::make()
                                    ->success()
                                    ->title('Transaksi ' . $transaction->invoice_number . ' berhasil dibuat!')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                            }
                        }),

                    // ── CANCEL ────────────────────────────────────────
                    Action::make('cancel')
                        ->label('Batalkan SO')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(SalesOrder $record) => $record->canCancel())
                        ->form([
                            Textarea::make('reason')
                                ->label('Alasan Pembatalan')
                                ->required()->rows(2),
                        ])
                        ->modalHeading('Batalkan Sales Order')
                        ->action(function (SalesOrder $record, array $data) {
                            try {
                                app(SalesOrderService::class)->cancel($record, $data['reason']);
                            } catch (\Exception $e) {
                                Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                            }
                        }),
                ])->label('Aksi')->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->defaultSort('order_date', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    // Form saat convert ke transaksi — tampilkan item SO untuk review/edit harga
    private static function convertForm(SalesOrder $record): array
    {
        return [
            Placeholder::make('so_info')
                ->label('SO')
                ->content($record->so_number . ' — ' . $record->customer->name),

            Repeater::make('items')
                ->label('Item (cek & lengkapi harga jika belum)')
                ->default(
                    $record->items->map(fn($i) => [
                        'product_id'   => $i->product_id,
                        'product_name' => $i->product_name,
                        'unit_name'    => $i->unit_name,
                        'quantity'     => $i->quantity,
                        'unit_price'   => $i->unit_price > 0 ? $i->unit_price : null,
                        'subtotal'     => $i->subtotal,
                        'notes'        => $i->notes,
                    ])->toArray()
                )
                ->schema([
                    TextInput::make('product_name')->label('Produk')->disabled()->dehydrated(false)->columnSpan(3),
                    TextInput::make('quantity')->label('Qty')->numeric()->required()->columnSpan(1),
                    TextInput::make('unit_name')->label('Satuan')->disabled()->dehydrated(false)->columnSpan(1),
                    TextInput::make('unit_price')->label('Harga')->numeric()->prefix('Rp')->required()
                        ->live(debounce: 400)
                        ->afterStateUpdated(
                            fn(Get $get, Set $set) =>
                            $set('subtotal', ((int)($get('quantity') ?? 0)) * ((float)($get('unit_price') ?? 0)))
                        )
                        ->columnSpan(2),
                    Placeholder::make('subtotal_display')->label('Subtotal')
                        ->content(fn(Get $get) => 'Rp ' . number_format(
                            ((int)($get('quantity') ?? 0)) * ((float)($get('unit_price') ?? 0)),
                            0,
                            ',',
                            '.'
                        ))->columnSpan(2),
                    TextInput::make('notes')->label('Ket.')->nullable()->columnSpan(3),
                    TextInput::make('product_id')->hidden()->dehydrated(),
                    TextInput::make('subtotal')->hidden()->dehydrated(),
                ])
                ->columns(12)
                ->reorderable(false)
                ->addable(false)
                ->deletable(false),

            Checkbox::make('admin_override')
                ->label('Override — buat transaksi meski deposit tidak mencukupi')
                ->default(false)
                ->dehydrated()
                ->visible($record->customer->depositBalance() < $record->grand_total),

            Textarea::make('delivery_note')
                ->label('No. Surat Jalan / Keterangan Pengiriman')
                ->rows(1)->nullable(),
        ];
    }
}
