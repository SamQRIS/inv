<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('tabs')->tabs([

                    Tab::make('Informasi Produk')
                        ->icon('heroicon-m-information-circle')
                        ->schema([
                            Section::make()->columns(2)->schema([
                                TextInput::make('name')
                                    ->label('Nama Produk')->required()->maxLength(255)->columnSpanFull(),

                                TextInput::make('sku')
                                    ->label('SKU')->required()->unique(ignoreRecord: true)->maxLength(100),

                                Select::make('category_id')
                                    ->label('Kategori')
                                    ->relationship('category', 'name', fn($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),

                                        TextInput::make('slug')
                                            ->required()
                                            ->unique('categories', 'slug'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Category::create([
                                            'name' => $data['name'],
                                            'slug' => $data['slug'],
                                            'is_active' => true,
                                        ])->id;
                                    }),

                                Select::make('unit_id')
                                    ->label('Satuan')
                                    ->options(Unit::pluck('name', 'id'))
                                    ->searchable()->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),

                                        TextInput::make('symbol')
                                            ->required()
                                            ->unique('units', 'symbol'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Unit::create([
                                            'name' => $data['name'],
                                            'symbol' => $data['symbol'],
                                            'is_active' => true,
                                        ])->id;
                                    }),

                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()->nullable(),

                                Textarea::make('description')
                                    ->label('Deskripsi')->rows(3)->columnSpanFull(),

                                Toggle::make('is_active')
                                    ->label('Produk Aktif')->default(true)->columnSpanFull(),
                            ]),
                        ]),

                    Tabs\Tab::make('Harga')
                        ->icon('heroicon-m-banknotes')
                        ->schema([
                            Section::make()->columns(2)->schema([
                                TextInput::make('cost_price')
                                    ->label('Harga Modal')->numeric()->prefix('Rp')->minValue(0),
                                TextInput::make('selling_price')
                                    ->label('Harga Jual')->numeric()->prefix('Rp')->minValue(0),
                            ]),
                        ]),

                    Tabs\Tab::make('Stok per Gudang')
                        ->icon('heroicon-m-building-storefront')
                        ->schema([
                            Section::make()
                                ->description('Atur stok awal di masing-masing gudang. Setelah produk dibuat, kelola mutasi stok via menu Gudang atau tombol Mutasi Stok.')
                                ->schema([
                                    TextInput::make('minimum_stock')
                                        ->label('Minimum Stok Global (semua gudang)')
                                        ->numeric()->default(0)->minValue(0)
                                        ->helperText('Alert low stock muncul jika total stok semua gudang ≤ angka ini.'),

                                    // Hanya tampil saat CREATE
                                    Repeater::make('stock_per_warehouse')
                                        ->label('Stok Awal per Gudang')
                                        ->schema([
                                            Select::make('warehouse_id')
                                                ->label('Gudang')
                                                ->options(Warehouse::active()->orderBy('sort_order')->pluck('name', 'id'))
                                                ->required()->distinct()->columnSpan(4),

                                            TextInput::make('quantity')
                                                ->label('Stok Awal')
                                                ->numeric()->minValue(0)->default(0)->required()->columnSpan(2),

                                            TextInput::make('minimum_stock')
                                                ->label('Min. Stok (gudang ini)')
                                                ->numeric()->minValue(0)->default(0)->columnSpan(2),
                                        ])
                                        ->columns(8)
                                        ->addActionLabel('+ Tambah Gudang')
                                        ->default(
                                            Warehouse::active()->orderBy('sort_order')->get()->map(fn($w) => [
                                                'warehouse_id'  => $w->id,
                                                'quantity'      => 0,
                                                'minimum_stock' => 0,
                                            ])->toArray()
                                        )
                                        ->dehydrated(false)
                                        ->visible(fn(string $operation) => $operation === 'create'),

                                    // Saat EDIT: tampilkan stok saat ini (read-only)
                                    Placeholder::make('stock_per_warehouse_edit')
                                        ->label('Stok Saat Ini per Gudang')
                                        ->content(function (Product $record) {
                                            if (!$record) return '-';
                                            return $record->productStocks()
                                                ->with('warehouse')
                                                ->get()
                                                ->map(
                                                    fn($ps) =>
                                                    "• {$ps->warehouse->name} [{$ps->warehouse->code}]: " .
                                                        number_format($ps->quantity) .
                                                        ($ps->isLowStock() ? ' ⚠️' : ' ✅')
                                                )
                                                ->join("\n") ?: 'Belum ada stok';
                                        })
                                        ->visible(fn(string $operation) => $operation === 'edit'),

                                    Placeholder::make('edit_hint')
                                        ->label('')
                                        ->content('💡 Untuk mengubah stok, gunakan tombol "Mutasi Stok" di tabel produk atau menu Gudang / Lokasi.')
                                        ->visible(fn(string $operation) => $operation === 'edit'),
                                ]),
                        ]),

                ])->columnSpanFull(),
            ]);
    }
}
