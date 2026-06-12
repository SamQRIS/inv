<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // Apakah item ini untuk keperluan display/konsinyasi?
            $table->boolean('is_display')
                ->default(false)
                ->after('qty_backorder');

            // Lokasi display — nama toko, pameran, dll
            // Contoh: "Toko Arrabon Malang", "Pameran JCC Hall A"
            $table->string('display_location')
                ->nullable()
                ->after('is_display');

            // Status konsinyasi item ini
            // pending  = barang sudah dikirim, belum ada info terjual/retur
            // sold     = terjual di lokasi display
            // returned = barang dikembalikan ke gudang
            $table->enum('display_status', ['pending', 'sold', 'returned'])
                ->nullable() // null = bukan item display
                ->after('display_location');

            // Qty yang sudah terjual dari lokasi display
            $table->integer('qty_display_sold')
                ->default(0)
                ->after('display_status');

            // Qty yang diretur dari lokasi display
            $table->integer('qty_display_returned')
                ->default(0)
                ->after('qty_display_sold');

            // Tanggal konfirmasi terjual / retur
            $table->date('display_confirmed_at')
                ->nullable()
                ->after('qty_display_returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_display',
                'display_location',
                'display_status',
                'qty_display_sold',
                'qty_display_returned',
                'display_confirmed_at'
            ]);
        });
    }
};
