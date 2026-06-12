<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Services\SalesOrderService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected static ?string $title = 'Detail Sales Order';

    // protected function getHeaderActions(): array
    // {
    //     $record = $this->getRecord();

    //     return [
    //         // Konfirmasi
    //         Action::make('confirm')
    //             ->label('Konfirmasi & Reserve Stok')
    //             ->icon('heroicon-o-check-circle')
    //             ->color('info')
    //             ->visible($record->canConfirmed())
    //             ->requiresConfirmation()
    //             ->modalHeading('Konfirmasi Sales Order')
    //             ->modalDescription('Stok akan di-reserve. Lanjutkan?')
    //             ->action(function () use ($record) {
    //                 try {
    //                     app(SalesOrderService::class)->confirm($record);
    //                     $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
    //                 } catch (\Exception $e) {
    //                     Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
    //                 }
    //             }),

    //         // Proses
    //         Action::make('process')
    //             ->label('Tandai Diproses')
    //             ->icon('heroicon-o-cog-6-tooth')
    //             ->color('warning')
    //             ->visible($record->canBeProcessed())
    //             ->requiresConfirmation()
    //             ->action(function () use ($record) {
    //                 try {
    //                     app(SalesOrderService::class)->markProcessing($record);
    //                     $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
    //                 } catch (\Exception $e) {
    //                     Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
    //                 }
    //             }),

    //         // Kirim & buat invoice
    //         Action::make('deliver')
    //             ->label('Kirim & Buat Invoice')
    //             ->icon('heroicon-o-truck')
    //             ->color('success')
    //             ->visible($record->canBeDelivered())
    //             ->form([
    //                 TextInput::make('delivery_note')
    //                     ->label('No. Surat Jalan / Keterangan')
    //                     ->placeholder('Contoh: SJ-20260517-001')
    //                     ->nullable(),
    //             ])
    //             ->modalHeading('Kirim Barang & Buat Invoice')
    //             ->modalDescription('Invoice otomatis dibuat dan deposit customer terpotong.')
    //             ->modalSubmitActionLabel('Ya, Kirim & Buat Invoice')
    //             ->action(function (array $data) use ($record) {
    //                 try {
    //                     $transaction = app(SalesOrderService::class)->deliver($record, $data['delivery_note'] ?? null);
    //                     $this->redirect(
    //                         route('filament.admin.resources.transactions.view', ['record' => $transaction->id])
    //                     );
    //                 } catch (\Exception $e) {
    //                     Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
    //                 }
    //             }),

    //         // Cancel
    //         Action::make('cancel')
    //             ->label('Batalkan')
    //             ->icon('heroicon-o-x-circle')
    //             ->color('danger')
    //             ->visible($record->canBeCancelled())
    //             ->form([
    //                 Textarea::make('reason')->label('Alasan Pembatalan')->required()->rows(2),
    //             ])
    //             ->modalHeading('Batalkan Sales Order')
    //             ->action(function (array $data) use ($record) {
    //                 try {
    //                     app(SalesOrderService::class)->cancel($record, $data['reason']);
    //                     $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
    //                 } catch (\Exception $e) {
    //                     Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
    //                 }
    //             }),

    //         EditAction::make()->visible($record->isDraft()),
    //     ];
    // }
}
