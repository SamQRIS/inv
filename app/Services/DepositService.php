<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositService
{
    public function topup(
        Customer $customer,
        float    $amount,
        int      $paymentMethodId,
        ?string  $referenceNumber = null,
        ?string  $notes           = null
    ): CreditLog {
        if ($customer->type !== 'do') {
            throw ValidationException::withMessages(['customer_id' => 'Top up deposit hanya berlaku untuk customer DO.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah top up harus lebih dari 0.']);
        }

        $log = DB::transaction(function () use ($customer, $amount, $paymentMethodId, $referenceNumber, $notes) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $before   = (float) $customer->deposit_balance;
            $after    = $before + $amount;
            $customer->update(['deposit_balance' => $after]);

            return CreditLog::create([
                'customer_id'       => $customer->id,
                'user_id'           => Auth::id(),
                'payment_method_id' => $paymentMethodId,
                'type'              => 'deposit_topup',
                'amount'            => $amount,
                'credit_before'     => $before,
                'credit_after'      => $after,
                'reference_type'    => 'manual',
                'reference_number'  => $referenceNumber,
                'notes'             => $notes ?? 'Top up deposit manual oleh admin.',
            ]);
        });

        // Setelah top up, cek transaksi pending dan kirim notifikasi konfirmasi
        $customer->refresh();
        $this->notifyPendingTransactions($customer);

        return $log;
    }

    /**
     * Cek transaksi pending dan kirim notifikasi ke admin dengan tombol konfirmasi.
     */
    public function notifyPendingTransactions(Customer $customer): void
    {
        $pending = Transaction::where('customer_id', $customer->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNull('deleted_at')
            ->orderBy('transaction_date')
            ->get();

        if ($pending->isEmpty()) {
            Notification::make()
                ->success()
                ->title('Top Up Deposit Berhasil')
                ->body('Saldo deposit ' . $customer->name . ': Rp ' . number_format($customer->deposit_balance, 0, ',', '.') . '. Tidak ada transaksi pending.')
                ->send();
            return;
        }

        $totalPending = $pending->sum('amount_remaining');
        $depositNow   = $customer->depositBalance();
        $jumlah       = $pending->count();
        $canCover     = $depositNow >= $totalPending;

        $invoiceList = $pending->take(3)
            ->map(fn($t) => $t->invoice_number . ' (Rp ' . number_format($t->amount_remaining, 0, ',', '.') . ')')
            ->join(', ');
        if ($jumlah > 3) $invoiceList .= ', +' . ($jumlah - 3) . ' lainnya';

        Notification::make()
            ->warning()
            ->title('Ada ' . $jumlah . ' Transaksi Pending — ' . $customer->name)
            ->body(
                'Total tagihan: Rp ' . number_format($totalPending, 0, ',', '.') .
                ' | Saldo deposit: Rp ' . number_format($depositNow, 0, ',', '.') .
                "\n" . ($canCover ? '✅ Deposit cukup untuk melunasi semua.' : '⚠ Deposit cukup sebagian.') .
                "\n" . $invoiceList
            )
            ->actions([
                Action::make('lunasi')
                    ->label('Lunasi Sekarang')
                    ->button()
                    ->color('success')
                    ->url(route('filament.admin.resources.customers.view', ['record' => $customer->id]) . '?apply_deposit=1'),
                Action::make('nanti')
                    ->label('Nanti Saja')
                    ->close(),
            ])
            ->persistent()
            ->send();
    }

    /**
     * Apply deposit ke semua transaksi pending, FIFO.
     * Return ringkasan hasilnya.
     */
    public function applyDepositToPending(Customer $customer): array
    {
        $results = ['lunasi' => [], 'sebagian' => [], 'skip' => [], 'deposit_after' => 0];

        $pending = Transaction::where('customer_id', $customer->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNull('deleted_at')
            ->orderBy('transaction_date')
            ->get();

        if ($pending->isEmpty()) {
            $results['deposit_after'] = $customer->depositBalance();
            return $results;
        }

        $depositMethod = PaymentMethod::where('code', 'deposit')->first();

        DB::transaction(function () use ($customer, $pending, $depositMethod, &$results) {
            foreach ($pending as $transaction) {
                $customer->refresh();
                $depositNow = $customer->depositBalance();

                if ($depositNow <= 0) {
                    $results['skip'][] = $transaction->invoice_number;
                    continue;
                }

                $remaining    = (float) $transaction->amount_remaining;
                $useDeposit   = min($depositNow, $remaining);
                $fullyCovered = $useDeposit >= $remaining;
                $before       = $customer->depositBalance();

                $customer->decrement('deposit_balance', $useDeposit);
                $customer->refresh();

                CreditLog::create([
                    'customer_id'    => $customer->id,
                    'user_id'        => Auth::id(),
                    'type'           => 'deposit_used',
                    'amount'         => $useDeposit,
                    'credit_before'  => $before,
                    'credit_after'   => $customer->depositBalance(),
                    'reference_type' => 'transaction',
                    'reference_id'   => $transaction->id,
                    'notes'          => "Deposit dipakai melunasi {$transaction->invoice_number} (konfirmasi admin).",
                ]);

                if ($depositMethod) {
                    Payment::create([
                        'transaction_id'    => $transaction->id,
                        'payment_method_id' => $depositMethod->id,
                        'amount'            => $useDeposit,
                        'payment_date'      => today()->toDateString(),
                        'notes'             => 'Dilunasi dari deposit — dikonfirmasi admin.',
                    ]);
                }

                $totalPaid  = (float) $transaction->amount_paid + $useDeposit;
                $grandTotal = (float) $transaction->grand_total;
                $transaction->update([
                    'amount_paid'      => min($totalPaid, $grandTotal),
                    'amount_remaining' => max(0, $grandTotal - $totalPaid),
                    'payment_status'   => $totalPaid >= $grandTotal ? 'paid' : 'partial',
                ]);

                $fullyCovered
                    ? $results['lunasi'][]   = $transaction->invoice_number
                    : $results['sebagian'][] = $transaction->invoice_number;
            }

            $customer->refresh();
            $results['deposit_after'] = $customer->depositBalance();
        });

        return $results;
    }

    public function manualDeduct(Customer $customer, float $amount, ?string $notes = null): CreditLog
    {
        if ($customer->type !== 'do') {
            throw ValidationException::withMessages(['customer_id' => 'Pengurangan deposit hanya berlaku untuk customer DO.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah pengurangan harus lebih dari 0.']);
        }

        return DB::transaction(function () use ($customer, $amount, $notes) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $before   = (float) $customer->deposit_balance;

            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pengurangan melebihi saldo deposit saat ini (Rp ' . number_format($before, 0, ',', '.') . ').',
                ]);
            }

            $after = $before - $amount;
            $customer->update(['deposit_balance' => $after]);

            return CreditLog::create([
                'customer_id'    => $customer->id,
                'user_id'        => Auth::id(),
                'type'           => 'deposit_manual_deduct',
                'amount'         => $amount,
                'credit_before'  => $before,
                'credit_after'   => $after,
                'reference_type' => 'manual',
                'notes'          => $notes ?? 'Pengurangan deposit manual oleh admin.',
            ]);
        });
    }

    public function validateSufficientDeposit(Customer $customer, float $grandTotal, bool $allowOverride = false): bool
    {
        if ($customer->type !== 'do') return true;
        $deposit = (float) $customer->deposit_balance;
        if ($deposit >= $grandTotal) return true;
        if ($allowOverride) return false;

        throw ValidationException::withMessages([
            'customer_id' => sprintf(
                'Deposit %s tidak mencukupi. Saldo deposit: Rp %s, Total order: Rp %s.',
                $customer->name,
                number_format($deposit, 0, ',', '.'),
                number_format($grandTotal, 0, ',', '.')
            ),
        ]);
    }
}