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
        Schema::create('credit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
 
            $table->enum('type', ['topup', 'deduct', 'used', 'refund']);
            // topup  = tambah credit limit
            // deduct = kurangi credit limit (manual)
            // used   = terpakai oleh transaksi (otomatis)
            // refund = dikembalikan saat transaksi lunas/batal
 
            $table->decimal('amount', 15, 2);          // selalu positif
            $table->decimal('credit_before', 15, 2);   // limit sebelum
            $table->decimal('credit_after', 15, 2);    // limit sesudah
 
            $table->string('reference_type')->nullable(); // 'transaction', 'manual'
            $table->unsignedBigInteger('reference_id')->nullable();
 
            $table->text('notes')->nullable();
            $table->timestamps();
 
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_logs');
    }
};
