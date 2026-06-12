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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained('product_fabrics')->nullOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();

            // Unique: satu harga per kombinasi produk+ukuran+kain
            $table->unique(['product_id', 'size_id', 'fabric_id'], 'unique_product_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
