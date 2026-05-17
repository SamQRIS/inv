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
        Schema::table('credit_logs', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('user_id')
                ->constrained('payment_methods')
                ->nullOnDelete();

            $table->string('reference_number')->nullable()->after('notes');
        });

        // Ubah enum type — tambah 'deposit_topup' dan 'deposit_manual_deduct'
        // SQLite tidak support ALTER COLUMN enum, jadi gunakan cara umum untuk MySQL/PostgreSQL
        // Jika pakai SQLite (development), skip bagian ini dan cukup catat di notes
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE credit_logs MODIFY COLUMN type ENUM(
                'topup',
                'deduct',
                'used',
                'refund',
                'deposit',
                'deposit_used',
                'deposit_topup',
                'deposit_manual_deduct'
            ) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('reference_number');
        });
    }
};
