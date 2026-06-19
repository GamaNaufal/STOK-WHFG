<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('type', [
                'stock_input',
                'stock_withdrawal',
                'delivery_pickup',
                'delivery_redo',
                'delivery_assignment',
                'pallet_merged',
                'box_pallet_moved',
                'other',
            ])->change();
        });
    }
};
