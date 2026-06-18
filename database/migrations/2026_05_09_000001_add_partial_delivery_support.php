<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Historical no-op. Status "partial" is defined in the base delivery
        // migration and is retained exclusively for parent split orders.
    }

    public function down(): void
    {
        //
    }
};
