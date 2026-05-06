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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // "Gudang Utama", "Toko Depok", dll
            $table->string('code')->unique();          // "GDG-01", "TOKO-DPK"
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('pic')->nullable();         // Person In Charge
            $table->boolean('is_default')->default(false); // gudang utama / default transaksi
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
