<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_surat_jalan')
                ->label('Cetak Surat Jalan')
                ->icon('heroicon-o-printer')
                ->url(fn() => route('delivery.surat-jalan', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
