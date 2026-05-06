<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Services\DiscountService;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $discountService = app(DiscountService::class);
        return $schema
            ->components([
                Infolists\Components\Section::make()
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('invoice_number')
                        ->label('No. Invoice')->copyable()->weight('bold'),
                    Infolists\Components\TextEntry::make('transaction_date')
                        ->label('Tanggal')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('customer.name')
                        ->label('Customer')->placeholder('—'),
                    Infolists\Components\TextEntry::make('user.name')
                        ->label('Kasir'),
                    Infolists\Components\TextEntry::make('payment_status')
                        ->label('Status Bayar')->badge()
                        ->color(fn($state) => match($state) {
                            'unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', default => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match($state) {
                            'unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', default => $state,
                        }),
                    Infolists\Components\TextEntry::make('delivery_status')
                        ->label('Status Kirim')->badge()
                        ->color(fn($state) => match($state) {
                            'pending' => 'gray', 'partial' => 'warning', 'delivered' => 'success', default => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match($state) {
                            'pending' => 'Menunggu', 'partial' => 'Sebagian Terkirim', 'delivered' => 'Terkirim', default => $state,
                        }),
                    Infolists\Components\TextEntry::make('delivery_date_display')
                        ->label('Jadwal Kirim')->placeholder('—'),
                    Infolists\Components\TextEntry::make('notes')
                        ->label('Catatan')->placeholder('—'),
                ]),
 
            Infolists\Components\Section::make('Item Pesanan')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')
                                ->label('Produk')->description(fn($record) => $record->product_sku),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('Qty')->suffix(fn($record) => ' ' . $record->unit_name),
                            Infolists\Components\TextEntry::make('unit_price')
                                ->label('Harga Satuan')->money('IDR'),
                            Infolists\Components\TextEntry::make('subtotal')
                                ->label('Subtotal')->money('IDR')->weight('bold'),
                        ])
                        ->columns(4),
                ]),
 
            Infolists\Components\Section::make('Ringkasan')->columns(2)
                ->schema([
                    Infolists\Components\Group::make()->schema([
                        Infolists\Components\TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Diskon')->money('IDR')->color('danger')
                            ->visible(fn($record) => $record->discount_amount > 0)
                            ->hint(fn($record) => $discountService->formatSummary($record->discount_json ?? [])),
                        Infolists\Components\TextEntry::make('grand_total')
                            ->label('Grand Total')->money('IDR')->weight('bold')->size('xl'),
                    ]),
                    Infolists\Components\Group::make()->schema([
                        Infolists\Components\TextEntry::make('amount_paid')
                            ->label('Sudah Dibayar')->money('IDR')->color('success'),
                        Infolists\Components\TextEntry::make('amount_remaining')
                            ->label('Sisa Tagihan')->money('IDR')->weight('bold')
                            ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success'),
                    ]),
                ]),
 
            Infolists\Components\Section::make('Riwayat Pembayaran')
                ->visible(fn($record) => $record->payments->isNotEmpty())
                ->schema([
                    Infolists\Components\RepeatableEntry::make('payments')->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('payment_date')->label('Tanggal')->date('d/m/Y'),
                            Infolists\Components\TextEntry::make('paymentMethod.name')->label('Metode')->badge(),
                            Infolists\Components\TextEntry::make('amount')->label('Jumlah')->money('IDR')->weight('bold'),
                            Infolists\Components\TextEntry::make('reference_number')->label('Referensi')->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
 
            Infolists\Components\Section::make('Delivery Orders')
                ->visible(fn($record) => $record->deliveries->isNotEmpty())
                ->schema([
                    Infolists\Components\RepeatableEntry::make('deliveries')->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('do_number')->label('No. DO'),
                            Infolists\Components\TextEntry::make('do_date')->label('Tanggal')->date('d/m/Y'),
                            Infolists\Components\TextEntry::make('status')->label('Status')->badge()
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
