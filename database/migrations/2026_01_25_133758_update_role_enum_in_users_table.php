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
        // Using raw SQL to modify ENUM column (MySQL specific)
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'warehouse_operator', 'admin_warehouse', 'sales', 'ppc') NOT NULL DEFAULT 'warehouse_operator'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'warehouse_operator', 'admin_warehouse') NOT NULL DEFAULT 'warehouse_operator'");
    }
};
