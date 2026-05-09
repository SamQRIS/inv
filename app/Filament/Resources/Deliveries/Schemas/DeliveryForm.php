<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('do_number')
                            ->label('No. DO')
                            ->disabled()
                            ->dehydrated(),

                        DatePicker::make('do_date')
                            ->label('Tanggal DO')
                            ->disabled(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'   => 'Menunggu',
                                'partial'   => 'Sebagian Terkirim',
                                'completed' => 'Selesai',
                            ])
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
