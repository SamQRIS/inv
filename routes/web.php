<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/transaction/{transaction}/invoice', [DocumentController::class, 'invoice'])
        ->name('transaction.invoice');
 
    Route::get('/delivery/{delivery}/surat-jalan', [DocumentController::class, 'suratJalan'])
        ->name('delivery.surat-jalan');
});

Route::get('/delivery/{delivery}/print', function ($delivery) {
    $delivery = \App\Models\Delivery::with([
        'transaction.customer',
        'transaction.payments.paymentMethod',
        'items.product.unit',
        'items.transactionItem',
        'user',
    ])->findOrFail($delivery);

    return view('print.surat-jalan', compact('delivery'));
})->middleware(['auth'])->name('surat-jalan.print');

// Print via QZ Tray (ESC/P raw) — BARU
Route::get('/delivery/{delivery}/print-qztray',
    [DocumentController::class, 'printQzTray']
)->name('delivery.print.qztray');

Route::get('/qztray/sign', function (\Illuminate\Http\Request $request) {
    $toSign     = $request->query('request');
    $privateKey = file_get_contents(storage_path('app/private/private-key.pem'));
 
    // Sign dengan SHA-512
    $signature  = '';
    openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA512);
 
    return response(base64_encode($signature))
        ->header('Content-Type', 'text/plain');
})->middleware('auth')->name('qztray.sign');
 
// ── QZ Tray Certificate (public, boleh diakses) ──────────────
Route::get('/qztray/certificate', function () {
    return response(file_get_contents(storage_path('app/private/digital-certificate.txt')))
        ->header('Content-Type', 'text/plain')
        ->header('Cache-Control', 'no-store');
})->name('qztray.certificate');