<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .page { padding: 30px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
        .company-name { font-size: 22px; font-weight: bold; color: #2563eb; }
        .company-info { font-size: 11px; color: #666; margin-top: 4px; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { font-size: 20px; color: #2563eb; text-transform: uppercase; letter-spacing: 2px; }
        .invoice-number { font-size: 14px; font-weight: bold; color: #333; margin-top: 4px; }
        .invoice-date { font-size: 11px; color: #666; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .info-box { background: #f8fafc; padding: 12px; border-radius: 4px; border-left: 3px solid #2563eb; }
        .info-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #2563eb; margin-bottom: 6px; }
        .info-box p { font-size: 12px; line-height: 1.6; }

        /* Items Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #2563eb; color: white; padding: 8px 10px; font-size: 11px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totals */
        .totals-section { display: flex; justify-content: flex-end; }
        .totals-box { width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #e5e7eb; }
        .totals-row.grand { font-weight: bold; font-size: 14px; color: #2563eb; border-bottom: 2px solid #2563eb; padding: 8px 0; }
        .totals-row.remaining { font-weight: bold; color: #dc2626; }
        .discount-detail { font-size: 10px; color: #888; padding-left: 10px; }

        /* Payments */
        .payments-section { margin-top: 20px; }
        .payments-section h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 8px; }

        /* Footer */
        .footer { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .sign-box { text-align: center; }
        .sign-box .sign-line { border-top: 1px solid #333; margin-top: 60px; padding-top: 6px; font-size: 11px; }
        .sign-box .sign-title { font-size: 10px; color: #666; }
        .note-box { margin-top: 20px; background: #fefce8; border: 1px solid #fde047; padding: 10px; border-radius: 4px; font-size: 11px; }
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
        <div class="invoice-title">
            <h2>Invoice</h2>
            <div class="invoice-number">{{ $transaction->invoice_number }}</div>
            <div class="invoice-date">{{ $transaction->transaction_date->format('d F Y') }}</div>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <h4>Tagihan Kepada</h4>
            <p>
                <strong>{{ $transaction->customer?->name ?? 'Umum' }}</strong><br>
                @if($transaction->customer?->phone)
                    {{ $transaction->customer->phone }}<br>
                @endif
                @if($transaction->customer?->address)
                    {{ $transaction->customer->address }}
                @endif
            </p>
        </div>
        <div class="info-box">
            <h4>Detail Invoice</h4>
            <p>
                <strong>Tgl Transaksi:</strong> {{ $transaction->transaction_date->format('d/m/Y') }}<br>
                <strong>Tgl Kirim:</strong> {{ $transaction->delivery_date_display }}<br>
                <strong>Kasir:</strong> {{ $transaction->user->name }}<br>
                <strong>Status:</strong>
                @if($transaction->payment_status === 'paid') ✅ LUNAS
                @elseif($transaction->payment_status === 'partial') 🟡 SEBAGIAN
                @else 🔴 BELUM BAYAR
                @endif
            </p>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:35%">Produk</th>
                <th style="width:10%" class="text-center">Qty</th>
                <th style="width:10%" class="text-center">Satuan</th>
                <th style="width:20%" class="text-right">Harga Satuan</th>
                <th style="width:20%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $item->product_name }}<br>
                    <small style="color:#888">{{ $item->product_sku }}</small>
                </td>
                <td class="text-center">{{ number_format($item->quantity) }}</td>
                <td class="text-center">{{ $item->unit_name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</span>
            </div>

            @if($transaction->discount_amount > 0)
            <div class="totals-row" style="color:#dc2626">
                <div>
                    <div>Diskon ({{ $discountSummary }})</div>
                    @foreach($discountBreakdown as $layer)
                    <div class="discount-detail">
                        {{ $layer['label'] }}: -Rp {{ number_format($layer['amount_reduced'], 0, ',', '.') }}
                        → Rp {{ number_format($layer['after'], 0, ',', '.') }}
                    </div>
                    @endforeach
                </div>
                <span>-Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</span>
            </div>
            @endif

            <div class="totals-row grand">
                <span>Grand Total</span>
                <span>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</span>
            </div>

            <div class="totals-row" style="color:#16a34a">
                <span>Total Dibayar</span>
                <span>Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</span>
            </div>

            @if($transaction->amount_remaining > 0)
            <div class="totals-row remaining">
                <span>Sisa Tagihan</span>
                <span>Rp {{ number_format($transaction->amount_remaining, 0, ',', '.') }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Payments -->
    @if($transaction->payments->isNotEmpty())
    <div class="payments-section">
        <h4>Riwayat Pembayaran</h4>
        <table>
            <thead>
                <tr>
                    <th>Tgl Bayar</th>
                    <th>Metode</th>
                    <th>Referensi</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                    <td>
                        {{ $payment->paymentMethod->name }}
                        @if($payment->paymentMethod->is_installment && $payment->installment_detail)
                            <br><small style="color:#888">{{ $payment->installment_detail['provider'] ?? '' }} - {{ $payment->installment_detail['tenor'] ?? '' }} bulan</small>
                        @endif
                    </td>
                    <td>{{ $payment->reference_number ?? '-' }}</td>
                    <td class="text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($transaction->notes)
    <div class="note-box">
        <strong>Catatan:</strong> {{ $transaction->notes }}
    </div>
    @endif

    <!-- Footer Signatures -->
    <div class="footer">
        <div class="sign-box">
            <div class="sign-title">Dibuat oleh</div>
            <div class="sign-line">{{ $transaction->user->name }}</div>
            <div class="sign-title">Kasir</div>
        </div>
        <div></div>
        <div class="sign-box">
            <div class="sign-title">Diterima oleh</div>
            <div class="sign-line">{{ $transaction->customer?->name ?? '________________' }}</div>
            <div class="sign-title">Pelanggan</div>
        </div>
    </div>
</div>
</body>
</html>