<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .page { padding: 30px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #16a34a; padding-bottom: 16px; margin-bottom: 20px; }
        .company-name { font-size: 20px; font-weight: bold; color: #16a34a; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 18px; color: #16a34a; text-transform: uppercase; }
        .doc-title .do-number { font-size: 16px; font-weight: bold; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-box { background: #f0fdf4; padding: 12px; border-left: 3px solid #16a34a; }
        .info-box h4 { color: #16a34a; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #16a34a; color: white; padding: 8px 10px; font-size: 11px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f0fdf4; }
        .text-center { text-align: center; }

        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #f3f4f6; color: #6b7280; }

        .footer { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .sign-box { text-align: center; }
        .sign-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; font-size: 11px; }
        .sign-title { font-size: 10px; color: #666; }
    </style>
</head>
<body>
<div class="page">
    <!-- Header -->
    <div class="header">
        <div>
            <div class="company-name">NAMA PERUSAHAAN</div>
            <div style="font-size:11px;color:#666;margin-top:4px">
                Jl. Alamat Perusahaan No. 123 | Telp: (021) 1234-5678
            </div>
        </div>
        <div class="doc-title">
            <h2>Surat Jalan</h2>
            <div class="do-number">{{ $delivery->do_number }}</div>
            <div style="font-size:11px;color:#666">{{ $delivery->do_date->format('d/m/Y') }}</div>
        </div>
    </div>

    <!-- Info -->
    <div class="info-grid">
        <div class="info-box">
            <h4>Tujuan Pengiriman</h4>
            <p>
                <strong>{{ $delivery->transaction->customer?->name ?? 'Umum' }}</strong><br>
                @if($delivery->transaction->customer?->phone)
                    {{ $delivery->transaction->customer->phone }}<br>
                @endif
                @if($delivery->transaction->customer?->address)
                    {{ $delivery->transaction->customer->address }}
                @endif
            </p>
        </div>
        <div class="info-box">
            <h4>Referensi</h4>
            <p>
                <strong>No. Invoice:</strong> {{ $delivery->transaction->invoice_number }}<br>
                <strong>Tgl Invoice:</strong> {{ $delivery->transaction->transaction_date->format('d/m/Y') }}<br>
                <strong>Dibuat oleh:</strong> {{ $delivery->user->name }}<br>
                <strong>Status:</strong>
                <span class="status-badge status-{{ $delivery->status }}">
                    {{ match($delivery->status) { 'pending'=>'Menunggu', 'partial'=>'Sebagian', 'completed'=>'Selesai' } }}
                </span>
            </p>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:40%">Produk</th>
                <th style="width:15%" class="text-center">Qty Pesan</th>
                <th style="width:15%" class="text-center">Qty Kirim</th>
                <th style="width:15%" class="text-center">Sisa</th>
                <th style="width:10%" class="text-center">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($delivery->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $item->product->name }}<br>
                    <small style="color:#888">{{ $item->product->sku }}</small>
                </td>
                <td class="text-center">{{ number_format($item->qty_ordered) }}</td>
                <td class="text-center" style="font-weight:bold;color:#16a34a">{{ number_format($item->qty_delivered) }}</td>
                <td class="text-center" style="color:{{ $item->qtyRemaining() > 0 ? '#dc2626' : '#16a34a' }}">
                    {{ number_format($item->qtyRemaining()) }}
                </td>
                <td class="text-center">{{ $item->product->unit->symbol }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($delivery->notes)
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:10px;border-radius:4px;margin-bottom:20px;font-size:11px;">
        <strong>Catatan:</strong> {{ $delivery->notes }}
    </div>
    @endif

    <!-- Footer Signatures -->
    <div class="footer">
        <div class="sign-box">
            <div class="sign-title">Dikirim oleh</div>
            <div class="sign-line">{{ $delivery->user->name }}</div>
            <div class="sign-title">Pengirim</div>
        </div>
        <div class="sign-box">
            <div class="sign-title">Pengemudi</div>
            <div class="sign-line">________________</div>
            <div class="sign-title">Driver</div>
        </div>
        <div class="sign-box">
            <div class="sign-title">Diterima oleh</div>
            <div class="sign-line">________________</div>
            <div class="sign-title">Penerima</div>
        </div>
    </div>
</div>
</body>
</html>