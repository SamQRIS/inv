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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ORD-20260524-001
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('target_date')->nullable();     // Target selesai produksi
            $table->string('delivery_address')->nullable();
            $table->text('production_notes')->nullable(); // Catatan untuk produksi
            $table->text('customer_notes')->nullable();   // Catatan dari customer WA
            $table->enum('status', ['draft', 'confirmed', 'in_production', 'done'])
                ->default('confirmed');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
