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
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('minimum_stock')->default(0); // minimum per gudang
            $table->timestamps();
 
            $table->unique(['product_id', 'warehouse_id']);
            $table->index('warehouse_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
