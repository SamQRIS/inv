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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // $table->foreignId('warehouse_id')->constrained()->nullOnDelete();
            $table->string('product_name');     // snapshot nama produk
            $table->string('product_sku');      // snapshot SKU
            $table->decimal('unit_price', 15, 2);
            $table->integer('quantity');
            $table->string('unit_name');        // snapshot unit
            $table->decimal('subtotal', 15, 2); // qty * unit_price (NO item-level discount)
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
