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
        Schema::table('delivery_status_enum', function (Blueprint $table) {
            // Tambah 'cancelled' ke delivery_status di transactions
            DB::statement("ALTER TABLE transactions MODIFY COLUMN delivery_status ENUM('pending','partial','delivered','cancelled') DEFAULT 'pending'");

            // Tambah 'cancelled' ke status di deliveries
            DB::statement("ALTER TABLE deliveries MODIFY COLUMN status ENUM('pending','partial','completed','cancelled') DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_status_enum', function (Blueprint $table) {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN delivery_status ENUM('pending','partial','delivered') DEFAULT 'pending'");
            DB::statement("ALTER TABLE deliveries MODIFY COLUMN status ENUM('pending','partial','completed') DEFAULT 'pending'");
        });
    }
};
