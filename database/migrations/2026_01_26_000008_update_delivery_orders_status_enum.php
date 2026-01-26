<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE delivery_orders MODIFY status ENUM('pending','approved','rejected','processing','completed','correction') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE delivery_orders MODIFY status ENUM('pending','approved','rejected','completed','correction') NOT NULL DEFAULT 'pending'");
    }
};