<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        /*
     * Continuous 1/2 = 241mm x 140mm
     * DomPDF: setPaper([0, 0, 683, 397])
     * Layout: 100% table-based — paling stabil di DomPDF
     */
        @media print {
            @page {
                margin: 0;
                size: 241mm 140mm landscape;
            }

            body {
                margin: 0;
                padding: 0;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #000;
            background: #fff;
        }


        .title-row {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
            border-bottom: 1.5px solid #000;
            padding-bottom: 2px;
            margin-bottom: 3px;
            padding-top: 5px;
        }

        /* Info rows */
        .lbl {
            width: 68px;
            font-weight: bold;
            font-size: 7.5px;
            white-space: nowrap;
            vertical-align: top;
        }

        .sep {
            width: 8px;
            font-size: 7.5px;
            vertical-align: top;
        }

        .val {
            font-size: 8px;
            vertical-align: top;
        }

        .bold {
            font-weight: bold;
        }

        /* Item table */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
            border: 0.5px solid #ccc;
        }

        .item-table thead th {
            /* background: #222; color: #fff; */
            font-size: 8px;
            font-weight: bold;
            padding: 2px 3px;
            border: 0.5px solid #444;
            text-align: center;
        }

        .item-table tbody td {
            font-size: 9px;
            padding: 1.5px 4px;
            border: 0.5px solid #ccc;
            vertical-align: top;
        }

        .item-table tbody tr:nth-child(even) td {
            background: #f5f5f5;
        }

        .empty-row td {
            height: 9px;
            background: #fff !important;
        }

        .tc {
            text-align: center;
        }

        .tb {
            font-weight: bold;
        }

        /* Billing */
        .b-lbl {
            width: 68px;
            font-weight: bold;
            font-size: 7.5px;
            vertical-align: top;
        }

        .b-sep {
            width: 8px;
            font-size: 7.5px;
            vertical-align: top;
        }

        .b-val {
            font-size: 7.5px;
            font-weight: bold;
            vertical-align: top;
        }

        .danger {
            color: #c00;
        }

        .success {
            color: #080;
        }

        .catatan-box {
            border: 0.5px dashed #999;
            padding: 2px 4px;
            margin-top: 3px;
            font-size: 7px;
            min-height: 14px;
        }

        .bottom-bar {
            border-top: 1px solid #000;
            margin-top: 3px;
            padding-top: 2px;
            font-size: 6.5px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="page">

        {{-- JUDUL --}}
        <div class="title-row">SURAT JALAN</div>

        @php
        $trx = $delivery->transaction;
        $status = $trx->payment_status;
        $grandTotal = $trx->grand_total;
        $amtPaid = $trx->amount_paid;
        $amtRemain = $trx->amount_remaining;
        $discount = (float) ($trx->discount_amount ?? 0);
        $discJson = $trx->discount_json ?? [];

        $discNote = '';
        if ($discount > 0 && !empty($discJson)) {
        $layers = is_array($discJson) ? $discJson : json_decode($discJson, true);
        $layerStr = collect($layers)->map(fn($d) =>
        $d['type'] === 'percent'
        ? $d['value'] . '%'
        : 'Rp ' . number_format($d['value'], 0, ',', '.')
        )->join(' + ');
        $discNote = 'Diskon ' . $layerStr . ' = Rp ' . number_format($discount, 0, ',', '.');
        }

        $catatanFinal = collect([
        $delivery->notes ?? null,
        $trx->notes ?? null,
        ])->filter()->join(' | ');

        $maxRows = 10;
        $itemCount = count($delivery->items);
        @endphp

        {{-- INFO HEADER --}}
        <table style="width:100%; border-collapse:collapse; border-bottom:1px dashed #999; margin-bottom:3px; padding-bottom:2px;">
            <tr>
                {{-- Kiri --}}
                <td style="width:48%; vertical-align:top; padding-right:3px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">No. Surat Jalan</td>
                            <td class="sep">:</td>
                            <td class="val bold">{{ $delivery->do_number }} / {{ \Carbon\Carbon::parse($delivery->do_date)->locale('id')->translatedFormat('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Kepada</td>
                            <td class="sep">:</td>
                            <td class="val bold">{{ $trx->customer?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Telepon</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $trx->customer?->phone ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Alamat</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $trx->customer?->address ?? '—' }}</td>
                        </tr>
                    </table>
                </td>

                {{-- Garis tengah --}}
                <td style="width:3px; border-left:1px dashed #bbb;">&nbsp;</td>

                {{-- Kanan --}}
                <td style="width:48%; vertical-align:top; padding-left:5px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Tgl Kirim</td>
                            <td class="sep">:</td>
                            <td class="val">{{ \Carbon\Carbon::parse($delivery->do_date)->locale('id')->translatedFormat('l, d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Referensi Nota</td>
                            <td class="sep">:</td>
                            <td class="val bold">{{ $trx->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Sales</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $delivery->user->name }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Tgl Transaksi</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $trx->transaction_date->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Outlet</td>
                            <td class="sep">:</td>
                            <td class="val bold">{{ $trx->customer?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Keterangan</td>
                            <td class="sep">:</td>
                            <td class="val">{{ match($trx->payment_status) {
                                'unpaid' =>'Belum Lunas',
                                'paid'    => 'Lunas',
                                default   => '—'} }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- TABEL ITEM --}}
        <table class="item-table">
            <thead>
                <tr>
                    <th style="width:4%">No</th>
                    <th style="width:56%; text-align:left">Nama Barang</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:10%">Satuan</th>
                    <th style="width:20%; text-align:left; padding-left:4px; padding-right:4px;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($delivery->items as $i => $item)
                <tr>
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tb">{{ $item->product->name }}</td>
                    <td class="tc tb">{{ $item->qty_delivered }}</td>
                    <td class="tc">{{ $item->product->unit->symbol }}</td>
                    <td>@if($item->qtyRemaining() > 0)<span style="color:#c00">Sisa: {{ $item->qtyRemaining() }}</span>@endif</td>
                </tr>
                @endforeach
                @for($r = $itemCount; $r < $maxRows; $r++)
                    <tr class="empty-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    </tr>
                    @endfor
            </tbody>
        </table>

        {{-- FOOTER --}}
        <table style="width:100%; border-collapse:collapse; border-top:1px dashed #999; padding-top:2px; margin-top:2px;">
            <tr>
                {{-- Tanda Tangan --}}
                <td style="width:56%; vertical-align:top; padding-right:3px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:33%; text-align:center; vertical-align:top; padding:0 3px;">
                                <div style="font-size:7px; font-weight:bold; padding-bottom:35px;">Mengetahui</div>
                                <div style="border-top:0.7px solid #000; padding-top:2px; font-size:7px; color:#444;">{{ $delivery->user->name }}</div>
                            </td>
                            <td style="width:33%; text-align:center; vertical-align:top; padding:0 3px;">
                                <div style="font-size:7px; font-weight:bold; padding-bottom:35px;">Driver</div>
                                <div style="border-top:0.7px solid #000; padding-top:2px; font-size:7px;">&nbsp;</div>
                            </td>
                            <td style="width:33%; text-align:center; vertical-align:top; padding:0 3px;">
                                <div style="font-size:7px; font-weight:bold; padding-bottom:35px;">Penerima</div>
                                <div style="border-top:0.7px solid #000; padding-top:2px; font-size:7px;">&nbsp;</div>
                            </td>
                        </tr>
                    </table>
                </td>

                {{-- Garis tengah --}}
                <td style="width:3px; border-left:1px dashed #bbb;">&nbsp;</td>

                {{-- Tagihan --}}
                <td style="width:42%; vertical-align:top; padding-left:5px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="b-lbl">Tagihan</td>
                            <td class="b-sep">:</td>
                            <td class="b-val">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="b-lbl">DP / Lunas</td>
                            <td class="b-sep">:</td>
                            <td class="b-val">Rp {{ number_format($amtPaid, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="b-lbl">Sisa Tagihan</td>
                            <td class="b-sep">:</td>
                            <td class="b-val">Rp {{ number_format($amtRemain, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    <div class="catatan-box">
                        <strong style="font-size:6.5px;">Catatan:</strong> {{ $catatanFinal ?: '—' }}
                    </div>
                </td>
            </tr>
        </table>

        {{-- BOTTOM BAR --}}
        <div class="bottom-bar">
            Kritik &amp; saran pelayanan pengiriman hubungi kami di WhatsApp 0813 3181 5189
        </div>

    </div>
</body>

</html>