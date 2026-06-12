<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductionOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('order_date')
                    ->label('Tgl Pesan')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Pemesan')
                    ->searchable(),

                TextColumn::make('target_date')
                    ->label('Target Selesai')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(
                        fn($record) =>
                        $record->target_date && $record->target_date->isPast()
                            && $record->status !== 'done'
                            ? 'danger' : null
                    ),

                TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->alignCenter(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'confirmed',
                        'info'    => 'in_production',
                        'success' => 'done',
                    ])
                    ->formatStateUsing(fn($record) => $record->statusLabel()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('order_date', 'desc')
            ->emptyStateHeading('Belum ada surat pesanan')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
