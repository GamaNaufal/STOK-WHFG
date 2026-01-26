<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standardize enum columns - sebelumnya pakai string
        Schema::table('stock_withdrawals', function (Blueprint $table) {
            // Drop lama dan create baru dengan enum
            if (Schema::hasColumn('stock_withdrawals', 'status')) {
                $table->dropColumn('status');
            }
            // Change dengan proper enum
            $table->enum('status', ['completed', 'reversed', 'cancelled'])->default('completed')->after('warehouse_location');
        });

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_sessions', 'status')) {
                $table->dropColumn('status');
            }
            $table->enum('status', ['pending', 'scanning', 'blocked', 'approved', 'completed', 'cancelled'])->default('pending')->after('created_by');
        });

        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_items', 'status')) {
                $table->dropColumn('status');
            }
            $table->enum('status', ['pending', 'scanned', 'verified', 'failed'])->default('pending')->after('pcs_quantity');
        });

        Schema::table('delivery_scan_issues', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_scan_issues', 'status')) {
                $table->dropColumn('status');
            }
            $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending')->after('reason');
        });

        // Add user_id ke delivery_pick_items untuk tracking siapa yang scan
        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_pick_items', 'scanned_by')) {
                $table->foreignId('scanned_by')->nullable()->after('scanned_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('stock_withdrawals', 'status')) {
                $table->dropColumn('status');
            }
            $table->string('status')->default('completed')->after('warehouse_location');
        });

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_sessions', 'status')) {
                $table->dropColumn('status');
            }
            $table->string('status')->default('pending')->after('created_by');
        });

        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_items', 'status')) {
                $table->dropColumn('status');
            }
            $table->string('status')->default('pending')->after('pcs_quantity');
        });

        Schema::table('delivery_scan_issues', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_scan_issues', 'status')) {
                $table->dropColumn('status');
            }
            $table->string('status')->default('pending')->after('reason');
        });

        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_items', 'scanned_by')) {
                $table->dropForeignKeyIfExists('delivery_pick_items_scanned_by_foreign');
                $table->dropColumn('scanned_by');
            }
        });
    }
};
