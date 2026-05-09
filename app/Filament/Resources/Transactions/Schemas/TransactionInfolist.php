<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Services\DiscountService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $discountService = app(DiscountService::class);
        return $schema
            ->components([
                Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('invoice_number')
                        ->label('No. Invoice')->copyable()->weight('bold'),
                    TextEntry::make('transaction_date')
                        ->label('Tanggal')->date('d/m/Y'),
                    TextEntry::make('customer.name')
                        ->label('Customer')->placeholder('—'),
                    TextEntry::make('user.name')
                        ->label('Kasir'),
                    TextEntry::make('payment_status')
                        ->label('Status Bayar')->badge()
                        ->color(fn($state) => match($state) {
                            'unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', default => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match($state) {
                            'unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', default => $state,
                        }),
                    TextEntry::make('delivery_status')
                        ->label('Status Kirim')->badge()
                        ->color(fn($state) => match($state) {
                            'pending' => 'gray', 'partial' => 'warning', 'delivered' => 'success', default => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match($state) {
                            'pending' => 'Menunggu', 'partial' => 'Sebagian Terkirim', 'delivered' => 'Terkirim', default => $state,
                        }),
                    TextEntry::make('delivery_date_display')
                        ->label('Jadwal Kirim')->placeholder('—'),
                    TextEntry::make('notes')
                        ->label('Catatan')->placeholder('—'),
                ]),
 
            Section::make('Item Pesanan')
                ->schema([
                    RepeatableEntry::make('items')->label('')
                        ->schema([
                            TextEntry::make('product_name')
                                ->label('Produk')
                                ->helperText(fn($record) => $record->product_sku),
                            TextEntry::make('quantity')
                                ->label('Qty')->suffix(fn($record) => ' ' . $record->unit_name),
                            TextEntry::make('unit_price')
                                ->label('Harga Satuan')->money('IDR'),
                            TextEntry::make('subtotal')
                                ->label('Subtotal')->money('IDR')->weight('bold'),
                        ])
                        ->columns(4),
                ]),
 
            Section::make('Ringkasan')->columns(2)
                ->schema([
                    Group::make()->schema([
                        TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                        TextEntry::make('discount_amount')
                            ->label('Diskon')->money('IDR')->color('danger')
                            ->visible(fn($record) => $record->discount_amount > 0)
                            ->hint(fn($record) => $discountService->formatSummary($record->discount_json ?? [])),
                        TextEntry::make('grand_total')
                            ->label('Grand Total')->money('IDR')->weight('bold')->size('xl'),
                    ]),
                    Group::make()->schema([
                        TextEntry::make('amount_paid')
                            ->label('Sudah Dibayar')->money('IDR')->color('success'),
                        TextEntry::make('amount_remaining')
                            ->label('Sisa Tagihan')->money('IDR')->weight('bold')
                            ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success'),
                    ]),
                ]),
 
            Section::make('Riwayat Pembayaran')
                ->visible(fn($record) => $record->payments->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('payments')->label('')
                        ->schema([
                            TextEntry::make('payment_date')->label('Tanggal')->date('d/m/Y'),
                            TextEntry::make('paymentMethod.name')->label('Metode')->badge(),
                            TextEntry::make('amount')->label('Jumlah')->money('IDR')->weight('bold'),
                            TextEntry::make('reference_number')->label('Referensi')->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
 
            Section::make('Delivery Orders')
                ->visible(fn($record) => $record->deliveries->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('deliveries')->label('')
                        ->schema([
                            TextEntry::make('do_number')->label('No. DO'),
                            TextEntry::make('do_date')->label('Tanggal')->date('d/m/Y'),
                            TextEntry::make('status')->label('Status')->badge()
                                ->color(fn($state) => match($state) {
                                    'pending' => 'gray', 'partial' => 'warning', 'completed' => 'success', default => 'gray',
                                })
                                ->formatStateUsing(fn($state) => match($state) {
                                    'pending' => 'Menunggu', 'partial' => 'Sebagian', 'completed' => 'Selesai', default => $state,
                                }),
                        ])
                        ->columns(3),
                ]),
            ]);
    }
}
