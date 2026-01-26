<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add index untuk better join performance antara stock_locations dan master_locations
        Schema::table('stock_locations', function (Blueprint $table) {
            // Add index jika belum ada
            if (!Schema::hasIndex('stock_locations', 'stock_locations_master_location_id_index')) {
                $table->index('master_location_id');
            }
        });

        // Add unique constraint pada master_locations.current_pallet_id
        // Jadi hanya satu location yang bisa hold satu pallet
        Schema::table('master_locations', function (Blueprint $table) {
            if (!Schema::hasIndex('master_locations', 'master_locations_current_pallet_id_unique')) {
                $table->unique('current_pallet_id', 'master_locations_current_pallet_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            if (Schema::hasIndex('stock_locations', 'stock_locations_master_location_id_index')) {
                $table->dropIndex('stock_locations_master_location_id_index');
            }
        });

        Schema::table('master_locations', function (Blueprint $table) {
            if (Schema::hasIndex('master_locations', 'master_locations_current_pallet_id_unique')) {
                $table->dropUnique('master_locations_current_pallet_id_unique');
            }
        });
    }
};
