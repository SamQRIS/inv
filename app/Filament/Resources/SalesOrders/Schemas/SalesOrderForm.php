<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\DiscountService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(5)->schema([

                    // ── Kolom Kiri (span 2) ──────────────────────────────
                    Group::make()->columnSpan(4)
                        ->schema([

                            Section::make('Informasi Order')
                                ->columns(2)
                                ->schema([
                                    Select::make('customer_id')
                                        ->label('Customer DO')
                                        ->options(
                                            Customer::where('type', 'do')
                                                ->where('is_active', true)
                                                ->get()
                                                ->mapWithKeys(fn($c) => [
                                                    $c->id => $c->name .
                                                        ' — Deposit: Rp ' . number_format($c->deposit_balance, 0, ',', '.'),
                                                ])
                                        )
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->helperText('Hanya customer bertipe DO.'),

                                    DatePicker::make('order_date')
                                        ->label('Tanggal Order')
                                        ->default(today())
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d/m/Y'),

                                    DatePicker::make('estimated_delivery_date')
                                        ->label('Estimasi Kirim')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->nullable()
                                        ->helperText('Kosongkan jika belum tahu.'),

                                    // Info deposit customer
                                    Placeholder::make('deposit_info')
                                        ->label('Saldo Deposit')
                                        ->content(function (Get $get): string {
                                            $id = $get('customer_id');
                                            if (!$id) return '— Pilih customer dulu';
                                            $customer = Customer::find($id);
                                            if (!$customer) return '—';
                                            $deposit = $customer->depositBalance();
                                            return 'Rp ' . number_format($deposit, 0, ',', '.') .
                                                ($deposit <= 0 ? ' ⚠ Deposit habis!' : ' ✓');
                                        }),
                                ]),

                            Section::make('List Barang yang Dipesan')
                                ->description('Input sesuai list order dari customer. Harga bisa dikosongkan jika belum fix — bisa diisi saat convert ke transaksi.')
                                ->schema([
                                    Repeater::make('items')
                                        ->table([
                                            TableColumn::make('Produk'),
                                            TableColumn::make('Qty')->width('80px'),
                                            TableColumn::make('Satuan')->width('80px'),
                                            TableColumn::make('Harga')->width('160px'),
                                            TableColumn::make('Subtotal')->width('160px'),
                                            TableColumn::make('Keterangan')->width('200px'),
                                        ])
                                        ->label('')
                                        ->schema([
                                            Select::make('product_id')
                                                ->options(
                                                    Product::where('is_active', true)
                                                        ->with(['unit', 'category'])
                                                        ->get()
                                                        ->mapWithKeys(fn($p) => [
                                                            $p->id => "[{$p->category->name}] {$p->name}",
                                                        ])
                                                )
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                    if (!$state) return;
                                                    $product = Product::with('unit')->find($state);
                                                    if (!$product) return;
                                                    $set('product_name', $product->name);
                                                    $set('unit_name', $product->unit->symbol);
                                                    // Auto-isi harga jika ada
                                                    if ($product->selling_price > 0) {
                                                        $set('unit_price', $product->selling_price);
                                                    }
                                                    self::recalcItem($get, $set);
                                                }),

                                            TextInput::make('quantity')
                                                ->numeric()->minValue(1)->default(1)->required()
                                                ->live(debounce: 400)
                                                ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcItem($get, $set)),

                                            TextInput::make('unit_name')
                                                ->disabled()->dehydrated(false),

                                            TextInput::make('unit_price')
                                                ->numeric()->prefix('Rp')
                                                ->nullable()
                                                ->placeholder('Belum fix')
                                                ->live(debounce: 400)
                                                ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcItem($get, $set))
                                                ->helperText('Boleh kosong'),

                                            Placeholder::make('subtotal_display')
                                                ->label('Subtotal')
                                                ->content(
                                                    fn(Get $get) => ((float)($get('unit_price') ?? 0)) > 0
                                                        ? 'Rp ' . number_format(
                                                            ((int)($get('quantity') ?? 0)) * ((float)($get('unit_price') ?? 0)),
                                                            0,
                                                            ',',
                                                            '.'
                                                        )
                                                        : '—'
                                                ),

                                            TextInput::make('notes')
                                                ->placeholder('Warna, ukuran, spec...')
                                                ->nullable(),

                                            // Hidden fields
                                            TextInput::make('product_name')->hidden()->dehydrated(),
                                            TextInput::make('subtotal')->hidden()->dehydrated(),
                                        ])
                                        ->minItems(1)
                                        ->reorderable(false)
                                        ->defaultItems(1)
                                        ->addActionLabel('+ Tambah Item')
                                        ->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcTotal($get, $set)),
                                ]),

                            Section::make('Catatan')
                                ->collapsed()
                                ->schema([
                                    Textarea::make('notes')
                                        ->label('')
                                        ->rows(2)
                                        ->placeholder('Catatan tambahan dari customer...'),
                                ]),
                        ]),

                    // ── Kolom Kanan: Summary + Pembayaran ────────────────
                    Group::make()->columnSpan(1)->schema([

                        Section::make('Ringkasan')
                            ->schema([
                                Placeholder::make('total_items')
                                    ->label('Total Item')
                                    ->content(
                                        fn(Get $get) =>
                                        collect($get('items') ?? [])->count() . ' item'
                                    ),

                                Placeholder::make('grand_total_display')
                                    ->label('Estimasi Total')
                                    ->content(function (Get $get): string {
                                        $total = collect($get('items') ?? [])->sum(
                                            fn($i) => ((int)($i['quantity'] ?? 0)) * ((float)($i['unit_price'] ?? 0))
                                        );
                                        return $total > 0
                                            ? 'Rp ' . number_format($total, 0, ',', '.')
                                            : '— (harga belum diisi)';
                                    }),

                                Placeholder::make('info_harga')
                                    ->label('')
                                    ->content('ℹ Harga boleh belum diisi. Bisa dilengkapi saat convert ke transaksi.'),
                            ]),
                    ]),
                ]),
            ])->columns(1);
    }

    private static function recalcItem(Get $get, Set $set): void
    {
        $qty   = (int)   ($get('quantity')   ?? 0);
        $price = (float) ($get('unit_price') ?? 0);
        $set('subtotal', $qty * $price);
    }
 
    private static function recalcTotal(Get $get, Set $set): void
    {
        // Total dihitung live via Placeholder
    }
}
