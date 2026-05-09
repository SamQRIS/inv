<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\DiscountService;
use Filament\Actions\Action;
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

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(5)->schema([

                    // ── Kolom Kiri (span 2) ──────────────────────────────
                    Group::make()->columnSpan(4)->schema([

                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('No. Invoice')
                                    ->default(fn() => Transaction::generateInvoiceNumber())
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-m-document-text'),

                                DatePicker::make('transaction_date')
                                    ->label('Tanggal Transaksi')
                                    ->default(today())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),

                                Select::make('customer_id')
                                    ->label('Customer DO / Tempo')
                                    ->options(Customer::where('type', 'do')->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->placeholder('— Pilih customer DO —')
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state) {
                                            $customer = Customer::find($state);
                                            if ($customer?->default_discount) {
                                                $set('discount_json', $customer->default_discount);
                                            }
                                        }
                                        self::recalculate($get, $set);
                                    }),
                            ]),

                        Section::make('Customer End User')
                            ->columns(2)
                            ->schema([
                                TextInput::make('end_user_name')
                                    ->label('Nama End User')
                                    ->placeholder('Nama pembeli'),
                                TextInput::make('end_user_phone')
                                    ->label('No. HP')->tel(),
                                Textarea::make('end_user_address')
                                    ->label('Alamat')
                                    ->placeholder('Alamat lengkap customer')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn(Get $get) => !$get('customer_id')),

                        Section::make('Jadwal Pengiriman')
                            ->columns(12)
                            ->collapsed()
                            ->schema([
                                Radio::make('delivery_date_type')
                                    ->label('Tipe')
                                    ->options([
                                        'none' => 'Belum Ditentukan',
                                        'date' => 'Tanggal Pasti',
                                        'text' => 'Teks Bebas',
                                    ])
                                    ->default('none')
                                    ->inline()
                                    ->live()
                                    ->columnSpan(12),

                                DatePicker::make('delivery_date')
                                    ->label('Tanggal Kirim')
                                    ->native(false)->displayFormat('d/m/Y')
                                    ->visible(fn(Get $get) => $get('delivery_date_type') === 'date')
                                    ->required(fn(Get $get) => $get('delivery_date_type') === 'date')
                                    ->columnSpan(6),

                                TextInput::make('delivery_note')
                                    ->label('Keterangan Kirim')
                                    ->placeholder('Contoh: kirim bertahap, urgent, dll')
                                    ->visible(fn(Get $get) => $get('delivery_date_type') === 'text')
                                    ->required(fn(Get $get) => $get('delivery_date_type') === 'text')
                                    ->columnSpan(8),
                            ]),

                        Section::make('Item Pesanan')
                            ->schema([
                                Repeater::make('items')
                                    ->table([
                                        TableColumn::make('Nama Produk'),
                                        TableColumn::make('Qty')
                                            ->width('100px'),
                                        TableColumn::make('Satuan')
                                            ->width('100px'),
                                        TableColumn::make('Harga Satuan')
                                            ->width('200px'),
                                        TableColumn::make('Subtotal')
                                            ->width('200px')
                                    ])
                                    ->label('')
                                    ->schema([
                                        Select::make('product_id')
                                            // ->label('Produk')
                                            ->options(
                                                Product::where('is_active', true)
                                                    ->with(['unit', 'category'])
                                                    ->get()
                                                    ->mapWithKeys(fn($p) => [
                                                        $p->id => "[{$p->category->name}] {$p->name} — Stok: {$p->stock_quantity} {$p->unit->symbol}",
                                                    ])
                                            )
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                if (!$state) return;
                                                $product = Product::with('unit')->find($state);
                                                if (!$product) return;
                                                $set('unit_price', $product->selling_price);
                                                $set('unit_name', $product->unit->symbol);
                                                $qty = (int) ($get('quantity') ?? 1);
                                                $set('line_subtotal', $qty * (float) $product->selling_price);
                                                self::recalculate($get, $set);
                                            })
                                            ->columnSpan(3),

                                        TextInput::make('quantity')
                                            // ->label('Qty')
                                            ->numeric()->minValue(1)->default(1)->required()
                                            ->live(debounce: 400)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $set('line_subtotal', (int)($state ?? 0) * (float)($get('unit_price') ?? 0));
                                                self::recalculate($get, $set);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('unit_name')
                                            // ->label('Satuan')
                                            ->disabled()->dehydrated(false)->placeholder('—'),
                                        // ->columnSpan(2),

                                        TextInput::make('unit_price')
                                            // ->label('Harga Satuan')
                                            ->numeric()->prefix('Rp')
                                            ->live(debounce: 400)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $set('line_subtotal', (int)($get('quantity') ?? 0) * (float)($state ?? 0));
                                                self::recalculate($get, $set);
                                            })
                                            ->columnSpan(3),

                                        TextInput::make('line_subtotal')
                                            // ->label('Subtotal')
                                            ->prefix('Rp')->disabled()->dehydrated(false)
                                            ->columnSpan(3),

                                        // TextInput::make('notes')
                                        //     ->label('Catatan item')->placeholder('Opsional')
                                        //     ->columnSpanFull(),
                                    ])
                                    ->columns(12)
                                    ->compact()
                                    ->minItems(1)
                                    ->reorderable(false)
                                    ->defaultItems(5)
                                    ->addActionLabel('+ Tambah Item')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set))
                                    ->deleteAction(
                                        fn(Action $action) => $action->after(
                                            fn(Get $get, Set $set) => self::recalculate($get, $set)
                                        )
                                    ),
                            ]),

                        Section::make('Diskon')
                            ->collapsed()
                            ->schema([
                                Repeater::make('discount_json')
                                    ->table([
                                        TableColumn::make('Tipe'),
                                        TableColumn::make('Nilai Diskon'),
                                        TableColumn::make('Preview')
                                    ])
                                    ->label('Layer Diskon')
                                    ->helperText('Dihitung berurutan. Contoh: 5% + Rp 200.000 dari 1.000.000 → 950.000 → 750.000')
                                    ->schema([
                                        Select::make('type')
                                            // ->label('Tipe')
                                            ->options(['percent' => 'Persentase (%)', 'nominal' => 'Nominal (Rp)'])
                                            ->required()->live()->columnSpan(3),

                                        TextInput::make('value')
                                            ->label(fn(Get $get) => $get('type') === 'percent' ? 'Nilai (%)' : 'Nilai (Rp)')
                                            ->placeholder((fn(Get $get) => $get('type') === 'percent'
                                                ? 'Masukkan persentase (contoh: 10 untuk 10%)'
                                                : 'Masukkan nominal dalam Rupiah'
                                            ))
                                            ->numeric()->minValue(0)->required()
                                            ->live(debounce: 400)
                                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set))
                                            ->columnSpan(3),

                                        Placeholder::make('layer_label')
                                            // ->label('Preview')
                                            ->content(function (Get $get): string {
                                                $type  = $get('type');
                                                $value = (float) ($get('value') ?? 0);
                                                if (!$type || !$value) return '—';
                                                return $type === 'percent'
                                                    ? "Diskon {$value}%"
                                                    : 'Diskon Rp ' . number_format($value, 0, ',', '.');
                                            })
                                            ->columnSpan(6),
                                    ])
                                    // ->compact()
                                    ->columns(12)->maxItems(5)
                                    ->addActionLabel('+ Tambah Layer Diskon')
                                    ->reorderable(false)
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set))
                                    ->deleteAction(
                                        fn(Action $action) => $action->after(
                                            fn(Get $get, Set $set) => self::recalculate($get, $set)
                                        )
                                    ),
                            ]),

                        Section::make('Catatan')
                            ->collapsed()
                            ->schema([
                                Textarea::make('notes')
                                    ->label('')->placeholder('Catatan tambahan...')->rows(2)->columnSpanFull(),
                            ]),
                    ]),

                    // ── Kolom Kanan: Summary + Pembayaran ────────────────
                    Group::make()->columnSpan(1)->schema([

                        Section::make('Ringkasan')
                            ->schema([
                                Hidden::make('_subtotal_raw')->default(0),
                                Hidden::make('_discount_raw')->default(0),
                                Hidden::make('_grandtotal_raw')->default(0),

                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(
                                        fn(Get $get) =>
                                        'Rp ' . number_format((float)($get('_subtotal_raw') ?? 0), 0, ',', '.')
                                    ),

                                Placeholder::make('discount_display')
                                    ->label('Total Diskon')
                                    ->content(function (Get $get): string {
                                        $disc = (float)($get('_discount_raw') ?? 0);
                                        if ($disc <= 0) return '—';
                                        $suffix = app(DiscountService::class)->formatSummary($get('discount_json') ?? []);
                                        return '-Rp ' . number_format($disc, 0, ',', '.') . ' (' . $suffix . ')';
                                    }),

                                Placeholder::make('grand_total_display')
                                    ->label('Grand Total')
                                    ->content(
                                        fn(Get $get) =>
                                        'Rp ' . number_format((float)($get('_grandtotal_raw') ?? 0), 0, ',', '.')
                                    )
                                    ->extraAttributes(['style' => 'font-size:1.2rem;font-weight:700']),

                                Placeholder::make('total_paid_display')
                                    ->label('Total Dibayar')
                                    ->content(
                                        fn(Get $get) =>
                                        'Rp ' . number_format(
                                            collect($get('payments') ?? [])->sum(fn($p) => (float)($p['amount'] ?? 0)),
                                            0,
                                            ',',
                                            '.'
                                        )
                                    ),

                                Placeholder::make('remaining_display')
                                    ->label('Sisa Tagihan')
                                    ->content(function (Get $get): string {
                                        $gt   = (float)($get('_grandtotal_raw') ?? 0);
                                        $paid = collect($get('payments') ?? [])->sum(fn($p) => (float)($p['amount'] ?? 0));
                                        return 'Rp ' . number_format(max(0, $gt - $paid), 0, ',', '.');
                                    }),

                                Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(function (Get $get): string {
                                        $gt   = (float)($get('_grandtotal_raw') ?? 0);
                                        $paid = collect($get('payments') ?? [])->sum(fn($p) => (float)($p['amount'] ?? 0));
                                        if ($gt <= 0)     return '—';
                                        if ($paid <= 0)   return '🔴 Belum Bayar';
                                        if ($paid >= $gt) return '✅ Lunas';
                                        return '🟡 Sebagian (' . number_format($paid / $gt * 100, 1) . '%)';
                                    }),
                            ]),

                        Section::make('Pembayaran')
                            ->schema([
                                Repeater::make('payments')
                                    ->label('')
                                    ->schema([
                                        Select::make('payment_method_id')
                                            ->label('Metode')
                                            ->options(PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                                            ->required()->searchable()->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $method = PaymentMethod::find($state);
                                                $set('is_installment', (bool) $method?->is_installment);
                                                if (!$method?->is_installment) {
                                                    $gt   = (float)($get('../../_grandtotal_raw') ?? 0);
                                                    $paid = collect($get('../../payments') ?? [])
                                                        ->sum(fn($p) => (float)($p['amount'] ?? 0));
                                                    $rem  = max(0, $gt - $paid);
                                                    if ($rem > 0) $set('amount', $rem);
                                                }
                                            })
                                            ->columnSpanFull(),

                                        TextInput::make('amount')
                                            ->label('Jumlah (Rp)')->numeric()->prefix('Rp')->required()->minValue(1)
                                            ->live(debounce: 400)
                                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set))
                                            ->columnSpanFull(),

                                        DatePicker::make('payment_date')
                                            ->label('Tanggal')->default(today())->required()
                                            ->native(false)->displayFormat('d/m/Y')
                                            ->columnSpanFull(),

                                        TextInput::make('reference_number')
                                            ->label('No. Referensi')->placeholder('No. transfer / kode bayar')
                                            ->columnSpanFull(),

                                        Hidden::make('is_installment')->default(false),

                                        Fieldset::make('Detail Cicilan Pihak Ketiga')
                                            ->visible(fn(Get $get) => (bool) $get('is_installment'))
                                            // ->columns(2)
                                            ->schema([
                                                TextInput::make('installment_detail.provider')->label('Provider')
                                                    ->columnSpanFull(),
                                                TextInput::make('installment_detail.tenor')->label('Tenor (bulan)')->numeric()
                                                    ->columnSpanFull(),
                                                TextInput::make('installment_detail.contract_number')->label('No. Kontrak')
                                                    ->columnSpanFull(),
                                                TextInput::make('installment_detail.monthly_amount')->label('Cicilan/Bulan')->numeric()->prefix('Rp')->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    // ->columns()
                                    ->addActionLabel('+ Tambah Pembayaran')
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set))
                                    ->deleteAction(
                                        fn(Action $action) => $action->after(
                                            fn(Get $get, Set $set) => self::recalculate($get, $set)
                                        )
                                    ),
                            ]),
                    ]),
                ]),
            ])->columns(1);
    }

    private static function recalculate(Get $get, Set $set): void
    {
        $items    = $get('items') ?? [];
        $subtotal = 0.0;

        foreach ($items as $key => $item) {
            $qty   = (int)   ($item['quantity']  ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $line  = $qty * $price;
            $subtotal += $line;
            $set("items.{$key}.line_subtotal", $line);
        }

        $discountAmount = 0.0;
        $grandTotal     = $subtotal;
        $discountLayers = $get('discount_json') ?? [];

        if (!empty($discountLayers)) {
            try {
                $result         = app(DiscountService::class)->apply($subtotal, $discountLayers);
                $discountAmount = $result['discount_amount'];
                $grandTotal     = $result['after_discount'];
            } catch (\Throwable) {
            }
        }

        $set('_subtotal_raw',   $subtotal);
        $set('_discount_raw',   $discountAmount);
        $set('_grandtotal_raw', $grandTotal);
    }
}
