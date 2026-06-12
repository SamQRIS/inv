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

    public function mount(): void
    {
        parent::mount();

        $soId = request('from_so');
        if (!$soId) return;

        $so = \App\Models\ProductionOrder::with([
            'customer',
            'items.product.unit',
            'items.size',
            'items.fabric',
            'items.color',
        ])->find($soId);

        if (!$so) return;

        // Pre-fill form dari data SO
        $this->form->fill([
            'customer_id'      => $so->customer_id,
            'transaction_date' => today()->toDateString(),
            'notes'            => 'Dari Surat Pesanan: ' . $so->order_number,

            // Items dari SO → otomatis masuk repeater
            'items' => $so->items->map(function ($item) {
                $product   = $item->product;
                $sizeName   = $item->size?->name;
                $fabricName = $item->fabric?->name;
                $colorName  = $item->color?->name;

                // Build nama produk lengkap
                $productName = trim(implode(' ', array_filter([
                    $product?->name ?? $item->product_name,
                    $sizeName,
                    $fabricName,
                    $colorName,
                ])));

                // Cari harga dari product_prices
                $price = null;
                if ($product && $item->size_id) {
                    $price = \App\Models\ProductPrice::findPrice(
                        $product->id,
                        $item->size_id,
                        $item->fabric_id
                    );
                }
                if (!$price && $product) {
                    $price = $product->selling_price ?: null;
                }

                return [
                    'product_id'   => $item->product_id,
                    'size_id'      => $item->size_id,
                    'fabric_id'    => $item->fabric_id,
                    'color_id'     => $item->color_id,
                    'product_name' => $productName,
                    'unit_name'    => $product?->unit?->symbol ?? 'PCS',
                    'quantity'     => $item->quantity,
                    'unit_price'   => $price,   // bisa null → admin isi manual
                    'line_subtotal' => $price ? $item->quantity * $price : 0,
                    'notes'        => $item->item_notes,
                ];
            })->toArray(),
        ]);
    }

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
            'invoice_number'   => $data['invoice_number'],
            'customer_type'    => $customerType,
            'customer_id'      => $data['customer_id'] ?? null,
            'customer_name'    => $data['end_user_name'] ?? null,
            'customer_phone'   => $data['end_user_phone'] ?? null,
            'customer_address' => $data['end_user_address'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'delivery_date'    => $data['delivery_date'] ?? null,
            'delivery_note'    => $data['delivery_note'] ?? null,
            'items'            => $data['items'] ?? [],
            'discount_json'    => $data['discount_json'] ?? null,
            'payments'         => $data['payments'] ?? [],
            'notes'            => $data['notes'] ?? null,
            'admin_override'   => (bool) ($data['admin_override'] ?? false), // ← tambah ini
        ];

        return $service->create($transactionData);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
