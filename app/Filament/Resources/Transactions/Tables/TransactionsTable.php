<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\DeliveryService;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['customer', 'payments']))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. Invoice')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('transaction_date')
                    ->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')->searchable()->placeholder('—')->limit(25),
                TextColumn::make('grand_total')
                    ->label('Grand Total')->money('IDR')->sortable(),
                TextColumn::make('amount_remaining')
                    ->label('Sisa')->money('IDR')
                    ->color(fn($record) => $record->amount_remaining > 0 ? 'danger' : 'success')
                    ->placeholder('—'),
                BadgeColumn::make('payment_status')
                    ->label('Bayar')
                    ->colors(['danger' => 'unpaid', 'warning' => 'partial', 'success' => 'paid'])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid' => 'Lunas',
                        default => $state,
                    }),
                BadgeColumn::make('delivery_status')
                    ->label('Kirim')
                    ->colors(['secondary' => 'pending', 'warning' => 'partial', 'success' => 'delivered'])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Menunggu',
                        'partial' => 'Sebagian',
                        'delivered' => 'Terkirim',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Status Bayar')
                    ->options(['unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas']),
                SelectFilter::make('delivery_status')
                    ->label('Status Kirim')
                    ->options(['pending' => 'Menunggu', 'partial' => 'Sebagian', 'delivered' => 'Terkirim']),
                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['from'],  fn($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('transaction_date', '<=', $data['until']))
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // ── Tambah Pembayaran ────────────────────────────
                    Action::make('add_payment')
                        ->label('Tambah Pembayaran')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn(Transaction $record) => in_array($record->payment_status, ['unpaid', 'partial']))
                        ->form(fn(Transaction $record) => self::paymentForm($record))
                        ->action(fn(Transaction $record, array $data) => self::handleAddPayment($record, $data)),

                    Action::make('print_invoice')
                        ->label('Cetak Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->url(fn(Transaction $record) => route('transaction.invoice', $record))
                        ->openUrlInNewTab(),
                    Action::make('generate_do')
                        ->label('Buat Delivery Order')
                        ->icon('heroicon-o-truck')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Transaction $record) {
                            app(DeliveryService::class)->createDelivery($record);
                            Notification::make()->success()->title('DO berhasil dibuat.')->send();
                        })
                        ->visible(fn(Transaction $record) => $record->delivery_status === 'pending'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->striped();
    }

    public static function paymentForm(Transaction $record): array
    {
        return [
            Placeholder::make('info_tagihan')
                ->label('Informasi Tagihan')
                ->content(
                    'Grand Total: Rp ' . number_format($record->grand_total, 0, ',', '.') .
                        ' | Dibayar: Rp '  . number_format($record->amount_paid, 0, ',', '.') .
                        ' | Sisa: Rp '     . number_format($record->amount_remaining, 0, ',', '.')
                ),

            Select::make('payment_method_id')
                ->label('Metode Pembayaran')
                ->options(PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                ->searchable()->required()->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) use ($record) {
                    $method = PaymentMethod::find($state);
                    $set('is_installment', (bool) $method?->is_installment);
                    if (!$method?->is_installment) {
                        $set('amount', $record->amount_remaining);
                    }
                }),

            TextInput::make('amount')
                ->label('Jumlah Pembayaran')
                ->numeric()->prefix('Rp')->required()->minValue(1)
                ->default($record->amount_remaining)
                ->helperText('Maks: Rp ' . number_format($record->amount_remaining, 0, ',', '.')),

            DatePicker::make('payment_date')
                ->label('Tanggal Pembayaran')
                ->default(today())->native(false)->displayFormat('d/m/Y')->required(),

            TextInput::make('reference_number')
                ->label('No. Referensi')
                ->placeholder('No. transfer, kode QRIS, no. kwitansi, dll')->nullable(),

            Hidden::make('is_installment')->default(false),

            Fieldset::make('Detail Cicilan Pihak Ketiga')
                ->visible(fn(Get $get) => (bool) $get('is_installment'))
                ->columns(2)
                ->schema([
                    TextInput::make('installment_detail.provider')
                        ->label('Provider')->placeholder('Akulaku, Home Credit, dll'),
                    TextInput::make('installment_detail.tenor')
                        ->label('Tenor (bulan)')->numeric()->minValue(1),
                    TextInput::make('installment_detail.contract_number')
                        ->label('No. Kontrak'),
                    TextInput::make('installment_detail.monthly_amount')
                        ->label('Cicilan / Bulan')->numeric()->prefix('Rp'),
                ]),
        ];
    }

    public static function handleAddPayment(Transaction $record, array $data): void
    {
        $installmentDetail = null;
        if (!empty($data['is_installment']) && !empty($data['installment_detail'])) {
            $detail = array_filter($data['installment_detail'], fn($v) => $v !== null && $v !== '');
            $installmentDetail = !empty($detail) ? $detail : null;
        }

        app(PaymentService::class)->addPayment($record, [
            'payment_method_id'  => $data['payment_method_id'],
            'amount'             => (float) $data['amount'],
            'payment_date'       => $data['payment_date'],
            'reference_number'   => $data['reference_number'] ?? null,
            'installment_detail' => $installmentDetail,
        ]);

        Notification::make()
            ->success()
            ->title('Pembayaran berhasil ditambahkan')
            ->body('Rp ' . number_format($data['amount'], 0, ',', '.'))
            ->send();
    }
}
