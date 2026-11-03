<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A merge preserves actual box capacities, including several Not Full boxes.
        Schema::table('export_delivery_plan_items', fn (Blueprint $t) => $t->json('box_composition')->nullable());
        Schema::table('export_fulfillment_boxes', fn (Blueprint $t) => $t->boolean('merge_pending_verification')->default(false));
        Schema::table('export_not_full_requests', function (Blueprint $t) {
            $t->unsignedInteger('required_boxes')->default(1);
            $t->unique(['export_delivery_plan_item_id', 'pcs_quantity'], 'export_nf_capacity_unique');
        });
        Schema::table('export_not_full_requests', fn (Blueprint $t) => $t->dropUnique('export_nf_requirement_unique'));
        Schema::create('export_pallet_merges', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_token')->unique();
            $t->char('request_hash', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('result_pallet_id')->constrained('export_stock_pallets')->restrictOnDelete();
            $t->json('snapshot');
            $t->timestamp('created_at');
        });
        // Extend enabled workflows only; an explicitly disabled base workflow stays disabled.
        foreach (['export_operator' => 'export_stock_input', 'admin_warehouse' => 'merge_pallet'] as $role => $base) {
            $record = DB::table('role_feature_access')->where('role', $role)->first();
            if (! $record) {
                continue;
            }
            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            if (! in_array($base, $before, true) || in_array('export_merge_pallet', $before, true)) {
                continue;
            }
            $after = [...$before, 'export_merge_pallet'];
            DB::table('role_feature_access')->where('id', $record->id)->update(['permissions' => json_encode($after), 'updated_at' => now()]);
            \App\Services\AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => $role, 'permissions' => $before], ['role' => $role, 'permissions' => $after], 'Migration mengaktifkan Merge Pallet Ekspor pada alur gudang yang telah diizinkan.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rekonsiliasi komposisi box dan histori merge ekspor sebelum rollback.');
    }
};
