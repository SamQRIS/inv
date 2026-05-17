<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Transaction;
use App\Services\DepositService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Jika admin klik "Lunasi Sekarang" dari notifikasi → langsung tampil modal konfirmasi
        if (request()->query('apply_deposit') === '1') {
            $this->mountAction('apply_deposit_pending');
        }
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $isDo   = $record->type === 'do';

        $pendingCount = $isDo
            ? Transaction::where('customer_id', $record->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNull('deleted_at')
            ->count()
            : 0;

        $pendingTotal = $isDo
            ? Transaction::where('customer_id', $record->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNull('deleted_at')
            ->sum('amount_remaining')
            : 0;

        return [
            // ── TOP UP DEPOSIT ────────────────────────────────────────
            Action::make('topup_deposit')
                ->label('Top Up Deposit')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible($isDo)
                ->form([
                    TextInput::make('amount')
                        ->label('Jumlah Top Up')
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(1)
                        ->required()
                        ->helperText('Saldo deposit saat ini: Rp ' . number_format($record->deposit_balance, 0, ',', '.')),

                    Select::make('payment_method_id')
                        ->label('Metode Pembayaran')
                        ->options(\App\Models\PaymentMethod::where('is_active', true)->where('is_deposit', false)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('reference_number')
                        ->label('No. Referensi / Transfer')
                        ->placeholder('Contoh: TRF-20260516-001')
                        ->nullable(),

                    Textarea::make('notes')
                        ->label('Keterangan')
                        ->rows(2)
                        ->nullable(),
                ])
                ->action(function (array $data) use ($record) {
                    app(DepositService::class)->topup(
                        customer: $record,
                        amount: (float) $data['amount'],
                        paymentMethodId: (int) $data['payment_method_id'],
                        referenceNumber: $data['reference_number'] ?? null,
                        notes: $data['notes'] ?? null,
                    );
                    // Notifikasi + cek pending dikirim dari dalam DepositService::topup()
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->modalHeading('Top Up Deposit — ' . $record->name)
                ->modalSubmitActionLabel('Simpan Top Up')
                ->modalWidth('lg'),

            // ── LUNASI DARI DEPOSIT (muncul jika ada transaksi pending) ──
            Action::make('apply_deposit_pending')
                ->label('Lunasi dari Deposit (' . $pendingCount . ')')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible($isDo && $pendingCount > 0 && $record->deposit_balance > 0)
                ->requiresConfirmation()
                ->modalHeading('Lunasi Transaksi Pending dari Deposit')
                ->modalDescription(
                    'Ada ' . $pendingCount . ' transaksi pending dengan total tagihan Rp ' .
                        number_format($pendingTotal, 0, ',', '.') . '. ' .
                        'Saldo deposit saat ini: Rp ' . number_format($record->deposit_balance, 0, ',', '.') . '. ' .
                        'Deposit akan dipakai untuk melunasi transaksi secara FIFO (terlama duluan). Lanjutkan?'
                )
                ->modalSubmitActionLabel('Ya, Lunasi Sekarang')
                ->action(function () use ($record) {
                    $results = app(DepositService::class)->applyDepositToPending($record);

                    $lunasiCount  = count($results['lunasi']);
                    $sebagianCount = count($results['sebagian']);
                    $skipCount    = count($results['skip']);

                    $body = '';
                    if ($lunasiCount > 0)   $body .= "✅ Lunas: " . implode(', ', $results['lunasi']) . ". ";
                    if ($sebagianCount > 0) $body .= "⚠ Sebagian: " . implode(', ', $results['sebagian']) . ". ";
                    if ($skipCount > 0)     $body .= "⏭ Skip (deposit habis): " . implode(', ', $results['skip']) . ".";

                    Notification::make()
                        ->success()
                        ->title('Deposit Berhasil Diaplikasikan')
                        ->body(trim($body) . ' Sisa deposit: Rp ' . number_format($results['deposit_after'], 0, ',', '.'))
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // ── KOREKSI DEPOSIT ───────────────────────────────────────
            Action::make('deduct_deposit')
                ->label('Koreksi Deposit')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->visible($isDo && $record->deposit_balance > 0)
                ->form([
                    TextInput::make('amount')
                        ->label('Jumlah Pengurangan')
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(1)
                        ->maxValue($record->deposit_balance)
                        ->required()
                        ->helperText('Maksimal: Rp ' . number_format($record->deposit_balance, 0, ',', '.')),

                    Textarea::make('notes')
                        ->label('Alasan Koreksi')
                        ->rows(2)
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    app(DepositService::class)->manualDeduct(
                        customer: $record,
                        amount: (float) $data['amount'],
                        notes: $data['notes'],
                    );
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->modalHeading('Koreksi / Kurangi Deposit')
                ->modalSubmitActionLabel('Simpan Koreksi')
                ->modalWidth('lg'),

            EditAction::make(),
        ];
    }
}
