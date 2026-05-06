<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')
                        ->label('Nama Metode')
                        ->required()
                        ->placeholder('Contoh: Transfer BCA, QRIS, Akulaku'),

                    TextInput::make('code')
                        ->label('Kode Unik')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->placeholder('Contoh: transfer_bca, qris, akulaku')
                        ->helperText('Huruf kecil, tanpa spasi, gunakan underscore'),

                    TextInput::make('provider')
                        ->label('Provider / Pihak Ketiga')
                        ->placeholder('Contoh: Akulaku, Home Credit')
                        ->nullable()
                        ->helperText('Isi jika ini merupakan cicilan pihak ketiga'),

                    TextInput::make('sort_order')
                        ->label('Urutan Tampil')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_installment')
                        ->label('Cicilan Pihak Ketiga?')
                        ->helperText('Jika aktif, transaksi dengan metode ini otomatis dianggap LUNAS')
                        ->live()
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
