<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
 
            Actions\Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->icon('heroicon-o-printer')
                ->url(fn() => route('transaction.invoice', $this->record))
                ->openUrlInNewTab(),
 
            Actions\Action::make('generate_do')
                ->label('Buat Delivery Order')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    app(\App\Services\DeliveryService::class)->createDelivery($this->record);
                    \Filament\Notifications\Notification::make()->success()->title('DO berhasil dibuat.')->send();
                })
                ->visible(fn() => $this->record->delivery_status === 'pending'),
        ];
    }
}
