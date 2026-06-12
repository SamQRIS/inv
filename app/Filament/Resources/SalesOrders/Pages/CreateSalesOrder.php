<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Services\SalesOrderService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected static ?string $title = 'Buat Sales Order';

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(SalesOrderService::class);

        return $service->create([
            'customer_id'             => $data['customer_id'],
            'order_date'              => $data['order_date'],
            'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
            'items'                   => $data['items'] ?? [],
            'discount_json'           => $data['discount_json'] ?? null,
            'notes'                   => $data['notes'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
