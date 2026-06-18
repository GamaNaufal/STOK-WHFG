<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('delivery_order_items')
            ->select('delivery_order_id', 'part_number')
            ->groupBy('delivery_order_id', 'part_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $items = DB::table('delivery_order_items')
                ->where('delivery_order_id', $duplicate->delivery_order_id)
                ->where('part_number', $duplicate->part_number)
                ->orderBy('id')
                ->get();

            $keeper = $items->first();
            if (!$keeper) {
                continue;
            }

            DB::table('delivery_order_items')
                ->where('id', $keeper->id)
                ->update([
                    'quantity' => (int) $items->sum('quantity'),
                    'fulfilled_quantity' => (int) $items->sum('fulfilled_quantity'),
                    'updated_at' => now(),
                ]);

            DB::table('delivery_order_items')
                ->whereIn('id', $items->skip(1)->pluck('id')->all())
                ->delete();
        }

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->unique(
                ['delivery_order_id', 'part_number'],
                'delivery_order_items_order_part_unique'
            );
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE delivery_pick_sessions MODIFY status ENUM('pending', 'scanning', 'blocked', 'approved', 'stale', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropUnique('delivery_order_items_order_part_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE delivery_pick_sessions MODIFY status ENUM('pending', 'scanning', 'blocked', 'approved', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
