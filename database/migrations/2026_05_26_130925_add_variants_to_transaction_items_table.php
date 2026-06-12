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
            // product_id sudah ada, ubah jadi nullable agar bisa input manual
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
        Schema::table('transaction_items', function (Blueprint $table) {
            //
        });
    }
};
