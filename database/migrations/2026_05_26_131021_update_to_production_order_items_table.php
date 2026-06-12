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
        Schema::table('production_order_items', function (Blueprint $table) {
            // Hapus kolom lama jika ada
            if (Schema::hasColumn('production_order_items', 'size')) {
                $table->dropColumn('size');
            }
            if (Schema::hasColumn('production_order_items', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('production_order_items', 'headboard_type')) {
                $table->dropColumn('headboard_type');
            }

            // Tambah FK baru
            $table->foreignId('product_id')->nullable()->after('production_order_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('product_id')
                ->constrained('product_sizes')->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->after('size_id')
                ->constrained('product_fabrics')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->after('fabric_id')
                ->constrained('product_colors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_order_items', function (Blueprint $table) {
            //
        });
    }
};
