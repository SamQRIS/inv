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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');           // created, updated, deleted, restored
            $table->string('model_type');       // App\Models\Transaction
            $table->unsignedBigInteger('model_id');
            $table->string('model_label')->nullable(); // invoice_number, do_number, dll
            $table->json('old_values')->nullable();    // nilai sebelum
            $table->json('new_values')->nullable();    // nilai sesudah
            $table->string('ip_address')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
 
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'logged_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
