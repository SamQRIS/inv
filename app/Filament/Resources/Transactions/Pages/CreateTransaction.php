<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\TransactionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected static ?string $title   = 'Transaksi Baru';
 
    protected function handleRecordCreation(array $data): Model
    {
        $service = app(TransactionService::class);
 
        // Resolve customer type
        $customerType = 'none';
        if (!empty($data['customer_id'])) {
            $customerType = 'existing';
        } elseif (!empty($data['end_user_name'])) {
            $customerType = 'end_user';
        }
 
        $transactionData = [
            'customer_type'    => $customerType,
            'customer_id'      => $data['customer_id'] ?? null,
            'customer_name'    => $data['end_user_name'] ?? null,
            'customer_phone'   => $data['end_user_phone'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'delivery_date'    => $data['delivery_date'] ?? null,
            'delivery_note'    => $data['delivery_note'] ?? null,
            'items'            => $data['items'] ?? [],
            'discount_json'    => $data['discount_json'] ?? null,
            'payments'         => $data['payments'] ?? [],
            'notes'            => $data['notes'] ?? null,
        ];
 
        return $service->create($transactionData);
    }
 
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

}
