<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use Filament\Schemas\Schema;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('do_number')
                            ->label('No. DO')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\DatePicker::make('do_date')
                            ->label('Tanggal DO')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'   => 'Menunggu',
                                'partial'   => 'Sebagian Terkirim',
                                'completed' => 'Selesai',
                            ])
                            ->disabled(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
