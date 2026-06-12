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
        Schema::table('delivery_items', function (Blueprint $table) {
            // Tandai apakah pengiriman ini untuk display
            $table->boolean('is_display')
                ->default(false)
                ->after('qty_delivered');

            // Lokasi display untuk pengiriman ini
            // (bisa berbeda per delivery item meski 1 transaksi)
            $table->string('display_location')
                ->nullable()
                ->after('is_display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_display',
                'display_location'
            ]);
        });
    }
};
