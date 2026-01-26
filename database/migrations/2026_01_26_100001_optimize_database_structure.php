<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix stock_locations - add proper warehouse_location reference
        Schema::table('stock_locations', function (Blueprint $table) {
            // Add foreign key ke master_locations untuk enforce data integrity
            $table->foreignId('master_location_id')->nullable()->after('pallet_id')->constrained('master_locations')->nullOnDelete();
        });

        // Fix boxes - add column references untuk tracking
        Schema::table('boxes', function (Blueprint $table) {
            // Add part_name jika belum ada (mungkin sudah ada dari migration sebelumnya)
            if (!Schema::hasColumn('boxes', 'part_name')) {
                $table->string('part_name')->nullable()->after('part_number');
            }
        });

        // Fix pallet_items - standardisasi quantity fields
        Schema::table('pallet_items', function (Blueprint $table) {
            // Ensure ini unique per pallet
            $table->unique(['pallet_id', 'part_number']);
        });

        // Add soft deletes untuk audit trail
        Schema::table('stock_withdrawals', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_withdrawals', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('boxes', function (Blueprint $table) {
            if (!Schema::hasColumn('boxes', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('stock_inputs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_inputs', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_orders', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            if (Schema::hasColumn('stock_locations', 'master_location_id')) {
                $table->dropForeignKeyIfExists('stock_locations_master_location_id_foreign');
                $table->dropColumn('master_location_id');
            }
        });

        Schema::table('pallet_items', function (Blueprint $table) {
            $table->dropUnique(['pallet_id', 'part_number']);
        });

        Schema::table('stock_withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('stock_withdrawals', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('boxes', function (Blueprint $table) {
            if (Schema::hasColumn('boxes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('stock_inputs', function (Blueprint $table) {
            if (Schema::hasColumn('stock_inputs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
