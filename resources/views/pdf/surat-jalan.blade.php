<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    /*
     * Kertas Continuous 1/2 = 9.5" x 5.5"  (241mm x 140mm)
     * DomPDF: set paper custom size di DocumentController
     *   ->setPaper([0, 0, 680.31, 396.85], 'landscape')
     *   (1mm = 2.8346 pt → 241 x 2.8346 = 683pt, 140 x 2.8346 = 397pt)
     */

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, sans-serif;
        font-size: 8.5px;
        color: #000;
        background: #fff;
    }

    .page {
        width: 100%;
        padding: 5mm 6mm 4mm 6mm;
    }

    /* ── JUDUL ─────────────────────────────────────────── */
    .title-row {
        text-align: center;
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 2px;
        border-bottom: 1.5px solid #000;
        padding-bottom: 2px;
        margin-bottom: 4px;
    }

    /* ── INFO HEADER (2 kolom) ─────────────────────────── */
    .info-wrap {
        display: table;
        width: 100%;
        border-bottom: 1px dashed #999;
        padding-bottom: 3px;
        margin-bottom: 3px;
    }

    .info-left, .info-right {
        display: table-cell;
        width: 50%;
        vertical-align: top;
    }

    .info-right {
        padding-left: 6px;
        border-left: 1px dashed #bbb;
    }

    .info-row {
        display: table;
        width: 100%;
        margin-bottom: 1px;
    }

    .info-label {
        display: table-cell;
        width: 72px;
        font-weight: bold;
        font-size: 7.5px;
        vertical-align: top;
        padding-right: 2px;
        white-space: nowrap;
    }

    .info-sep {
        display: table-cell;
        width: 8px;
        font-size: 7.5px;
        vertical-align: top;
    }

    .info-value {
        display: table-cell;
        font-size: 7.5px;
        vertical-align: top;
    }

    /* ── TABEL ITEM ────────────────────────────────────── */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3px;
    }

    thead tr th {
        background: #222;
        color: #fff;
        font-size: 7.5px;
        font-weight: bold;
        padding: 2px 3px;
        border: 0.5px solid #444;
        text-align: center;
    }

    tbody tr td {
        font-size: 7.5px;
        padding: 2px 3px;
        border: 0.5px solid #ccc;
        vertical-align: top;
    }

    tbody tr:nth-child(even) td {
        background: #f5f5f5;
    }

    /* Baris kosong pengisi halaman */
    .empty-row td {
        height: 10px;
        background: #fff !important;
    }

    .text-center { text-align: center; }
    .text-right  { text-align: right; }
    .text-bold   { font-weight: bold; }

    /* ── FOOTER ────────────────────────────────────────── */
    .footer-wrap {
        display: table;
        width: 100%;
        border-top: 1px dashed #999;
        padding-top: 3px;
        margin-top: 2px;
    }

    /* Kolom kiri: tanda tangan */
    .footer-sign {
        display: table-cell;
        width: 58%;
        vertical-align: top;
    }

    .sign-grid {
        display: table;
        width: 100%;
    }

    .sign-col {
        display: table-cell;
        width: 33.33%;
        text-align: center;
        font-size: 7px;
        padding: 0 2px;
    }

    .sign-title {
        font-weight: bold;
        margin-bottom: 14px;
    }

    .sign-line {
        border-top: 0.7px solid #000;
        padding-top: 2px;
        font-size: 7px;
        color: #444;
    }

    /* Kolom kanan: tagihan + catatan */
    .footer-right {
        display: table-cell;
        width: 42%;
        vertical-align: top;
        padding-left: 6px;
        border-left: 1px dashed #bbb;
    }

    .billing-row {
        display: table;
        width: 100%;
        margin-bottom: 1px;
    }

    .billing-label {
        display: table-cell;
        width: 72px;
        font-size: 7.5px;
        font-weight: bold;
    }

    .billing-sep {
        display: table-cell;
        width: 8px;
        font-size: 7.5px;
    }

    .billing-value {
        display: table-cell;
        font-size: 7.5px;
        font-weight: bold;
    }

    .billing-value.danger  { color: #c00; }
    .billing-value.success { color: #0a0; }

    .catatan-box {
        border: 0.5px dashed #999;
        padding: 2px 4px;
        margin-top: 3px;
        min-height: 14px;
        font-size: 7px;
    }

    /* ── BOTTOM BAR ────────────────────────────────────── */
    .bottom-bar {
        border-top: 1px solid #000;
        margin-top: 3px;
        padding-top: 2px;
        display: table;
        width: 100%;
    }

    .bottom-left {
        display: table-cell;
        font-size: 6.5px;
        color: #555;
        vertical-align: bottom;
    }

    .bottom-right {
        display: table-cell;
        font-size: 7px;
        text-align: right;
        font-weight: bold;
        vertical-align: bottom;
    }

</style>
</head>
<body>
<div class="page">

    {{-- ── JUDUL ─────────────────────────────────────────── --}}
    <div class="title-row">SURAT JALAN</div>

    {{-- ── INFO HEADER ────────────────────────────────────── --}}
    <div class="info-wrap">

        {{-- Kolom Kiri --}}
        <div class="info-left">
            <div class="info-row">
                <span class="info-label">No. Surat Jalan</span>
                <span class="info-sep">:</span>
                <span class="info-value text-bold">
                    {{ $delivery->do_number }} /
                    {{ \Carbon\Carbon::parse($delivery->do_date)->translatedFormat('l, d F Y') }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Kepada</span>
                <span class="info-sep">:</span>
                <span class="info-value text-bold">
                    {{ $delivery->transaction->customer?->name ?? '—' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Telepon</span>
                <span class="info-sep">:</span>
                <span class="info-value">
                    {{ $delivery->transaction->customer?->phone ?? '—' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Alamat</span>
                <span class="info-sep">:</span>
                <span class="info-value">
                    {{ $delivery->transaction->customer?->address ?? '—' }}
                </span>
            </div>
        </div>

        {{-- Kolom Kanan --}}
        <div class="info-right">
            <div class="info-row">
                <span class="info-label">Kirim</span>
                <span class="info-sep">:</span>
                <span class="info-value">
                    {{ \Carbon\Carbon::parse($delivery->do_date)->translatedFormat('l, d F Y') }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Referensi Nota</span>
                <span class="info-sep">:</span>
                <span class="info-value text-bold">
                    {{ $delivery->transaction->invoice_number }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Sales</span>
                <span class="info-sep">:</span>
                <span class="info-value">{{ $delivery->user->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Transaksi</span>
                <span class="info-sep">:</span>
                <span class="info-value">
                    {{ $delivery->transaction->transaction_date->format('d/m/Y') }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Outlet</span>
                <span class="info-sep">:</span>
                <span class="info-value text-bold">
                    {{ $delivery->transaction->customer?->name ?? '—' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Keterangan</span>
                <span class="info-sep">:</span>
                <span class="info-value">
                    {{ $delivery->transaction->delivery_note ?? '—' }}
                </span>
            </div>
        </div>
    </div>

    {{-- ── TABEL ITEM ──────────────────────────────────────── --}}
    <table>
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:38%;text-align:left">Nama Barang</th>
                <th style="width:8%">Qty</th>
                <th style="width:8%">Satuan</th>
                {{-- SARAN: tambah harga agar jadi bukti tagihan sekaligus --}}
                <th style="width:14%;text-align:right">Harga</th>
                <th style="width:14%;text-align:right">Subtotal</th>
                <th style="width:14%;text-align:left">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php
                $maxRows    = 8;   // jumlah baris sesuai tinggi kertas continuous 1/2
                $itemCount  = count($delivery->items);
            @endphp

            @foreach($delivery->items as $i => $item)
            @php
                // Ambil harga dari transaction item
                $txItem    = $item->transactionItem;
                $unitPrice = $txItem?->unit_price ?? 0;
                $subtotal  = $unitPrice * $item->qty_delivered;
            @endphp
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-center text-bold">{{ $item->qty_delivered }}</td>
                <td class="text-center">{{ $item->product->unit->symbol }}</td>
                <td class="text-right">{{ number_format($unitPrice, 0, ',', '.') }}</td>
                <td class="text-right text-bold">{{ number_format($subtotal, 0, ',', '.') }}</td>
                <td>
                    @if($item->qtyRemaining() > 0)
                        <span style="color:#c00">Sisa: {{ $item->qtyRemaining() }}</span>
                    @endif
                </td>
            </tr>
            @endforeach

            {{-- Baris kosong pengisi agar tampilan rapi --}}
            @for($r = $itemCount; $r < $maxRows; $r++)
            <tr class="empty-row">
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
            @endfor
        </tbody>
    </table>

    {{-- ── FOOTER ──────────────────────────────────────────── --}}
    <div class="footer-wrap">

        {{-- Tanda Tangan --}}
        <div class="footer-sign">
            <div class="sign-grid">
                <div class="sign-col">
                    <div class="sign-title">Mengetahui</div>
                    <div class="sign-line">{{ $delivery->user->name }}</div>
                </div>
                <div class="sign-col">
                    <div class="sign-title">Driver</div>
                    <div class="sign-line">________________</div>
                </div>
                <div class="sign-col">
                    <div class="sign-title">Penerima</div>
                    <div class="sign-line">________________</div>
                </div>
            </div>
        </div>

        {{-- Tagihan & Catatan --}}
        <div class="footer-right">
            @php
                $trx       = $delivery->transaction;
                $grandTotal = $trx->grand_total;
                $amtPaid    = $trx->amount_paid;
                $amtRemain  = $trx->amount_remaining;
            @endphp

            <div class="billing-row">
                <span class="billing-label">Tagihan</span>
                <span class="billing-sep">:</span>
                <span class="billing-value">
                    Rp {{ number_format($grandTotal, 0, ',', '.') }}
                </span>
            </div>
            <div class="billing-row">
                <span class="billing-label">DP / Lunas</span>
                <span class="billing-sep">:</span>
                <span class="billing-value success">
                    Rp {{ number_format($amtPaid, 0, ',', '.') }}
                </span>
            </div>
            <div class="billing-row">
                <span class="billing-label">Sisa Tagihan</span>
                <span class="billing-sep">:</span>
                <span class="billing-value {{ $amtRemain > 0 ? 'danger' : 'success' }}">
                    Rp {{ number_format($amtRemain, 0, ',', '.') }}
                </span>
            </div>

            <div class="catatan-box">
                <strong style="font-size:6.5px">Catatan:</strong>
                {{ $delivery->notes ?? $trx->notes ?? '' }}
            </div>
        </div>

    </div>

    {{-- ── BOTTOM BAR ──────────────────────────────────────── --}}
    <div class="bottom-bar">
        <div class="bottom-left">
            Kritik &amp; saran pelayanan pengiriman hubungi kami di WhatsApp 0813 3181 5189
        </div>
        <!-- <div class="bottom-right">
            Halaman 1 dari 1
        </div> -->
    </div>

</div>
</body>
</html>