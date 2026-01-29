<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE audit_logs MODIFY COLUMN type ENUM('stock_input', 'stock_withdrawal', 'delivery_pickup', 'delivery_redo', 'pallet_merged', 'box_pallet_moved', 'other')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE audit_logs MODIFY COLUMN type ENUM('stock_input', 'stock_withdrawal', 'delivery_pickup', 'delivery_redo', 'pallet_merged', 'other')");
    }
};
