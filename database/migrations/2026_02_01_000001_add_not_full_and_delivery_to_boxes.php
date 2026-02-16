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
            if (!Schema::hasColumn('boxes', 'is_not_full')) {
                $table->boolean('is_not_full')->default(false)->after('is_withdrawn');
            }

            if (!Schema::hasColumn('boxes', 'not_full_reason')) {
                $table->text('not_full_reason')->nullable()->after('is_not_full');
            }

            if (!Schema::hasColumn('boxes', 'assigned_delivery_order_id')) {
                $table->foreignId('assigned_delivery_order_id')
                    ->nullable()
                    ->after('not_full_reason')
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

            if (Schema::hasColumn('boxes', 'not_full_reason')) {
                $table->dropColumn('not_full_reason');
            }

            if (Schema::hasColumn('boxes', 'is_not_full')) {
                $table->dropColumn('is_not_full');
            }
        });
    }
};
