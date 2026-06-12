<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductFabric;
use App\Models\ProductionOrder;
use App\Models\ProductSize;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                        TableColumn::make('Produk (Katalog)'),
                        TableColumn::make('Ukuran'),
                        TableColumn::make('Kain'),
                        TableColumn::make('Warna'),
                        TableColumn::make('Nama Produk (Cetak)'),
                        TableColumn::make('Qty')
                            ->width('80px'),
                        TableColumn::make('Keterangan Tambahan'),
                    ])
                    ->label('Daftar Pesanan')
                    ->schema([
                        // ── PILIH DARI KATALOG (opsional) ─────────────────
                        Select::make('product_id')
                            ->label('Produk')
                            ->options(
                                Product::where('is_active', true)
                                    ->with('category')
                                    ->get()
                                    ->mapWithKeys(fn($p) => [
                                        $p->id => $p->category ? "[{$p->category->name}] {$p->name}" : $p->name,
                                    ])
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder('— Custom / belum ada di katalog —')
                            ->live()
                            ->columnSpan(2)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (!$state) {
                                    $set('size_id', null);
                                    $set('fabric_id', null);
                                    $set('color_id', null);
                                    return;
                                }
                                self::updateProductName($get, $set);
                            }),

                        // ── UKURAN (muncul jika divan/kasur) ─────────────────
                        Select::make('size_id')
                            ->label('Ukuran')
                            ->options(ProductSize::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                            ->nullable()
                            ->placeholder('Ukuran')
                            ->visible(function (Get $get) {
                                $id = $get('product_id');
                                if (!$id) return false;
                                $p = Product::find($id);
                                return $p && in_array($p->product_type, ['divan', 'kasur']);
                            })
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::updateProductName($get, $set)),

                        // ── JENIS KAIN (muncul jika divan) ────────────────────
                        Select::make('fabric_id')
                            ->label('Kain')
                            ->options(ProductFabric::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                            ->nullable()
                            ->placeholder('Jenis Kain')
                            ->searchable()
                            ->visible(function (Get $get) {
                                $id = $get('product_id');
                                if (!$id) return false;
                                $p = Product::find($id);
                                return $p && $p->product_type === 'divan';
                            })
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::updateProductName($get, $set)),

                        // ── WARNA (muncul jika divan/kasur) ─────────────────
                        Select::make('color_id')
                            ->label('Warna')
                            ->options(ProductColor::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                            ->nullable()
                            ->placeholder('Warna')
                            ->searchable()
                            ->visible(function (Get $get) {
                                $id = $get('product_id');
                                if (!$id) return false;
                                $p = Product::find($id);
                                return $p && in_array($p->product_type, ['divan', 'kasur']);
                            })
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::updateProductName($get, $set)),

                        // ── NAMA PRODUK UNTUK CETAK ───────────────────────────
                        TextInput::make('product_name')
                            ->label('Nama Produk (Cetak)')
                            ->placeholder('Contoh: DIVAN + HEADBOARD VILUMA 160 OSCAR HITAM')
                            ->required()
                            ->helperText('Terisi otomatis jika pilih dari katalog, atau tulis manual untuk item custom.')
                            ->columnSpan(3),

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
                            ->columnSpan(2),
                    ])
                    ->columns(12)
                    ->minItems(1)
                    ->defaultItems(3)
                    ->addActionLabel('+ Tambah Item')
                    ->reorderable()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Bangun nama produk otomatis dari katalog + variasi (ukuran/kain/warna).
     * Hanya dijalankan ketika produk dipilih dari katalog.
     */
    private static function updateProductName(Get $get, Set $set): void
    {
        $productId = $get('product_id');
        if (!$productId) return;

        $product = Product::find($productId);
        if (!$product) return;

        $sizeId   = $get('size_id');
        $fabricId = $get('fabric_id');
        $colorId  = $get('color_id');

        $sizeName   = $sizeId   ? ProductSize::find($sizeId)?->name     : null;
        $fabricName = $fabricId ? ProductFabric::find($fabricId)?->name : null;
        $colorName  = $colorId  ? ProductColor::find($colorId)?->name   : null;

        $productName = trim(implode(' ', array_filter([
            $product->name,
            $sizeName,
            $fabricName,
            $colorName,
        ])));

        $set('product_name', $productName);
    }
}