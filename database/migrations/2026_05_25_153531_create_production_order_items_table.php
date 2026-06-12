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
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');           // Nama produk: DIVAN + HEADBOARD
            $table->string('size')->nullable();       // Ukuran: 120, 160, 180x200
            $table->string('color')->nullable();      // Warna/Motif: HITAM, PUTIH, BIRU
            $table->string('headboard_type')->nullable(); // Sandaran/Tipe: VILUMA, PALLADIUM
            $table->integer('quantity')->default(1);
            $table->text('item_notes')->nullable();   // Keterangan tambahan: URGENT, kaki chrome
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
    }
};
