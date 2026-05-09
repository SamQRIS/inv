<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
 
            Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->icon('heroicon-o-printer')
                ->url(fn() => route('transaction.invoice', $this->record))
                ->openUrlInNewTab(),
 
            Action::make('generate_do')
                ->label('Buat Delivery Order')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    app(DeliveryService::class)->createDelivery($this->record);
                    Notification::make()->success()->title('DO berhasil dibuat.')->send();
                })
                ->visible(fn() => $this->record->delivery_status === 'pending'),
        ];
    }
}
