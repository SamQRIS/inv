<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(3)->schema([
                    TextEntry::make('logged_at')
                        ->label('Waktu')->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('user.name')
                        ->label('User')->placeholder('System'),
                    TextEntry::make('action')
                        ->label('Aksi')->badge()
                        ->color(fn($record) => $record->actionColor())
                        ->formatStateUsing(fn($record) => $record->actionLabel()),
                    TextEntry::make('model_type')
                        ->label('Model')
                        ->formatStateUsing(fn($s) => class_basename($s)),
                    TextEntry::make('model_label')
                        ->label('Data')->placeholder('—'),
                    TextEntry::make('ip_address')
                        ->label('IP Address')->placeholder('—'),
                    TextEntry::make('description')
                        ->label('Deskripsi')->placeholder('—')->columnSpanFull(),
                ]),

                Section::make('Perubahan Data')
                    ->columns(2)
                    ->visible(fn($record) => $record->old_values || $record->new_values)
                    ->schema([
                        TextEntry::make('old_values')
                            ->label('Sebelum')
                            ->formatStateUsing(
                                fn($state) => $state
                                    ? collect($state)->map(fn($v, $k) => "{$k}: {$v}")->join("\n")
                                    : '—'
                            )
                            ->placeholder('—'),
                        TextEntry::make('new_values')
                            ->label('Sesudah')
                            ->formatStateUsing(
                                fn($state) => $state
                                    ? collect($state)->map(fn($v, $k) => "{$k}: {$v}")->join("\n")
                                    : '—'
                            )
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
