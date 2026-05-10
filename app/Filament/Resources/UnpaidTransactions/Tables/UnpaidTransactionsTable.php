<?php

namespace App\Filament\Resources\UnpaidTransactions\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnpaidTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager load dipindah ke sini agar tidak conflict dengan getEloquentQuery + filter
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->with(['customer', 'payments.paymentMethod'])
            )
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tgl Transaksi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(
                        fn($record) =>
                        $record->transaction_date->diffInDays(today()) . ' hari yang lalu'
                    ),

                TextColumn::make('invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn($record) => $record->customer?->phone ?? ''),

                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('amount_paid')
                    ->label('Dibayar')
                    ->money('IDR')
                    ->color('success'),

                TextColumn::make('amount_remaining')
                    ->label('Sisa Tagihan')
                    ->money('IDR')
                    ->color('danger')
                    ->weight('bold')
                    ->sortable(),

                // Progress bar pembayaran
                TextColumn::make('payment_progress')
                    ->label('Progress')
                    ->getStateUsing(
                        fn($record) => $record->grand_total > 0
                            ? round($record->amount_paid / $record->grand_total * 100, 1) . '%'
                            : '0%'
                    )
                    ->color(fn($record) => match (true) {
                        $record->amount_paid <= 0                             => 'danger',
                        $record->amount_paid < $record->grand_total * 0.5    => 'warning',
                        default                                               => 'success',
                    }),

                BadgeColumn::make('payment_status')
                    ->label('Status')
                    ->colors([
                        'danger'  => 'unpaid',
                        'warning' => 'partial',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'unpaid'  => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        default   => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label('Tipe Customer')
                    ->options(['do' => 'DO / Tempo', 'end_user' => 'End User'])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;

                        if (empty($value) || $value === '' || $value === null) {
                            return $query;
                        }

                        return $query->whereHas(
                            'customer',
                            fn(Builder $q) => $q->where('type', $value)
                        );
                    }),

                // Filter status
                SelectFilter::make('payment_status')
                    ->label('Status')
                    ->options([
                        'unpaid'  => 'Belum Bayar',
                        'partial' => 'Sebagian',
                    ]),

                // Filter umur tagihan
                SelectFilter::make('due_age')
                    ->label('Umur Tagihan')
                    ->options([
                        '7'  => '> 7 hari',
                        '14' => '> 14 hari',
                        '30' => '> 30 hari',
                        '60' => '> 60 hari',
                    ])
                    // ->query(
                    //     fn($query, array $data) =>
                    //     $data['value']
                    //         ? $query->where('transaction_date', '<=', now()->subDays((int)$data['value']))
                    //         : $query
                    // ),
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where(
                            'transaction_date',
                            '<=',
                            now()->subDays((int) $data['value'])
                        );
                    }),

                // Filter tanggal transaksi
                Filter::make('transaction_date')
                    ->label('Tgl Transaksi')
                    ->schema([
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
                // ── TAMBAH PEMBAYARAN — aksi utama ─────────────────── 
                // ── Tambah Pembayaran ────────────────────────────
                    Action::make('add_payment')
                        ->label('Tambah Pembayaran')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn(Transaction $record) => in_array($record->payment_status, ['unpaid', 'partial']))
                        ->schema(fn(Transaction $record) => self::paymentForm($record))
                        ->action(fn(Transaction $record, array $data) => self::handleAddPayment($record, $data)),

                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('go_to_transaction')
                        ->label('Lihat Transaksi Lengkap')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Transaction $record) => TransactionResource::getUrl('view', ['record' => $record]))
                        ->openUrlInNewTab(),
                ]),
            ])

            ->defaultSort('transaction_date', 'asc') // terlama di atas
            ->striped()
            ->poll('60s')
            ->emptyStateHeading('Semua transaksi sudah lunas!')
            ->emptyStateDescription('Tidak ada tagihan yang perlu ditagih.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================
    // PAYMENT FORM
    // =========================================================

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
