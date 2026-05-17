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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_deposit')->default(false)->after('is_installment');
        });

        // Insert payment method khusus Deposit jika belum ada
        if (!DB::table('payment_methods')->where('code', 'deposit')->exists()) {
            DB::table('payment_methods')->insert([
                'name'         => 'Deposit',
                'code'         => 'deposit',
                'is_installment' => false,
                'is_deposit'   => true,
                'is_active'    => true,
                'sort_order'   => 99,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_deposit');
        });
    }
};
