<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tambah status baru di payment_status dan delivery_status
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('unpaid','partial','paid','void','cancelled') DEFAULT 'unpaid'");
 
            // Kolom tambahan untuk void/cancel
            $table->string('cancellation_reason')->nullable()->after('notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Kembalikan ke status semula
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid'");
 
            // Hapus kolom tambahan
            $table->dropColumn(['cancellation_reason', 'cancelled_at', 'cancelled_by']);
        });
    }
};
