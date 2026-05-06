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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('type', ['do', 'end_user'])->default('end_user');
            // DO Customer specific fields
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('credit_used', 15, 2)->default(0);
            $table->json('default_discount')->nullable();   // JSON diskon default untuk DO
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
 
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
