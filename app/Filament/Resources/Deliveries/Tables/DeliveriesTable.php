<?php

namespace App\Filament\Resources\Deliveries\Tables;

use App\Models\Delivery;
use App\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\TrashedFilter;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('do_number')
                    ->label('No. DO')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('transaction.invoice_number')
                    ->label('Invoice')
                    ->searchable(),

                TextColumn::make('transaction.customer.name')
                    ->label('Customer')
                    ->placeholder('-'),

                TextColumn::make('do_date')
                    ->label('Tanggal DO')
                    ->date('d/m/Y')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'partial',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending'   => 'Menunggu',
                        'partial'   => 'Sebagian',
                        'completed' => 'Selesai',
                    }),

                TextColumn::make('shipments_count')
                    ->label('Pengiriman')
                    ->counts('shipments'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Menunggu',
                        'partial'   => 'Sebagian',
                        'completed' => 'Selesai',
                    ]),
            ])
            ->recordActions([
                Action::make('process_shipment')
                    ->label('Kirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn(Delivery $record) => $record->status !== 'completed')
                    ->form(function (Delivery $record) {
                        return [
                            DatePicker::make('shipment_date')
                                ->label('Tanggal Pengiriman')
                                ->default(today())
                                ->required(),

                            TextInput::make('driver_name')
                                ->label('Nama Driver'),

                            TextInput::make('vehicle_number')
                                ->label('No. Kendaraan'),

                            Repeater::make('items')
                                ->label('Item yang Dikirim')
                                ->schema([
                                    Hidden::make('delivery_item_id'),
                                    Placeholder::make('product_info')
                                        ->label('Produk')
                                        ->columnSpan(4),
                                    TextInput::make('qty_remaining_info')
                                        ->label('Sisa')
                                        ->disabled()
                                        ->columnSpan(2),
                                    TextInput::make('qty_shipped')
                                        ->label('Kirim Sekarang')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->columnSpan(2),
                                ])
                                ->columns(8)
                                ->default(
                                    $record->items
                                        ->where('qty_delivered', '<', 'qty_ordered')
                                        ->map(fn($item) => [
                                            'delivery_item_id' => $item->id,
                                            'product_info'     => $item->product->name . ' [' . $item->product->sku . ']',
                                            'qty_remaining_info' => $item->qtyRemaining(),
                                            'qty_shipped'      => $item->qtyRemaining(),
                                        ])
                                        ->values()
                                        ->toArray()
                                )
                                ->deletable(false)
                                ->addable(false),

                            Textarea::make('notes')
                                ->label('Catatan Pengiriman'),
                        ];
                    })
                    ->action(function (Delivery $record, array $data, DeliveryService $service) {
                        // Filter item dengan qty_shipped > 0
                        $data['items'] = collect($data['items'])
                            ->filter(fn($item) => ($item['qty_shipped'] ?? 0) > 0)
                            ->values()
                            ->toArray();

                        if (empty($data['items'])) {
                            Notification::make()->warning()->title('Tidak ada item yang dikirim.')->send();
                            return;
                        }

                        $service->processShipment($record, $data);
                        Notification::make()->success()->title('Pengiriman berhasil diproses.')->send();
                    }),

                Action::make('print_surat_jalan')
                    ->label('Surat Jalan')
                    ->icon('heroicon-o-document-text')
                    ->url(fn(Delivery $record) => route('delivery.surat-jalan', $record))
                    ->openUrlInNewTab(),

                ViewAction::make(),
            ])
            ->defaultSort('do_date', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
