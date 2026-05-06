<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Gudang')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Gudang / Lokasi')
                            ->placeholder('Contoh: Gudang Utama, Toko Depok, Cabang Bekasi')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('code')
                            ->label('Kode Unik')
                            ->placeholder('Contoh: GDG-01, TOKO-DPK')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->helperText('Huruf kapital + tanda hubung, max 20 karakter'),

                        TextInput::make('pic')
                            ->label('Penanggung Jawab (PIC)')
                            ->placeholder('Nama pengelola gudang')
                            ->nullable(),

                        TextInput::make('phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->nullable(),

                        Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->helperText('Angka kecil tampil lebih awal'),

                        Toggle::make('is_default')
                            ->label('Jadikan Gudang Default')
                            ->helperText('Gudang ini akan dipilih otomatis saat transaksi baru')
                            ->live(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
