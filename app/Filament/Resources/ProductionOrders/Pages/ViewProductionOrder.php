<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionOrder extends ViewRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),

            // ── CETAK SURAT PESANAN ───────────────────────────────
            \Filament\Actions\Action::make('cetak_surat_pesanan')
                ->label('Cetak Surat Pesanan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('production-order.print', $this->record))
                ->openUrlInNewTab(),

            // ── BUAT TRANSAKSI DARI SO ────────────────────────────
            \Filament\Actions\Action::make('buat_transaksi')
                ->label('Buat Transaksi')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->visible(fn() => $this->record->status !== 'done')
                ->requiresConfirmation()
                ->modalHeading('Buat Transaksi dari Surat Pesanan')
                ->modalDescription('Item dari surat pesanan ini akan otomatis masuk ke form transaksi. Harga perlu diisi manual.')
                ->modalSubmitActionLabel('Lanjut ke Form Transaksi')
                ->action(function () {
                    // Update status SO jadi done
                    $this->record->update(['status' => 'done']);

                    // Redirect ke create transaksi dengan parameter SO
                    $this->redirect(
                        route('filament.admin.resources.transactions.create', [
                            'from_so' => $this->record->id,
                        ])
                    );
                }),
        ];
    }
}
