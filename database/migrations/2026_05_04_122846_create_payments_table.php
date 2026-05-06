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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->json('installment_detail')->nullable();
            /*
             * installment_detail example:
             * {
             *   "provider": "Akulaku",
             *   "tenor": 12,
             *   "monthly_amount": 100000,
             *   "down_payment": 0,
             *   "contract_number": "AKL-2024-xxxxx"
             * }
             */
            $table->text('notes')->nullable();
            $table->timestamps();
 
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
