<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->modifyQueryUsing(fn(Builder $q) => $q->with('user')->latest('logged_at'))
            ->columns([
                TextColumn::make('logged_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('System'),

                BadgeColumn::make('action')
                    ->label('Aksi')
                    ->colors([
                        'success' => fn($state) => in_array($state, ['created', 'payment']),
                        'warning' => 'updated',
                        'danger'  => 'deleted',
                        'info'    => 'restored',
                        'primary' => 'shipment',
                    ])
                    ->formatStateUsing(fn($record) => $record->actionLabel()),

                TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('model_label')
                    ->label('Data')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(60)
                    ->placeholder('—'),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'created'  => 'Dibuat',
                        'updated'  => 'Diubah',
                        'deleted'  => 'Dihapus',
                        'restored' => 'Dipulihkan',
                        'payment'  => 'Pembayaran',
                        'shipment' => 'Pengiriman',
                    ]),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('logged_at')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['from']))  $query->whereDate('logged_at', '>=', $data['from']);
                        if (!empty($data['until'])) $query->whereDate('logged_at', '<=', $data['until']);
                    }),
            ])
            ->defaultSort('logged_at', 'desc')
            ->striped()
            ->poll('30s')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
