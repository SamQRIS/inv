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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // kasir
            $table->date('transaction_date');
 
            // Delivery date: flexible (tanggal pasti atau teks)
            $table->date('delivery_date')->nullable();
            $table->string('delivery_note')->nullable();   // "kirim bertahap", "urgent", dll
 
            // Totals
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_remaining', 15, 2)->default(0);
 
            // Advanced discount system
            $table->enum('discount_type', ['none', 'percentage', 'nominal', 'mixed'])->default('none');
            $table->json('discount_json')->nullable();
            /*
             * discount_json examples:
             * Single: [{"type":"percent","value":5}]
             * Tiered: [{"type":"percent","value":5},{"type":"percent","value":1}]
             * Mixed:  [{"type":"percent","value":5},{"type":"nominal","value":200000}]
             */
 
            // Status
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('delivery_status', ['pending', 'partial', 'delivered'])->default('pending');
 
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
 
            $table->index(['payment_status', 'delivery_status']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
