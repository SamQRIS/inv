<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacySeeder extends Seeder
{
    // ─── Path ke file txt ───────────────────────────────────────────────────
    // Taruh file TransaksiMaster.txt dan TransaksiDetail.txt
    // di folder database/legacy/ dalam project Anda
    private string $masterFile = 'database/legacy/TransaksiMaster.txt';
    private string $detailFile = 'database/legacy/TransaksiDetail.txt';

    // ─── Cache agar tidak query berulang ────────────────────────────────────
    private int $defaultUserId;
    private int $defaultCategoryId;
    private int $defaultUnitPcsId;
    private int $defaultUnitSetId;
    private int $cashPaymentMethodId;
    private array $productCache    = []; // [nama_produk => product_id]
    private array $customerCache   = []; // [nama_customer => customer_id]

    public function run(): void
    {
        $this->command->info('🚀 Memulai import data lama dari Access...');

        // ── Pastikan prerequisite ada ────────────────────────────────────────
        $this->setupPrerequisites();

        // ── Load semua detail dulu ke memory, dikelompokkan per nota ─────────
        $this->command->info('📂 Membaca TransaksiDetail.txt...');
        $detailByNota = $this->loadDetailByNota();
        $this->command->info('   → ' . count($detailByNota) . ' nota ditemukan di detail');

        // ── Proses master line by line ────────────────────────────────────────
        $this->command->info('📂 Membaca TransaksiMaster.txt...');
        $masterLines = file(base_path($this->masterFile), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total       = count($masterLines);
        $success     = 0;
        $skipped     = 0;

        $this->command->getOutput()->progressStart($total);

        foreach ($masterLines as $line) {
            $cols = $this->parseLine($line);
            if (count($cols) < 7) {
                $skipped++;
                $this->command->getOutput()->progressAdvance();
                continue;
            }

            $nota = $this->clean($cols[1]);
            if (!$nota) {
                $skipped++;
                $this->command->getOutput()->progressAdvance();
                continue;
            }

            // Skip jika nota sudah ada (idempotent — aman dijalankan ulang)
            if (Transaction::where('invoice_number', $nota)->exists()) {
                $skipped++;
                $this->command->getOutput()->progressAdvance();
                continue;
            }

            try {
                DB::transaction(function () use ($cols, $nota, $detailByNota) {
                    $this->importSingleTransaction($cols, $nota, $detailByNota[$nota] ?? []);
                });
                $success++;
            } catch (\Throwable $e) {
                $this->command->warn("\n   ⚠ Skip nota {$nota}: " . $e->getMessage());
                $skipped++;
            }

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("✅ Selesai! Berhasil: {$success}, Dilewati: {$skipped} dari {$total} baris.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETUP PREREQUISITE
    // ─────────────────────────────────────────────────────────────────────────

    private function setupPrerequisites(): void
    {
        // User default (kasir) — pakai admin yang sudah ada
        $user = User::first();
        if (!$user) {
            throw new \RuntimeException('Tidak ada user. Jalankan DatabaseSeeder dulu: php artisan db:seed');
        }
        $this->defaultUserId = $user->id;

        // Kategori default untuk produk lama
        $category = Category::firstOrCreate(
            ['slug' => 'produk-lama'],
            ['name' => 'Produk Lama (Import)', 'is_active' => true]
        );
        $this->defaultCategoryId = $category->id;

        // Unit
        $pcs = Unit::firstOrCreate(['symbol' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'PCS']);
        $set = Unit::firstOrCreate(['symbol' => 'SET'], ['name' => 'Set', 'symbol' => 'SET']);
        $this->defaultUnitPcsId = $pcs->id;
        $this->defaultUnitSetId = $set->id;

        // Payment method untuk DP lama
        $cash = PaymentMethod::firstOrCreate(
            ['code' => 'cash'],
            ['name' => 'Tunai', 'code' => 'cash', 'is_installment' => false, 'is_active' => true, 'sort_order' => 1]
        );
        $this->cashPaymentMethodId = $cash->id;

        $this->command->info('✅ Prerequisites siap.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOAD SEMUA DETAIL KE MEMORY
    // ─────────────────────────────────────────────────────────────────────────

    private function loadDetailByNota(): array
    {
        $lines  = file(base_path($this->detailFile), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = [];

        foreach ($lines as $line) {
            $cols = $this->parseLine($line);
            // Format: id;nota;nama_barang;qty;satuan;harga;subtotal
            if (count($cols) < 5) continue;

            $nota = $this->clean($cols[1]);
            if (!$nota) continue;

            $result[$nota][] = [
                'nama'    => $this->clean($cols[2]),
                'qty'     => (int) ($cols[3] ?? 1),
                'satuan'  => $this->clean($cols[4]) ?: 'PCS',
                'harga'   => $this->parseRupiah($cols[5] ?? '0'),
                'subtotal'=> $this->parseRupiah($cols[6] ?? '0'),
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORT SATU TRANSAKSI
    // ─────────────────────────────────────────────────────────────────────────

    private function importSingleTransaction(array $cols, string $nota, array $items): void
    {
        // ── Parse kolom master ────────────────────────────────────────────────
        // [0]id [1]nota [2]tanggal [3]outlet [4]customer [5]telp [6]alamat
        // [7]total [8]? [9]dp [10]sisa [11]tgl_kirim [12]sales [13]catatan

        $tanggal      = $this->parseDate($cols[2] ?? '');
        $outletNama   = $this->clean($cols[3] ?? '');
        $customerNama = $this->clean($cols[4] ?? '');
        $telp         = $this->clean($cols[5] ?? '');
        $alamat       = $this->clean($cols[6] ?? '');
        $total        = $this->parseRupiah($cols[7] ?? '0');
        $dp           = $this->parseRupiah($cols[9] ?? '0');
        $sisa         = $this->parseRupiah($cols[10] ?? '0');
        $tglKirim     = $this->clean($cols[11] ?? '');
        $salesNama    = $this->clean($cols[12] ?? '');
        $catatan      = $this->clean($cols[13] ?? '');

        // ── Tentukan status pembayaran ────────────────────────────────────────
        $paymentStatus = 'unpaid';
        if ($sisa <= 0 && $total > 0) {
            $paymentStatus = 'paid';
        } elseif ($dp > 0) {
            $paymentStatus = 'partial';
        }

        // ── Cari atau buat customer ───────────────────────────────────────────
        $customerId = null;
        if ($customerNama && $customerNama !== '-') {
            $customerId = $this->getOrCreateCustomer($customerNama, $telp, $alamat);
        }

        // ── Hitung subtotal dari items ────────────────────────────────────────
        // Pakai total dari master karena lebih akurat (sudah termasuk diskon)
        $subtotal   = $total;
        $grandTotal = $total;

        // ── Catatan gabungan ─────────────────────────────────────────────────
        $notesArr = [];
        if ($outletNama)  $notesArr[] = "Outlet: {$outletNama}";
        if ($salesNama)   $notesArr[] = "Sales: {$salesNama}";
        if ($tglKirim)    $notesArr[] = "Tgl Kirim: {$tglKirim}";
        if ($catatan)     $notesArr[] = $catatan;
        $notes = implode(' | ', array_filter($notesArr));

        // ── Simpan transaksi ──────────────────────────────────────────────────
        $transaction = Transaction::create([
            'invoice_number'   => $nota,
            'customer_id'      => $customerId,
            'user_id'          => $this->defaultUserId,
            'transaction_date' => $tanggal ?? now()->toDateString(),
            'delivery_note'    => $tglKirim ?: null,
            'subtotal'         => $subtotal,
            'discount_amount'  => 0,
            'grand_total'      => $grandTotal,
            'amount_paid'      => $dp,
            'amount_remaining' => max(0, $sisa),
            'discount_type'    => 'none',
            'payment_status'   => $paymentStatus,
            'delivery_status'  => 'pending',
            'notes'            => $notes ?: null,
        ]);

        // ── Simpan items ──────────────────────────────────────────────────────
        foreach ($items as $item) {
            if (!$item['nama']) continue;

            $isFree      = str_contains($item['nama'], '**FREE');
            $namaClean   = trim(str_replace('**FREE', '', $item['nama']));
            $productId   = $this->getOrCreateProduct($namaClean, $item['satuan'], $item['harga']);
            $unitName    = strtoupper($item['satuan'] ?: 'PCS');
            $harga       = $isFree ? 0 : $item['harga'];
            $subtotalItem= $isFree ? 0 : ($item['subtotal'] > 0 ? $item['subtotal'] : $item['qty'] * $harga);

            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id'     => $productId,
                'product_name'   => $namaClean,
                'product_sku'    => $this->generateSku($namaClean),
                'unit_price'     => $harga,
                'quantity'       => max(1, $item['qty']),
                'unit_name'      => $unitName,
                'subtotal'       => $subtotalItem,
                'notes'          => $isFree ? 'Item gratis / bonus' : null,
            ]);
        }

        // ── Simpan DP sebagai payment jika ada ────────────────────────────────
        if ($dp > 0) {
            Payment::create([
                'transaction_id'    => $transaction->id,
                'payment_method_id' => $this->cashPaymentMethodId,
                'amount'            => $dp,
                'payment_date'      => $tanggal ?? now()->toDateString(),
                'reference_number'  => null,
                'notes'             => 'DP (import data lama)',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: GET OR CREATE CUSTOMER
    // ─────────────────────────────────────────────────────────────────────────

    private function getOrCreateCustomer(string $nama, string $telp, string $alamat): int
    {
        $key = strtolower(trim($nama));

        if (isset($this->customerCache[$key])) {
            return $this->customerCache[$key];
        }

        $customer = Customer::firstOrCreate(
            ['name' => $nama, 'type' => 'end_user'],
            [
                'phone'    => $telp ?: null,
                'address'  => $alamat ?: null,
                'type'     => 'end_user',
                'is_active'=> true,
            ]
        );

        $this->customerCache[$key] = $customer->id;
        return $customer->id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: GET OR CREATE PRODUCT
    // ─────────────────────────────────────────────────────────────────────────

    private function getOrCreateProduct(string $nama, string $satuan, float $harga): int
    {
        $key = strtolower(trim($nama));

        if (isset($this->productCache[$key])) {
            return $this->productCache[$key];
        }

        $unitId = strtoupper($satuan) === 'SET'
            ? $this->defaultUnitSetId
            : $this->defaultUnitPcsId;

        $sku = $this->generateSku($nama);

        // Pastikan SKU unik
        $skuFinal = $sku;
        $counter  = 1;
        while (Product::where('sku', $skuFinal)->exists()) {
            $skuFinal = $sku . '-' . $counter;
            $counter++;
        }

        $product = Product::firstOrCreate(
            ['name' => $nama, 'category_id' => $this->defaultCategoryId],
            [
                'category_id'   => $this->defaultCategoryId,
                'unit_id'       => $unitId,
                'name'          => $nama,
                'sku'           => $skuFinal,
                'selling_price' => $harga > 0 ? $harga : 0,
                'cost_price'    => 0,
                'stock_quantity'=> 0,
                'minimum_stock' => 0,
                'is_active'     => true,
            ]
        );

        $this->productCache[$key] = $product->id;
        return $product->id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: PARSE
    // ─────────────────────────────────────────────────────────────────────────

    private function parseLine(string $line): array
    {
        // Hapus BOM dan whitespace
        $line = trim($line, "\xEF\xBB\xBF\r\n ");
        return str_getcsv($line, ';', '"');
    }

    private function clean(?string $val): string
    {
        return trim($val ?? '', " \t\r\n\"");
    }

    private function parseRupiah(string $val): float
    {
        // Format: "Rp4600000.00" atau "" atau "Rp0.00"
        $clean = preg_replace('/[^0-9.]/', '', $val);
        return $clean ? (float) $clean : 0.0;
    }

    private function parseDate(string $val): ?string
    {
        // Format Access: "9/4/2022 00.00.00" atau "23/1/1900 00.00.00"
        $val = $this->clean($val);
        if (!$val) return null;

        try {
            // Ganti titik jadi titik dua di bagian waktu
            $val = preg_replace('/(\d+)\.(\d+)\.(\d+)$/', '$1:$2:$3', $val);
            $dt  = \Carbon\Carbon::createFromFormat('j/n/Y H:i:s', $val);

            // Skip tanggal 1900 (placeholder Access)
            if ($dt->year < 2000) return null;

            return $dt->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateSku(string $nama): string
    {
        // Buat SKU dari nama: ambil huruf awal tiap kata, max 20 char
        $words  = explode(' ', strtoupper($nama));
        $prefix = implode('', array_map(fn($w) => substr($w, 0, 1), array_slice($words, 0, 4)));
        $suffix = substr(md5($nama), 0, 6);
        return 'LGC-' . $prefix . '-' . $suffix; // LGC = Legacy
    }
}