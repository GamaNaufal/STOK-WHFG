<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index untuk queries yang sering dijalankan
        Schema::table('boxes', function (Blueprint $table) {
            if (!Schema::hasIndex('boxes', 'boxes_part_number_index')) {
                $table->index('part_number'); // Sering di-filter by part_number
            }
            if (!Schema::hasIndex('boxes', 'boxes_box_number_index')) {
                $table->index('box_number'); // Unique tapi perlu index untuk join
            }
            if (!Schema::hasIndex('boxes', 'boxes_user_id_index')) {
                $table->index('user_id');
            }
            if (!Schema::hasIndex('boxes', 'boxes_created_at_index')) {
                $table->index('created_at'); // Untuk sorting/filtering by date
            }
        });

        Schema::table('stock_withdrawals', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_part_number_index')) {
                $table->index('part_number');
            }
            if (!Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_user_id_index')) {
                $table->index('user_id');
            }
            if (!Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_withdrawal_batch_id_index')) {
                $table->index('withdrawal_batch_id');
            }
            if (!Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_withdrawn_at_index')) {
                $table->index('withdrawn_at');
            }
        });

        Schema::table('stock_locations', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_locations', 'stock_locations_warehouse_location_index')) {
                $table->index('warehouse_location');
            }
            if (!Schema::hasIndex('stock_locations', 'stock_locations_pallet_id_index')) {
                $table->index('pallet_id');
            }
            if (!Schema::hasIndex('stock_locations', 'stock_locations_stored_at_index')) {
                $table->index('stored_at');
            }
        });

        Schema::table('stock_inputs', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_inputs', 'stock_inputs_pallet_id_index')) {
                $table->index('pallet_id');
            }
            if (!Schema::hasIndex('stock_inputs', 'stock_inputs_user_id_index')) {
                $table->index('user_id');
            }
            if (!Schema::hasIndex('stock_inputs', 'stock_inputs_stored_at_index')) {
                $table->index('stored_at');
            }
        });

        Schema::table('pallet_items', function (Blueprint $table) {
            if (!Schema::hasIndex('pallet_items', 'pallet_items_pallet_id_index')) {
                $table->index('pallet_id');
            }
            if (!Schema::hasIndex('pallet_items', 'pallet_items_part_number_index')) {
                $table->index('part_number');
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (!Schema::hasIndex('delivery_orders', 'delivery_orders_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('delivery_orders', 'delivery_orders_sales_user_id_index')) {
                $table->index('sales_user_id');
            }
            if (!Schema::hasIndex('delivery_orders', 'delivery_orders_delivery_date_index')) {
                $table->index('delivery_date');
            }
            if (!Schema::hasIndex('delivery_orders', 'delivery_orders_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (!Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_delivery_order_id_index')) {
                $table->index('delivery_order_id');
            }
            if (!Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_created_by_index')) {
                $table->index('created_by');
            }
            if (!Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_started_at_index')) {
                $table->index('started_at');
            }
        });

        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (!Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_pick_session_id_index')) {
                $table->index('pick_session_id');
            }
            if (!Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_part_number_index')) {
                $table->index('part_number');
            }
        });

        Schema::table('delivery_scan_issues', function (Blueprint $table) {
            if (!Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_pick_session_id_index')) {
                $table->index('pick_session_id');
            }
            if (!Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('master_locations', function (Blueprint $table) {
            if (!Schema::hasIndex('master_locations', 'master_locations_is_occupied_index')) {
                $table->index('is_occupied');
            }
            if (!Schema::hasIndex('master_locations', 'master_locations_current_pallet_id_index')) {
                $table->index('current_pallet_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            if (Schema::hasIndex('boxes', 'boxes_part_number_index')) {
                $table->dropIndex('boxes_part_number_index');
            }
            if (Schema::hasIndex('boxes', 'boxes_box_number_index')) {
                $table->dropIndex('boxes_box_number_index');
            }
            if (Schema::hasIndex('boxes', 'boxes_user_id_index')) {
                $table->dropIndex('boxes_user_id_index');
            }
            if (Schema::hasIndex('boxes', 'boxes_created_at_index')) {
                $table->dropIndex('boxes_created_at_index');
            }
        });

        Schema::table('stock_withdrawals', function (Blueprint $table) {
            if (Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_status_index')) {
                $table->dropIndex('stock_withdrawals_status_index');
            }
            if (Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_part_number_index')) {
                $table->dropIndex('stock_withdrawals_part_number_index');
            }
            if (Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_user_id_index')) {
                $table->dropIndex('stock_withdrawals_user_id_index');
            }
            if (Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_withdrawal_batch_id_index')) {
                $table->dropIndex('stock_withdrawals_withdrawal_batch_id_index');
            }
            if (Schema::hasIndex('stock_withdrawals', 'stock_withdrawals_withdrawn_at_index')) {
                $table->dropIndex('stock_withdrawals_withdrawn_at_index');
            }
        });

        Schema::table('stock_locations', function (Blueprint $table) {
            if (Schema::hasIndex('stock_locations', 'stock_locations_warehouse_location_index')) {
                $table->dropIndex('stock_locations_warehouse_location_index');
            }
            if (Schema::hasIndex('stock_locations', 'stock_locations_pallet_id_index')) {
                $table->dropIndex('stock_locations_pallet_id_index');
            }
            if (Schema::hasIndex('stock_locations', 'stock_locations_stored_at_index')) {
                $table->dropIndex('stock_locations_stored_at_index');
            }
        });

        Schema::table('stock_inputs', function (Blueprint $table) {
            if (Schema::hasIndex('stock_inputs', 'stock_inputs_pallet_id_index')) {
                $table->dropIndex('stock_inputs_pallet_id_index');
            }
            if (Schema::hasIndex('stock_inputs', 'stock_inputs_user_id_index')) {
                $table->dropIndex('stock_inputs_user_id_index');
            }
            if (Schema::hasIndex('stock_inputs', 'stock_inputs_stored_at_index')) {
                $table->dropIndex('stock_inputs_stored_at_index');
            }
        });

        Schema::table('pallet_items', function (Blueprint $table) {
            if (Schema::hasIndex('pallet_items', 'pallet_items_pallet_id_index')) {
                $table->dropIndex('pallet_items_pallet_id_index');
            }
            if (Schema::hasIndex('pallet_items', 'pallet_items_part_number_index')) {
                $table->dropIndex('pallet_items_part_number_index');
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (Schema::hasIndex('delivery_orders', 'delivery_orders_status_index')) {
                $table->dropIndex('delivery_orders_status_index');
            }
            if (Schema::hasIndex('delivery_orders', 'delivery_orders_sales_user_id_index')) {
                $table->dropIndex('delivery_orders_sales_user_id_index');
            }
            if (Schema::hasIndex('delivery_orders', 'delivery_orders_delivery_date_index')) {
                $table->dropIndex('delivery_orders_delivery_date_index');
            }
            if (Schema::hasIndex('delivery_orders', 'delivery_orders_created_at_index')) {
                $table->dropIndex('delivery_orders_created_at_index');
            }
        });

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_status_index')) {
                $table->dropIndex('delivery_pick_sessions_status_index');
            }
            if (Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_delivery_order_id_index')) {
                $table->dropIndex('delivery_pick_sessions_delivery_order_id_index');
            }
            if (Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_created_by_index')) {
                $table->dropIndex('delivery_pick_sessions_created_by_index');
            }
            if (Schema::hasIndex('delivery_pick_sessions', 'delivery_pick_sessions_started_at_index')) {
                $table->dropIndex('delivery_pick_sessions_started_at_index');
            }
        });

        Schema::table('delivery_pick_items', function (Blueprint $table) {
            if (Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_pick_session_id_index')) {
                $table->dropIndex('delivery_pick_items_pick_session_id_index');
            }
            if (Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_status_index')) {
                $table->dropIndex('delivery_pick_items_status_index');
            }
            if (Schema::hasIndex('delivery_pick_items', 'delivery_pick_items_part_number_index')) {
                $table->dropIndex('delivery_pick_items_part_number_index');
            }
        });

        Schema::table('delivery_scan_issues', function (Blueprint $table) {
            if (Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_pick_session_id_index')) {
                $table->dropIndex('delivery_scan_issues_pick_session_id_index');
            }
            if (Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_status_index')) {
                $table->dropIndex('delivery_scan_issues_status_index');
            }
            if (Schema::hasIndex('delivery_scan_issues', 'delivery_scan_issues_created_at_index')) {
                $table->dropIndex('delivery_scan_issues_created_at_index');
            }
        });

        Schema::table('master_locations', function (Blueprint $table) {
            if (Schema::hasIndex('master_locations', 'master_locations_is_occupied_index')) {
                $table->dropIndex('master_locations_is_occupied_index');
            }
            if (Schema::hasIndex('master_locations', 'master_locations_current_pallet_id_index')) {
                $table->dropIndex('master_locations_current_pallet_id_index');
            }
        });
    }
};
