<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')
                        ->label('Nama Supplier')
                        ->required(),

                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),

                    TextInput::make('contact_person')
                        ->label('Contact Person'),

                    TextInput::make('phone')
                        ->label('No. Telepon')
                        ->tel(),

                    TextInput::make('email')
                        ->label('Email')
                        ->email(),

                    Textarea::make('address')
                        ->label('Alamat')
                        ->rows(3)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
