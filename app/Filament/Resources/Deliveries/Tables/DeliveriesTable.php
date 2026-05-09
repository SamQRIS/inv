<?php

namespace App\Filament\Resources\Deliveries\Tables;

use App\Models\Delivery;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
// use Filament\Tables\Table as FilamentTable;

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
                        default     => $state,
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
                    ->schema(function (Delivery $record) {

                        // Resolve warehouse default untuk preview stok
                        $defaultWarehouseId = Warehouse::where('is_default', true)
                            ->where('is_active', true)
                            ->value('id')
                            ?? Warehouse::where('is_active', true)->orderBy('sort_order')->value('id');

                        // Ambil item yang belum selesai dikirim + info stok
                        $pendingItems = $record->items
                            ->filter(fn($item) => $item->qtyRemaining() > 0)
                            ->map(function ($item) use ($defaultWarehouseId) {
                                $stockInfo = '';
                                if ($defaultWarehouseId) {
                                    $stock = $item->product->stockAt($defaultWarehouseId);
                                    $stockInfo = $stock <= 0
                                        ? ' | ⚠️ Stok KOSONG'
                                        : ($stock < $item->qtyRemaining()
                                            ? " | ⚠️ Stok kurang: {$stock}"
                                            : " | Stok: {$stock}");
                                }
                                return [
                                    'delivery_item_id'   => $item->id,
                                    'product_info'       => $item->product->name .
                                                            ' [' . $item->product->sku . ']' .
                                                            $stockInfo,
                                    'qty_remaining_info' => (string) $item->qtyRemaining(),
                                    'qty_shipped'        => $item->qtyRemaining(),
                                ];
                            })
                            ->values()
                            ->toArray();

                        return [
                            DatePicker::make('shipment_date')
                                ->label('Tanggal Pengiriman')
                                ->default(today())
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required(),

                            TextInput::make('driver_name')
                                ->label('Nama Driver')
                                ->placeholder('Opsional'),

                            TextInput::make('vehicle_number')
                                ->label('No. Kendaraan')
                                ->placeholder('Opsional'),

                            // ── Pilih gudang asal pengiriman ─────────
                            Select::make('warehouse_id')
                                ->label('Kirim dari Gudang')
                                ->options(Warehouse::where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id'))
                                ->default(
                                    Warehouse::where('is_default', true)
                                        ->where('is_active', true)
                                        ->value('id')
                                )
                                ->required()
                                ->searchable()
                                ->helperText('Stok akan dikurangi dari gudang ini'),

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
                                ->default($pendingItems)
                                ->deletable(false)
                                ->addable(false),

                            Textarea::make('notes')
                                ->label('Catatan Pengiriman')
                                ->placeholder('Opsional')
                                ->rows(2),
                        ];
                    })
                    ->action(function (Delivery $record, array $data, DeliveryService $service) {
                        // Filter item dengan qty_shipped > 0
                        $data['items'] = collect($data['items'])
                            ->filter(fn($item) => (int) ($item['qty_shipped'] ?? 0) > 0)
                            ->values()
                            ->toArray();

                        if (empty($data['items'])) {
                            Notification::make()
                                ->warning()
                                ->title('Tidak ada item yang dikirim.')
                                ->send();
                            return;
                        }

                        try {
                            $service->processShipment($record, $data);

                            Notification::make()
                                ->success()
                                ->title('Pengiriman berhasil diproses.')
                                ->send();

                        } catch (\Illuminate\Validation\ValidationException $e) {
                            // Tampilkan semua error stok/qty sebagai notifikasi merah
                            $messages = collect($e->errors())->flatten()->implode("\n");

                            Notification::make()
                                ->danger()
                                ->title('Pengiriman gagal — stok tidak mencukupi')
                                ->body($messages)
                                ->persistent() // tidak hilang otomatis agar user sempat baca
                                ->send();
                        }
                    }),

                Action::make('print_surat_jalan')
                    ->label('Surat Jalan')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
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
