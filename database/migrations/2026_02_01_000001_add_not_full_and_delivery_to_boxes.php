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
        if (!Schema::hasTable('boxes')) {
            return;
        }

        Schema::table('boxes', function (Blueprint $table) {
            if (!Schema::hasColumn('boxes', 'assigned_delivery_order_id')) {
                $table->foreignId('assigned_delivery_order_id')
                    ->nullable()
                    ->constrained('delivery_orders')
                    ->nullOnDelete();

                $table->index('assigned_delivery_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('boxes')) {
            return;
        }

        Schema::table('boxes', function (Blueprint $table) {
            if (Schema::hasColumn('boxes', 'assigned_delivery_order_id')) {
                try {
                    $table->dropForeign(['assigned_delivery_order_id']);
                } catch (\Throwable $e) {
                }

                try {
                    $table->dropIndex(['assigned_delivery_order_id']);
                } catch (\Throwable $e) {
                }

                $table->dropColumn('assigned_delivery_order_id');
            }
        });
    }
};
