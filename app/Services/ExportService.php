<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export collection ke CSV dengan BOM UTF-8 (Excel-compatible)
     *
     * @param  Collection  $records
     * @param  string      $filename
     * @param  array       $headers   ['Label Kolom', ...]
     * @param  callable    $rowMapper fn($record) => ['col1', 'col2', ...]
     * @param  array|null  $totals    Baris total opsional
     */
    public static function toCsv(
        Collection $records,
        string     $filename,
        array      $headers,
        callable   $rowMapper,
        ?array     $totals = null
    ): StreamedResponse {
        return response()->streamDownload(function () use ($records, $headers, $rowMapper, $totals) {
            $handle = fopen('php://output', 'w');

            // BOM agar Excel bisa baca UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($handle, $headers);

            // Rows
            foreach ($records as $record) {
                fputcsv($handle, $rowMapper($record));
            }

            // Total row
            if ($totals) {
                fputcsv($handle, []);
                fputcsv($handle, $totals);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Preset exporters per resource ───────────────────────

    public static function transactions(Collection $records): StreamedResponse
    {
        return static::toCsv(
            $records,
            'transaksi-' . now()->format('Y-m-d') . '.csv',
            ['No. Invoice', 'Tanggal', 'Customer', 'Subtotal', 'Diskon', 'Grand Total', 'Dibayar', 'Sisa', 'Status Bayar', 'Status Kirim'],
            fn($r) => [
                $r->invoice_number,
                $r->transaction_date->format('d/m/Y'),
                $r->customer?->name ?? '—',
                $r->subtotal,
                $r->discount_amount,
                $r->grand_total,
                $r->amount_paid,
                $r->amount_remaining,
                match($r->payment_status) { 'unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', default => $r->payment_status },
                match($r->delivery_status) { 'pending' => 'Menunggu', 'partial' => 'Sebagian', 'delivered' => 'Terkirim', default => $r->delivery_status },
            ],
            ['TOTAL', '', '', $records->sum('subtotal'), $records->sum('discount_amount'), $records->sum('grand_total'), $records->sum('amount_paid'), $records->sum('amount_remaining'), '', '']
        );
    }

    public static function products(Collection $records): StreamedResponse
    {
        return static::toCsv(
            $records,
            'produk-' . now()->format('Y-m-d') . '.csv',
            ['SKU', 'Nama Produk', 'Kategori', 'Satuan', 'Harga Modal', 'Harga Jual', 'Stok Total', 'Min Stok', 'Status'],
            fn($r) => [
                $r->sku,
                $r->name,
                $r->category?->name ?? '—',
                $r->unit?->symbol ?? '—',
                $r->cost_price,
                $r->selling_price,
                $r->stock_quantity,
                $r->minimum_stock,
                $r->is_active ? 'Aktif' : 'Non-Aktif',
            ]
        );
    }

    public static function customers(Collection $records): StreamedResponse
    {
        return static::toCsv(
            $records,
            'customer-' . now()->format('Y-m-d') . '.csv',
            ['Nama', 'Tipe', 'No. HP', 'Alamat', 'Limit Kredit', 'Kredit Terpakai', 'Sisa Kredit', 'Status'],
            fn($r) => [
                $r->name,
                $r->type === 'do' ? 'DO / Tempo' : 'End User',
                $r->phone ?? '—',
                $r->address ?? '—',
                $r->credit_limit,
                $r->credit_used,
                $r->availableCredit(),
                $r->is_active ? 'Aktif' : 'Non-Aktif',
            ]
        );
    }

    public static function stockMovements(Collection $records): StreamedResponse
    {
        return static::toCsv(
            $records,
            'mutasi-stok-' . now()->format('Y-m-d') . '.csv',
            ['Waktu', 'Produk', 'SKU', 'Gudang', 'Tipe', 'Qty', 'Stok Sebelum', 'Stok Sesudah', 'Oleh', 'Keterangan'],
            fn($r) => [
                $r->moved_at->format('d/m/Y H:i'),
                $r->product?->name ?? '—',
                $r->product?->sku ?? '—',
                $r->warehouse?->name ?? '—',
                match($r->type) { 'in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Penyesuaian', default => $r->type },
                ($r->type === 'in' ? '+' : '-') . $r->quantity,
                $r->stock_before,
                $r->stock_after,
                $r->user?->name ?? '—',
                $r->notes ?? '—',
            ]
        );
    }
}