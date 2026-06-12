<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Models\Customer;
use App\Models\ProductionOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductionOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── HEADER ────────────────────────────────────────────────
                \Filament\Schemas\Components\Grid::make(2)->schema([

                    Select::make('customer_id')
                        ->label('Customer DO / Pemesan')
                        ->options(
                            Customer::where('type', 'do')
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $customer = Customer::find($state);
                            if ($customer?->address) {
                                $set('delivery_address', $customer->address);
                            }
                        }),

                    TextInput::make('order_number')
                        ->label('No. Pesanan')
                        ->default(fn() => ProductionOrder::generateOrderNumber())
                        ->required()
                        ->unique(ignoreRecord: true),

                    DatePicker::make('order_date')
                        ->label('Tanggal Pesan')
                        ->default(today())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('target_date')
                        ->label('Target Selesai Produksi')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->nullable()
                        ->helperText('Kosongkan jika belum ditentukan'),

                    TextInput::make('delivery_address')
                        ->label('Alamat Pengiriman')
                        ->placeholder('Otomatis dari data customer')
                        ->columnSpanFull(),

                    Textarea::make('customer_notes')
                        ->label('Catatan dari Customer (WA)')
                        ->rows(2)
                        ->placeholder('Salin pesan WA dari customer jika perlu...')
                        ->nullable()
                        ->columnSpanFull(),

                    Textarea::make('production_notes')
                        ->label('Catatan untuk Tim Produksi')
                        ->rows(2)
                        ->placeholder('Instruksi khusus, prioritas, dll...')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

                // ── ITEM REPEATER ─────────────────────────────────────────
                Repeater::make('items')
                    ->relationship()
                    ->table([
                        TableColumn::make('Nama Produk'),
                        TableColumn::make('Ukuran'),
                        TableColumn::make('Warna'),
                        TableColumn::make('Sandaran / Tipe'),
                        TableColumn::make('Qty')
                        ->width('100px'),
                        TableColumn::make('Keterangan Tambahan'),
                    ])
                    ->label('Daftar Pesanan')
                    ->schema([
                        TextInput::make('product_name')
                            ->label('Nama Produk')
                            ->placeholder('Contoh: DIVAN + HEADBOARD, KASUR SPRING BED')
                            ->required()
                            ->columnSpan(3),

                        TextInput::make('size')
                            ->label('Ukuran')
                            ->placeholder('120, 160, 180x200')
                            ->nullable()
                            ->columnSpan(1),

                        TextInput::make('color')
                            ->label('Warna / Motif')
                            ->placeholder('HITAM, PUTIH, BIRU')
                            ->nullable()
                            ->columnSpan(2),

                        TextInput::make('headboard_type')
                            ->label('Sandaran / Tipe')
                            ->placeholder('VILUMA, PALLADIUM')
                            ->nullable()
                            ->columnSpan(2),

                        TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('item_notes')
                            ->label('Keterangan Tambahan')
                            ->placeholder('URGENT, kaki chrome, dll')
                            ->nullable()
                            ->columnSpan(3),
                    ])
                    ->columns(12)
                    ->minItems(1)
                    ->defaultItems(3)
                    ->addActionLabel('+ Tambah Item')
                    ->reorderable()
                    ->columnSpanFull(),
            ]);
    }
}
