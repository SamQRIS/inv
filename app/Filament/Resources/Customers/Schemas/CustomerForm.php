<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tipe Customer')
                ->schema([
                    Radio::make('type')
                        ->label('')
                        ->options([
                            'do'       => 'DO / Tempo — dibuat manual, bisa kredit',
                            'end_user' => 'End User — input langsung saat transaksi',
                        ])
                        ->default('do')
                        ->required()
                        ->live()
                        ->inline(),
                ]),
 
            Section::make('Data Customer')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')->required()->maxLength(255),
 
                    TextInput::make('phone')
                        ->label('No. HP')->tel()->nullable(),
 
                    Textarea::make('address')
                        ->label('Alamat')->rows(3)->columnSpanFull(),
                ]),
 
            Section::make('Kredit & Diskon Default')
                ->description('Hanya berlaku untuk customer DO / Tempo')
                ->visible(fn(Get $get) => $get('type') === 'do')
                ->columns(2)
                ->schema([
                    TextInput::make('credit_limit')
                        ->label('Limit Kredit')
                        ->numeric()->prefix('Rp')->default(0)->minValue(0)
                        ->helperText('Gunakan tombol Top Up / Kurangi di halaman detail untuk mengubah limit setelah dibuat.'),
 
                    Placeholder::make('credit_used_display')
                        ->label('Kredit Terpakai')
                        ->content(fn($record) => $record
                            ? 'Rp ' . number_format($record->credit_used, 0, ',', '.')
                            : 'Rp 0'
                        ),
 
                    Repeater::make('default_discount')
                        ->label('Diskon Default (opsional)')
                        ->schema([
                            Select::make('type')
                                ->label('Tipe')
                                ->options(['percent' => 'Persentase (%)', 'nominal' => 'Nominal (Rp)'])
                                ->required()->columnSpan(2),
                            TextInput::make('value')
                                ->label('Nilai')->numeric()->minValue(0)->required()->columnSpan(2),
                            Placeholder::make('preview')
                                ->label('Preview')
                                ->content(function (Get $get): string {
                                    $type  = $get('type');
                                    $value = $get('value');
                                    if (!$type || !$value) return '—';
                                    return $type === 'percent'
                                        ? "{$value}%"
                                        : 'Rp ' . number_format($value, 0, ',', '.');
                                })
                                ->columnSpan(2),
                        ])
                        ->columns(6)->maxItems(5)
                        ->addActionLabel('+ Tambah Layer Diskon')
                        ->columnSpanFull()
                        ->helperText('Diskon ini otomatis terisi saat membuat transaksi untuk customer ini.'),
 
                    Toggle::make('is_active')
                        ->label('Aktif')->default(true)->columnSpanFull(),
                ]),
            ]);
    }
}
