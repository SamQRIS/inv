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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // $table->foreignId('warehouse_id')->constrained()->nullOnDelete();
            // $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete(); // untuk transfer antar gudang
            $table->enum('type', ['in', 'out', 'adjustment'])->default('in');
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reference_type')->nullable(); // transaction, shipment, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();
 
            $table->index(['product_id', 'moved_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
