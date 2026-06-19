<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_locks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('operation_locks')->insertOrIgnore([
            'name' => 'global_delivery_pick',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $duplicatePalletIds = DB::table('stock_locations')
            ->select('pallet_id')
            ->whereNotNull('pallet_id')
            ->groupBy('pallet_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pallet_id');

        foreach ($duplicatePalletIds as $palletId) {
            $keepId = DB::table('stock_locations')
                ->where('pallet_id', $palletId)
                ->max('id');

            DB::table('stock_locations')
                ->where('pallet_id', $palletId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        DB::table('stock_locations')
            ->whereNull('master_location_id')
            ->where('warehouse_location', '!=', 'Unknown')
            ->orderBy('id')
            ->get(['id', 'warehouse_location'])
            ->each(function ($stockLocation): void {
                $masterLocationId = DB::table('master_locations')
                    ->where('code', $stockLocation->warehouse_location)
                    ->value('id');

                if ($masterLocationId) {
                    DB::table('stock_locations')
                        ->where('id', $stockLocation->id)
                        ->update([
                            'master_location_id' => $masterLocationId,
                            'updated_at' => now(),
                        ]);
                }
            });

        $duplicateMasterLocationIds = DB::table('stock_locations')
            ->select('master_location_id')
            ->whereNotNull('master_location_id')
            ->groupBy('master_location_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('master_location_id');

        foreach ($duplicateMasterLocationIds as $masterLocationId) {
            $keepId = DB::table('stock_locations')
                ->where('master_location_id', $masterLocationId)
                ->max('id');

            DB::table('stock_locations')
                ->where('master_location_id', $masterLocationId)
                ->where('id', '!=', $keepId)
                ->update([
                    'master_location_id' => null,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('stock_locations', function (Blueprint $table) {
            $table->unique('pallet_id', 'stock_locations_pallet_id_unique');
            $table->unique('master_location_id', 'stock_locations_master_location_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropUnique('stock_locations_pallet_id_unique');
            $table->dropUnique('stock_locations_master_location_id_unique');
        });

        Schema::dropIfExists('operation_locks');
    }
};
