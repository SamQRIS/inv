<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\PaymentMethod;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
               Section::make('Informasi Transaksi')
                ->schema([
                    Placeholder::make('invoice_number')
                        ->label('No. Invoice')
                        ->content(fn($record) => $record?->transaction?->invoice_number ?? '—'),
 
                    Placeholder::make('customer_name')
                        ->label('Customer')
                        ->content(fn($record) => $record?->transaction?->customer?->name ?? '—'),
 
                    Placeholder::make('grand_total')
                        ->label('Grand Total Transaksi')
                        ->content(fn($record) => $record
                            ? 'Rp ' . number_format($record->transaction->grand_total, 0, ',', '.')
                            : '—'
                        ),
                ])
                ->columns(3),
 
            Section::make('Detail Pembayaran')
                ->schema([
                    Select::make('payment_method_id')
                        ->label('Metode Pembayaran')
                        ->options(PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $method = PaymentMethod::find($state);
                            $set('is_installment_flag', (bool) $method?->is_installment);
                        }),
 
                    TextInput::make('amount')
                        ->label('Jumlah')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(1),
 
                    DatePicker::make('payment_date')
                        ->label('Tanggal Pembayaran')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
 
                    TextInput::make('reference_number')
                        ->label('No. Referensi')
                        ->placeholder('No. transfer, kode QRIS, dll')
                        ->nullable(),
 
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            ]);
    }
}
