<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .page {
            padding: 30px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 16px;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #2563eb;
        }

        .company-info {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h2 {
            font-size: 20px;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .doc-number {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-top: 4px;
        }

        .doc-date {
            font-size: 11px;
            color: #666;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-box {
            background: #f8fafc;
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #2563eb;
        }

        .info-box h4 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2563eb;
            margin-bottom: 6px;
        }

        .info-box p {
            font-size: 12px;
            line-height: 1.6;
        }

        /* Items Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th {
            background: #2563eb;
            color: white;
            padding: 8px 10px;
            font-size: 11px;
            text-align: left;
        }

        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Notes */
        .note-box {
            margin-top: 16px;
            background: #fefce8;
            border: 1px solid #fde047;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
        }

        .note-box h4 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #92400e;
            margin-bottom: 4px;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
        }

        .sign-box {
            text-align: center;
        }

        .sign-box .sign-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 6px;
            font-size: 11px;
        }

        .sign-box .sign-title {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="company-name">NAMA PERUSAHAAN</div>
                <div class="company-info">
                    Jl. Alamat Perusahaan No. 123, Kota<br>
                    Telp: (021) 1234-5678 | Email: info@perusahaan.com
                </div>
            </div>
            <div class="doc-title">
                <h2>Surat Pesanan</h2>
                <div class="doc-number">{{ $order->order_number }}</div>
                <div class="doc-date">{{ $order->order_date->locale('id')->translatedFormat('d F Y') }}</div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-box">
                <h4>Pemesan</h4>
                <p>
                    <strong>{{ $order->customer?->name ?? 'Umum' }}</strong><br>
                    @if($order->customer?->phone)
                    {{ $order->customer->phone }}<br>
                    @endif
                    @if($order->delivery_address)
                    {{ $order->delivery_address }}
                    @endif
                </p>
            </div>
            <div class="info-box">
                <h4>Detail Pesanan</h4>
                <p>
                    <strong>Tgl Pesan:</strong> {{ $order->order_date->format('d/m/Y') }}<br>
                    <strong>Target Selesai:</strong> {{ $order->target_date?->format('d/m/Y') ?? '-' }}<br>
                    <strong>Dibuat oleh:</strong> {{ $order->user?->name ?? '-' }}<br>
                    <strong>Status:</strong> {{ $order->statusLabel() }}
                </p>
            </div>
        </div>

        @if($order->customer_notes)
        <div class="note-box">
            <h4>Catatan dari Customer</h4>
            {{ $order->customer_notes }}
        </div>
        @endif

        <!-- Items Table -->
        <table style="margin-top:16px">
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:45%">Produk</th>
                    <th style="width:25%">Spesifikasi</th>
                    <th style="width:8%" class="text-center">Qty</th>
                    <th style="width:17%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->displayName() }}</td>
                    <td>{{ $item->specSummary() ?: '-' }}</td>
                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                    <td>{{ $item->item_notes ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($order->production_notes)
        <div class="note-box">
            <h4>Catatan untuk Tim Produksi</h4>
            {{ $order->production_notes }}
        </div>
        @endif

        <!-- Footer Signatures -->
        <div class="footer">
            <table>
                <tr>
                    <td style="width:33%">
                        <div class="sign-box">
                            <div class="sign-title">Dibuat oleh</div>
                            <div class="sign-line">{{ $order->user?->name ?? '________________' }}</div>
                            <div class="sign-title">Admin</div>
                        </div>
                    </td>
                    <td style="width:33%">
                        <div class="sign-box">
                            <div class="sign-title">Tim Produksi</div>
                            <div class="sign-line">&nbsp;</div>
                            <div class="sign-title">Pelaksana</div>
                        </div>
                    </td>
                    <td style="width:33%">
                        <div class="sign-box">
                            <div class="sign-title">Disetujui oleh</div>
                            <div class="sign-line">{{ $order->customer?->name ?? '________________' }}</div>
                            <div class="sign-title">Pelanggan</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>