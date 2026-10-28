<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (DB::connection()->pretending()) {
                return;
            }

            $column = DB::selectOne("SHOW COLUMNS FROM `users` LIKE 'role'");
            $type = $column->Type ?? '';

            if (! str_starts_with($type, 'enum(') || ! str_ends_with($type, ')')) {
                throw new RuntimeException('users.role must be a MySQL enum.');
            }

            if (! str_contains($type, "'export_operator'")) {
                // Preserve all existing roles and accounts on deployed installations.
                $expandedType = substr($type, 0, -1).",'export_operator')";
                DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` {$expandedType} NOT NULL DEFAULT 'warehouse_operator'");
            }

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'warehouse_operator', 'admin_warehouse', 'sales', 'ppc', 'supervisi', 'export_operator'])
                ->default('warehouse_operator')->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded enum so rollback does not corrupt export operator accounts.
    }
};
