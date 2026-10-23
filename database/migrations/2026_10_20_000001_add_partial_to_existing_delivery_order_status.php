<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh databases already have this value in the original table migration.
        if (DB::getDriverName() !== 'mysql' || DB::connection()->pretending()) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM `delivery_orders` LIKE 'status'");
        $type = $column->Type ?? '';

        if (! str_starts_with($type, 'enum(') || ! str_ends_with($type, ')')) {
            throw new RuntimeException('delivery_orders.status must be a MySQL enum.');
        }

        if (str_contains($type, "'partial'")) {
            return;
        }

        // Append without removing any status supported by an existing installation.
        $typeWithPartial = substr($type, 0, -1).",'partial')";

        DB::statement("ALTER TABLE `delivery_orders` MODIFY COLUMN `status` {$typeWithPartial} NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Existing parent orders may use partial; narrowing the enum would corrupt them.
    }
};
