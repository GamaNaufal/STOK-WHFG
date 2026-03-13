<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_pick_items')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement(
                    'DELETE FROM delivery_pick_items
                     WHERE id IN (
                        SELECT newer.id
                        FROM delivery_pick_items AS newer
                        INNER JOIN delivery_pick_items AS older
                            ON newer.pick_session_id = older.pick_session_id
                           AND newer.box_id = older.box_id
                           AND newer.id > older.id
                     )'
                );
            } else {
                DB::statement(
                    'DELETE newer
                     FROM delivery_pick_items AS newer
                     INNER JOIN delivery_pick_items AS older
                        ON newer.pick_session_id = older.pick_session_id
                       AND newer.box_id = older.box_id
                       AND newer.id > older.id'
                );
            }

            // Unique index delivery_pick_items_session_box_unique is defined
            // in base migration 2026_01_26_000005_create_delivery_pick_items_table.
        }

        if (Schema::hasTable('stock_withdrawals')) {
            Schema::table('stock_withdrawals', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_withdrawals', 'pick_session_id')) {
                    $table->foreignId('pick_session_id')
                        ->nullable()
                        ->after('withdrawal_batch_id')
                        ->constrained('delivery_pick_sessions')
                        ->nullOnDelete();
                }
            });

            Schema::table('stock_withdrawals', function (Blueprint $table) {
                $table->unique(['pick_session_id', 'box_id'], 'stock_withdrawals_session_box_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_withdrawals')) {
            Schema::table('stock_withdrawals', function (Blueprint $table) {
                $table->dropUnique('stock_withdrawals_session_box_unique');
                if (Schema::hasColumn('stock_withdrawals', 'pick_session_id')) {
                    $table->dropConstrainedForeignId('pick_session_id');
                }
            });
        }

        // No rollback needed for delivery_pick_items unique index here,
        // because it belongs to the base create migration.
    }
};
