<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->width('40px'),
                TextColumn::make('name')->label('Metode')->searchable(),
                TextColumn::make('code')->label('Kode')->badge()->color('gray'),
                TextColumn::make('provider')->label('Provider')->placeholder('-'),
                IconColumn::make('is_installment')->label('Cicilan')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
