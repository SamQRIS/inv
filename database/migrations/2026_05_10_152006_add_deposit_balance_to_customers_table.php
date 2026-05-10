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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('deposit_balance', 15, 2)->default(0)->after('credit_used');
        });
         // Tambah type 'deposit' dan 'deposit_used' ke credit_logs
        // MySQL: harus drop dulu lalu recreate enum
        DB::statement("ALTER TABLE credit_logs MODIFY COLUMN type ENUM('topup','deduct','used','refund','deposit','deposit_used')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('deposit_balance');
        });
        // Hapus type 'deposit' dan 'deposit_used' dari credit_logs
        // MySQL: harus drop dulu lalu recreate enum
        DB::statement("ALTER TABLE credit_logs MODIFY COLUMN type ENUM('topup','deduct','used','refund')");
    }
};
