<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum type column to add 'pallet_merged'
        DB::statement("ALTER TABLE audit_logs MODIFY COLUMN type ENUM('stock_input', 'stock_withdrawal', 'delivery_pickup', 'delivery_redo', 'pallet_merged', 'other')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE audit_logs MODIFY COLUMN type ENUM('stock_input', 'stock_withdrawal', 'delivery_pickup', 'delivery_redo', 'other')");
    }
};
