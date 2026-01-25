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
        // First, update any invalid role values to default
        DB::statement("UPDATE users SET role = 'warehouse_operator' WHERE role NOT IN ('admin', 'warehouse_operator', 'admin_warehouse', 'sales', 'ppc', 'supervisi')");

        // Then modify the enum to include all values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'warehouse_operator', 'admin_warehouse', 'sales', 'ppc', 'supervisi') NOT NULL DEFAULT 'warehouse_operator'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'warehouse_operator', 'admin_warehouse') NOT NULL DEFAULT 'warehouse_operator'");
    }
};
