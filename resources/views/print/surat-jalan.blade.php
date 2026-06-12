{{--
    SURAT JALAN — QZ TRAY (ESC/P Raw Mode)
    Kertas: Continuous ½ folio (210mm x 140mm)
    Multi-halaman otomatis jika item melebihi kapasitas
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Surat Jalan {{ $delivery->do_number }}</title>
    <script src="{{ asset('js/qz-tray.js') }}"></script>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background: #1e293b;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 28px 32px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        h2 {
            margin: 0 0 4px;
            font-size: 18px;
            color: #f1f5f9;
        }

        .sub {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .info-row span:last-child {
            color: #e2e8f0;
            font-weight: bold;
        }

        .divider {
            border: none;
            border-top: 1px solid #1e293b;
            margin: 16px 0;
        }

        .printer-wrap {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        select,
        input[type=text] {
            width: 100%;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #f1f5f9;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            padding: 8px 12px;
            outline: none;
        }

        select:focus,
        input:focus {
            border-color: #3b82f6;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }

        #status {
            margin-top: 14px;
            font-size: 12px;
            padding: 10px 14px;
            border-radius: 6px;
            display: none;
        }

        .status-ok {
            background: #14532d;
            color: #86efac;
            display: block !important;
        }

        .status-err {
            background: #7f1d1d;
            color: #fca5a5;
            display: block !important;
        }

        .status-info {
            background: #1e3a5f;
            color: #93c5fd;
            display: block !important;
        }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            background: #ef4444;
        }

        .dot.connected {
            background: #22c55e;
        }

        #conn-status {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 16px;
        }

        /* PREVIEW */
        #preview-wrap {
            margin-top: 24px;
            width: 100%;
            max-width: 860px;
        }

        #preview-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-page {
            background: #f8f5e4;
            color: #1a1a1a;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
            padding: 14px 16px;
            border: 1px solid #d4c97a;
            border-radius: 6px;
            white-space: pre;
            overflow-x: auto;
            box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.08);
            min-height: 200px;
            margin-bottom: 12px;
        }

        .page-label {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preview-badge {
            background: #d4c97a;
            color: #1a1a1a;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2>🖨 Print Surat Jalan</h2>
        <div class="sub">via QZ Tray — ESC/P Raw Mode (dot matrix)</div>

        <div class="info-row"><span>No. SJ</span><span>{{ $delivery->do_number }}</span></div>
        <div class="info-row"><span>Customer</span><span>{{ $delivery->transaction->customer?->name ?? '—' }}</span></div>
        <div class="info-row"><span>Tanggal</span><span>{{ \Carbon\Carbon::parse($delivery->do_date)->format('d/m/Y') }}</span></div>
        <div class="info-row"><span>Jumlah Item</span><span>{{ count($delivery->items) }} item</span></div>

        <hr class="divider">

        <div id="conn-status">
            <span class="dot" id="dot"></span>
            <span id="conn-label">Menghubungkan ke QZ Tray...</span>
            <button id="btn-retry" onclick="connectQZ()" style="display:none; margin-left:8px; background:#334155; color:#e2e8f0; border:none; border-radius:4px; padding:2px 10px; font-size:11px; cursor:pointer;">Retry</button>
        </div>

        <div class="printer-wrap">
            <label>Nama Printer (sesuai Windows)</label>
            <select id="printer-select">
                <option value="">-- Memuat... --</option>
            </select>
        </div>

        <div class="printer-wrap">
            <label>Jumlah Salinan</label>
            <input type="text" id="copies" value="1" style="width:80px">
        </div>

        <div class="btn-group">
            <button class="btn btn-primary" id="btn-print" disabled onclick="doPrint()">Print Sekarang</button>
            <button class="btn btn-secondary" onclick="window.close()">Tutup</button>
        </div>

        <div id="status"></div>
    </div>

    <div id="preview-wrap">
        <div id="preview-label">
            <span>📄 Preview Hasil Cetak</span>
            <span class="preview-badge">Dot Matrix Simulation</span>
        </div>
        <div id="preview-pages">Memuat preview...</div>
    </div>

    @php
    $trx = $delivery->transaction;
    $sjDoNumber = $delivery->do_number;
    $sjDoDate = \Carbon\Carbon::parse($delivery->do_date)->locale('id')->translatedFormat('d F Y');
    $sjDoDay = \Carbon\Carbon::parse($delivery->do_date)->locale('id')->translatedFormat('l, d F Y');
    $sjInvoice = $trx->invoice_number;
    $sjSales = $delivery->user?->name ?? '';
    $sjTrxDate = $trx->transaction_date?->format('d/m/Y') ?? '';
    $sjCustName = $trx->customer?->name ?? '—';
    $sjCustPhone = $trx->customer?->phone ?? '—';
    $sjCustAddr = $trx->customer?->address ?? '—';
    $sjPayStatus = match($trx->payment_status) {
    'unpaid' => 'BELUM LUNAS',
    'partial' => 'SEBAGIAN',
    'paid' => 'LUNAS',
    default => strtoupper($trx->payment_status),
    };
    $sjGrandTotal = number_format($trx->grand_total ?? 0, 0, ',', '.');
    $sjAmtPaid = number_format($trx->amount_paid ?? 0, 0, ',', '.');
    $sjAmtRemain = number_format($trx->amount_remaining ?? 0, 0, ',', '.');
    $sjCatatan = collect([$delivery->notes, $trx->notes])->filter()->join(' | ');
    $sjItems = $delivery->items->map(fn($item) => [
    'name' => $item->product?->name ?? '',
    'qty' => $item->qty_delivered,
    'unit' => $item->product?->unit?->symbol ?? '',
    'remaining' => $item->qtyRemaining(),
    ]);
    $sjIsVoid = in_array($trx->payment_status, ['void', 'cancelled']);
    @endphp

    <script>
        const SJ = {
            doNumber: @json($sjDoNumber),
            doDate: @json($sjDoDate),
            doDay: @json($sjDoDay),
            invoiceNum: @json($sjInvoice),
            salesName: @json($sjSales),
            trxDate: @json($sjTrxDate),
            customerName: @json($sjCustName),
            customerPhone: @json($sjCustPhone),
            customerAddr: @json($sjCustAddr),
            payStatus: @json($sjPayStatus),
            grandTotal: @json($sjGrandTotal),
            amtPaid: @json($sjAmtPaid),
            amtRemain: @json($sjAmtRemain),
            catatan: @json($sjCatatan),
            items: @json($sjItems),
            isVoid: @json($sjIsVoid),
        };

        // ============================================================
        // CONFIG
        // ============================================================
        const COL = 95; // char/baris @10cpi, kertas ~210mm
        const ROWS_PER_PAGE = 10; // max baris item per halaman
        const PAGE_LEN = 33; // page length @6lpi (140mm ≈ 33 baris)

        // ============================================================
        // ESC/P HELPERS
        // ============================================================
        const ESC = '\x1B',
            LF = '\n',
            FF = '\x0C';

        function padR(s, n) {
            s = String(s ?? '');
            return (s.length > n ? s.substring(0, n) : s).padEnd(n, ' ');
        }

        function padL(s, n) {
            s = String(s ?? '');
            return (s.length > n ? s.substring(0, n) : s).padStart(n, ' ');
        }

        function center(s, n) {
            s = String(s ?? '');
            if (s.length >= n) return s.substring(0, n);
            const p = Math.floor((n - s.length) / 2);
            return ' '.repeat(p) + s + ' '.repeat(n - s.length - p);
        }

        function ln(c, n) {
            return c.repeat(n);
        }

        function wrapText(str, maxW) {
            str = String(str ?? '');
            if (str.length <= maxW) return [str];
            const words = str.split(' ');
            const lines = [];
            let cur = '';
            words.forEach(w => {
                if ((cur + (cur ? ' ' : '') + w).length <= maxW) {
                    cur += (cur ? ' ' : '') + w;
                } else {
                    if (cur) lines.push(cur);
                    while (w.length > maxW) {
                        lines.push(w.substring(0, maxW));
                        w = w.substring(maxW);
                    }
                    cur = w;
                }
            });
            if (cur) lines.push(cur);
            return lines.length ? lines : [''];
        }

        // ============================================================
        // PAGINATION — split items ke halaman-halaman
        // Return: array of { pageNum, doNumber, items: [...] }
        // doNumber: halaman 1 = original, halaman 2+ = original + '-N'
        // ============================================================
        function paginateItems() {
            // Hitung baris yang dibutuhkan tiap item (bisa wrap)
            const C_NAMA = 54; // lebar kolom nama (untuk hitung wrap)
            const itemRows = SJ.items.map(item => ({
                ...item,
                rows: wrapText(item.name, C_NAMA).length
            }));

            const pages = [];
            let pageNum = 1;
            let curPage = [];
            let curRows = 0;

            itemRows.forEach(item => {
                // Jika item ini tidak muat di halaman saat ini, mulai halaman baru
                if (curRows + item.rows > ROWS_PER_PAGE && curPage.length > 0) {
                    pages.push({
                        pageNum,
                        items: curPage
                    });
                    pageNum++;
                    curPage = [];
                    curRows = 0;
                }
                curPage.push(item);
                curRows += item.rows;
            });

            // Push halaman terakhir
            if (curPage.length > 0 || pages.length === 0) {
                pages.push({
                    pageNum,
                    items: curPage
                });
            }

            const totalPages = pages.length;

            // Tambah doNumber per halaman
            return pages.map((page, idx) => ({
                ...page,
                totalPages,
                doNumber: idx === 0 ?
                    SJ.doNumber :
                    SJ.doNumber + '-' + idx,
                // Nomor urut item global (mulai dari mana di halaman ini)
                startIndex: pages.slice(0, idx).reduce((acc, p) => acc + p.items.length, 0)
            }));
        }

        // ============================================================
        // BUILD HEADER (per halaman)
        // ============================================================
        function buildHeader(doNumber, pageNum, totalPages) {
            const out = [];

            const HL = 52;
            const HR = COL - HL - 3;
            const LW = 10;
            const SEP = ' : ';

            // Title — tampilkan nomor halaman jika lebih dari 1
            const pageInfo = totalPages > 1 ? `  (Hal. ${pageNum}/${totalPages})` : '';
            out.push(ESC + 'E' + center('S U R A T   J A L A N' + pageInfo, COL) + ESC + 'F' + LF);
            out.push(ln('-', COL) + LF);

            // Kanan: selalu tampil lengkap di semua halaman
            const rightRows = [
                padR('Kirim', LW) + SEP + SJ.doDay.substring(0, HR - LW - SEP.length),
                padR('Ref. Nota', LW) + SEP + SJ.invoiceNum,
                padR('Sales', LW) + SEP + SJ.salesName.substring(0, HR - LW - SEP.length),
                padR('Tgl Trx', LW) + SEP + SJ.trxDate,
                padR('Outlet', LW) + SEP + SJ.customerName.substring(0, HR - LW - SEP.length),
                padR('Status', LW) + SEP + SJ.payStatus,
            ];

            // Kiri: nomor SJ pakai doNumber halaman ini
            const addrLines = wrapText(SJ.customerAddr, HL - LW - SEP.length);
            const leftRows = [
                padR('No. Surat Jalan', LW) + SEP + padR(doNumber + ' / ' + SJ.doDate, HL - LW - SEP.length),
                padR('Kepada', LW) + SEP + padR(SJ.customerName.substring(0, HL - LW - SEP.length), HL - LW - SEP.length),
                padR('Telepon', LW) + SEP + padR(SJ.customerPhone, HL - LW - SEP.length),
            ];
            addrLines.forEach((al, ai) => {
                const prefix = ai === 0 ?
                    padR('Alamat', LW) + SEP :
                    ' '.repeat(LW + SEP.length);
                leftRows.push(prefix + padR(al, HL - LW - SEP.length));
            });

            const totalRows = Math.max(leftRows.length, rightRows.length);
            for (let i = 0; i < totalRows; i++) {
                const l = padR(leftRows[i] ?? '', HL);
                const r = rightRows[i] ?? '';
                out.push(l + ' | ' + r + LF);
            }

            out.push(ln('-', COL) + LF);
            return out;
        }

        // ============================================================
        // BUILD ITEM TABLE (per halaman)
        // ============================================================
        function buildItemTable(pageItems, startIndex, isLastPage) {
            const out = [];
            const C = {
                no: 3,
                nama: 54,
                qty: 5,
                sat: 8,
                ket: 20
            };

            // Header kolom
            out.push(
                ESC + 'E' +
                padL('No', C.no) + ' ' +
                padR('Nama Barang', C.nama) + ' ' +
                padL('Qty', C.qty) + ' ' +
                padR('Satuan', C.sat) + ' ' +
                padR('Keterangan', C.ket) +
                ESC + 'F' + LF
            );
            out.push(ln('-', COL) + LF);

            let usedRows = 0;
            pageItems.forEach((item, localIdx) => {
                const globalIdx = startIndex + localIdx;
                const ket = item.remaining > 0 ? 'Sisa: ' + item.remaining : '';
                const namaLines = wrapText(item.name, C.nama);
                namaLines.forEach((nl, ni) => {
                    out.push(
                        (ni === 0 ? padL(globalIdx + 1, C.no) : ' '.repeat(C.no)) + ' ' +
                        padR(nl, C.nama) + ' ' +
                        (ni === 0 ? padL(item.qty, C.qty) : ' '.repeat(C.qty)) + ' ' +
                        (ni === 0 ? padR(item.unit, C.sat) : ' '.repeat(C.sat)) + ' ' +
                        (ni === 0 ? padR(ket, C.ket) : '') + LF
                    );
                });
                usedRows += namaLines.length;
            });

            // Baris kosong — hanya di halaman terakhir agar footer turun
            if (isLastPage) {
                const emptyRows = Math.max(0, ROWS_PER_PAGE - usedRows);
                for (let i = 0; i < emptyRows; i++) out.push(LF);
            }

            out.push(ln('-', COL) + LF);
            return out;
        }

        // ============================================================
        // BUILD FOOTER (per halaman)
        // ============================================================
        function buildFooter(pageNum, totalPages) {
            const out = [];

            const TTD_W = 16 + 1 + 16 + 1 + 16; // = 50
            const BIL_W = COL - TTD_W - 2;
            const BL = 11;
            const BV = BIL_W - BL - 3;

            function bilRow(label, val) {
                return padR(label, BL) + ' : ' + padL(val, BV);
            }

            // Tagihan hanya di halaman terakhir, halaman sebelumnya tampilkan "Lanjut hal. X"
            const isLast = pageNum === totalPages;

            out.push(
                center('Mengetahui', 16) + ' ' +
                center('Driver', 16) + ' ' +
                center('Penerima', 16) +
                '  ' +
                (isLast ? bilRow('Tagihan', 'Rp ' + SJ.grandTotal) : padR('(bersambung ke hal. ' + (pageNum + 1) + ')', BIL_W)) + LF
            );
            out.push(' '.repeat(TTD_W + 2) + (isLast ? bilRow('DP / Lunas', 'Rp ' + SJ.amtPaid) : '') + LF);
            out.push(' '.repeat(TTD_W + 2) + (isLast ? bilRow('Sisa', 'Rp ' + SJ.amtRemain) : '') + LF);
            out.push(
                ln('_', 16) + ' ' + ln('_', 16) + ' ' + ln('_', 16) +
                '  ' + (isLast ? bilRow('Catatan', SJ.catatan.substring(0, BV)) : '') + LF
            );
            out.push(center(SJ.salesName.substring(0, 16), 16) + LF);
            out.push(ln('-', COL) + LF);
            out.push(center('Kritik & saran: WhatsApp 0813 3181 5189', COL) + LF);
            out.push(LF);

            return out;
        }

        // ============================================================
        // BUILD FULL ESC/P — semua halaman
        // ============================================================
        function buildEscp() {
            const pages = paginateItems();
            const out = [];

            pages.forEach((page, idx) => {
                const isFirst = idx === 0;
                const isLast = idx === pages.length - 1;

                // INIT hanya di halaman pertama
                if (isFirst) {
                    out.push(
                        ESC + '@' + // reset
                        ESC + 'M' + // 12 CPI
                        ESC + '2' + // 1/6" line spacing
                        ESC + 'C' + String.fromCharCode(PAGE_LEN) + // page length
                        ESC + 'O' + // disable skip perf
                        ESC + 'l' + '\x00' + // left margin = 0
                        ESC + 'Q' + String.fromCharCode(COL) // right margin
                    );
                }

                // VOID hanya halaman pertama
                if (isFirst && SJ.isVoid) {
                    out.push(ESC + 'E' + center('*** DOCUMENT VOID ***', COL) + ESC + 'F' + LF);
                }

                // Header
                out.push(...buildHeader(page.doNumber, page.pageNum, page.totalPages));

                // Item table
                out.push(...buildItemTable(page.items, page.startIndex, isLast));

                // Footer
                out.push(...buildFooter(page.pageNum, page.totalPages));

                // Form feed ke halaman berikutnya (kecuali halaman terakhir)
                if (!isLast) {
                    out.push(FF);
                }
            });

            return out;
        }

        // ============================================================
        // PREVIEW
        // ============================================================
        function stripEscp(raw) {
            return raw
                .replace(/\x1BC[\s\S]/g, '')
                .replace(/\x1Bl[\s\S]/g, '')
                .replace(/\x1BQ[\s\S]/g, '')
                .replace(/\x1B[@PM2OEFGHl]/g, '')
                .replace(/\x1B[\s\S]/g, '')
                .replace(/\r/g, '')
                .replace(/\x0C/g, '\n--- [FORM FEED / halaman baru] ---\n')
                .replace(/^\n+/, '');
        }

        function renderPreview() {
            const pages = paginateItems();
            const wrap = document.getElementById('preview-pages');
            wrap.innerHTML = '';

            // Build ESC/P per halaman untuk preview terpisah
            pages.forEach((page, idx) => {
                const isFirst = idx === 0;
                const isLast = idx === pages.length - 1;

                const pageOut = [];
                if (isFirst && SJ.isVoid) {
                    pageOut.push('*** DOCUMENT VOID ***\n');
                }
                pageOut.push(...buildHeader(page.doNumber, page.pageNum, page.totalPages));
                pageOut.push(...buildItemTable(page.items, page.startIndex, isLast));
                pageOut.push(...buildFooter(page.pageNum, page.totalPages));

                const raw = stripEscp(pageOut.join(''));

                const label = document.createElement('div');
                label.className = 'page-label';
                label.textContent = `Halaman ${page.pageNum} dari ${page.totalPages}  —  No. SJ: ${page.doNumber}`;

                const box = document.createElement('div');
                box.className = 'preview-page';
                box.textContent = raw;

                wrap.appendChild(label);
                wrap.appendChild(box);
            });
        }

        // ============================================================
        // QZ TRAY
        // ============================================================
        function setStatus(msg, type) {
            const el = document.getElementById('status');
            el.className = 'status-' + type;
            el.textContent = msg;
        }

        function setConnected(ok) {
            document.getElementById('dot').className = 'dot' + (ok ? ' connected' : '');
            document.getElementById('conn-label').textContent = ok ? 'QZ Tray terhubung ✓' : 'QZ Tray tidak terhubung — klik Retry';
            document.getElementById('btn-print').disabled = !ok;
            document.getElementById('btn-retry').style.display = ok ? 'none' : 'inline-block';
        }

        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch("{{ route('qztray.certificate') }}", {
                    cache: 'no-store'
                })
                .then(res => res.ok ? resolve(res.text()) : reject(res.text()));
        });
        qz.security.setSignatureAlgorithm("SHA512");
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch("{{ route('qztray.sign') }}?request=" + encodeURIComponent(toSign), {
                    cache: 'no-store',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                    }
                }).then(res => res.ok ? resolve(res.text()) : reject(res.text()));
            };
        });

        let _connected = false;

        function connectQZ() {
            setStatus('Menghubungkan ke QZ Tray...', 'info');
            _connected = false;
            if (qz.websocket.isActive()) qz.websocket.disconnect().catch(() => {});

            qz.websocket.connect({
                    retries: 2,
                    delay: 0.5,
                    host: 'localhost',
                    usingSecure: true
                })
                .then(onConnected)
                .catch(() => qz.websocket.connect({
                    retries: 2,
                    delay: 0.5,
                    host: 'localhost',
                    usingSecure: false
                }))
                .then(onConnected)
                .catch(err => {
                    setConnected(false);
                    setStatus('Gagal connect ke QZ Tray. Klik Retry. Error: ' + String(err), 'err');
                });
        }

        function onConnected() {
            if (_connected) return;
            _connected = true;
            setConnected(true);
            setStatus('', 'info');
            qz.printers.find().then(printers => {
                const sel = document.getElementById('printer-select');
                sel.innerHTML = '';
                if (!printers || !printers.length) {
                    sel.innerHTML = '<option value="">-- Tidak ada printer --</option>';
                    return;
                }
                printers.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p;
                    opt.textContent = p;
                    if (/lx.?3[01]0|epson.*lx/i.test(p)) opt.selected = true;
                    sel.appendChild(opt);
                });
                if (!sel.value) sel.value = printers[0];
            }).catch(err => setStatus('Gagal ambil daftar printer: ' + err, 'err'));
        }

        window.addEventListener('DOMContentLoaded', () => {
            connectQZ();
            renderPreview();
        });

        function doPrint() {
            const printerName = document.getElementById('printer-select').value;
            const copies = parseInt(document.getElementById('copies').value) || 1;
            if (!printerName) {
                setStatus('Pilih printer terlebih dahulu.', 'err');
                return;
            }

            setStatus('Mengirim ke printer...', 'info');
            document.getElementById('btn-print').disabled = true;

            // Kirim semua halaman sekaligus dalam 1 print job
            const config = qz.configs.create(printerName, {
                copies,
                jobName: 'SJ-' + SJ.doNumber
            });
            qz.print(config, buildEscp())
                .then(() => {
                    const pages = paginateItems();
                    setStatus(`✓ Berhasil dikirim — ${pages.length} halaman ke printer: ${printerName}`, 'ok');
                    document.getElementById('btn-print').disabled = false;
                })
                .catch(err => {
                    setStatus('✗ Gagal: ' + err, 'err');
                    document.getElementById('btn-print').disabled = false;
                });
        }
    </script>

</body>

</html>