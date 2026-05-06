<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/transaction/{transaction}/invoice', [DocumentController::class, 'invoice'])
        ->name('transaction.invoice');
 
    Route::get('/delivery/{delivery}/surat-jalan', [DocumentController::class, 'suratJalan'])
        ->name('delivery.surat-jalan');
});
