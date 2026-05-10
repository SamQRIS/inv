<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->load(['items.product.unit', 'payments.paymentMethod']);

        // ✅ Load items dari relasi
        $data['items'] = $record->items->map(fn($item) => [
            'product_id'    => $item->product_id,
            'quantity'      => $item->quantity,
            'unit_price'    => $item->unit_price,
            'unit_name'     => $item->unit_name,
            'line_subtotal' => $item->subtotal,
            'notes'         => $item->notes,
        ])->toArray();

        // ✅ Load payments dari relasi
        $data['payments'] = $record->payments->map(fn($p) => [
            'payment_method_id'  => $p->payment_method_id,
            'amount'             => $p->amount,
            'payment_date'       => $p->payment_date?->toDateString(),
            'reference_number'   => $p->reference_number,
            'is_installment'     => $p->paymentMethod?->is_installment ?? false,
            'installment_detail' => $p->installment_detail,
        ])->toArray();

        // ✅ Set delivery_date_type
        $data['delivery_date_type'] = match (true) {
            !empty($data['delivery_date']) => 'date',
            !empty($data['delivery_note']) => 'text',
            default                        => 'none',
        };

        return $data;
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->icon('heroicon-o-printer')
                ->url(fn() => route('transaction.invoice', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
