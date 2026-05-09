<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            $table->boolean('allow_partial')->default(false);
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE delivery_orders MODIFY status ENUM('pending', 'approved', 'rejected', 'processing', 'partial', 'completed', 'correction', 'deleted') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE delivery_orders MODIFY status ENUM('pending', 'approved', 'rejected', 'processing', 'completed', 'correction', 'deleted') NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            $table->dropColumn('allow_partial');
        });
    }
};