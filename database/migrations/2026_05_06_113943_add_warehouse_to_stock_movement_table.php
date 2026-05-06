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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('product_id')
                ->constrained()->nullOnDelete();
 
            // Transfer antar gudang: warehouse_id = asal, to_warehouse_id = tujuan
            $table->foreignId('to_warehouse_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('warehouses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            //
        });
    }
};
