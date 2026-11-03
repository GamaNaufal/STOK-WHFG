<?php

use App\Support\DeliveryPlanCode;
use App\Support\InventoryUnificationCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All checks precede DML/DDL: MySQL schema changes implicitly commit.
        $report = InventoryUnificationCheck::report();
        if (InventoryUnificationCheck::blocked($report)) {
            throw new RuntimeException('Migrasi dihentikan tanpa mengubah data. Jalankan php artisan inventory:unification-check dan koreksi konflik terlebih dahulu.');
        }
        DB::transaction(function () use ($report) {
            foreach ($report['part_merges'] as $merge) {
                foreach (InventoryUnificationCheck::PART_REFERENCES as $table) {
                    DB::table($table)->where('part_setting_id', $merge['source_id'])->update(['part_setting_id' => $merge['target_id']]);
                }
                DB::table('part_settings')->where('id', $merge['source_id'])->delete();
                DB::table('audit_logs')->insert(['type' => 'master_part', 'action' => 'merged', 'model' => 'PartSetting', 'model_id' => $merge['target_id'], 'user_id' => null,
                    'description' => 'Master part digabung ke sumber bersama; kapasitas domestik menjadi acuan dan snapshot transaksi dipertahankan.',
                    'old_values' => json_encode($merge), 'new_values' => json_encode(['scope' => 'shared', 'canonical_id' => $merge['target_id']]), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('export_delivery_plans')->orderBy('id')->chunkById(500, function ($plans) {
                foreach ($plans as $plan) {
                    DB::table('export_delivery_plans')->where('id', $plan->id)->update(['plan_code' => DeliveryPlanCode::normalize($plan->plan_code)]);
                }
            });
            DB::table('role_feature_access')->orderBy('id')->chunkById(100, function ($roles) {
                foreach ($roles as $role) {
                    $permissions = json_decode($role->permissions, true) ?? [];
                    $hadExport = in_array('export_parts', $permissions, true);
                    $permissions = array_values(array_diff($permissions, ['export_parts']));
                    if ($role->role === 'export_operator' && $hadExport && app()->environment(['local', 'testing'])) {
                        $permissions[] = 'parts';
                    }
                    DB::table('role_feature_access')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
                }
            });
        });
        if (! Schema::hasColumn('box_identities', 'origin_scope')) {
            Schema::table('box_identities', fn (Blueprint $table) => $table->string('origin_scope', 20)->default('domestic'));
        }
        foreach (['boxes' => 'domestic', 'export_stock_boxes' => 'export', 'export_fulfillment_boxes' => 'export'] as $table => $scope) {
            $query = DB::table($table);
            if ($table === 'export_fulfillment_boxes') {
                $query->whereNull('export_stock_box_id');
            }
            $query->orderBy('id')->chunkById(500, function ($rows) use ($scope) {
                DB::table('box_identities')->insertOrIgnore($rows->map(fn ($row) => ['box_number' => $row->box_number, 'origin_scope' => $scope])->all());
            });
        }
        if (Schema::hasColumn('part_settings', 'scope')) {
            Schema::table('part_settings', function (Blueprint $table) {
                $table->dropUnique('part_settings_scope_part_number_unique');
                $table->dropColumn('scope');
            });
        }
        if (! Schema::hasIndex('part_settings', 'part_settings_part_number_unique')) {
            Schema::table('part_settings', fn (Blueprint $table) => $table->unique('part_number'));
        }
        Schema::table('export_delivery_plans', fn (Blueprint $table) => $table->string('plan_code', 100)->nullable(false)->change());
        if (! Schema::hasColumn('export_delivery_plans', 'plan_code_key')) {
            Schema::table('export_delivery_plans', function (Blueprint $table) {
                $column = $table->string('plan_code_key', 100)->virtualAs('UPPER(TRIM(plan_code))');
                if (DB::getDriverName() === 'mysql') {
                    $column->collation('utf8mb4_bin');
                }
            });
        }
        if (! Schema::hasIndex('export_delivery_plans', 'export_delivery_plan_code_unique')) {
            Schema::table('export_delivery_plans', fn (Blueprint $table) => $table->unique('plan_code_key', 'export_delivery_plan_code_unique'));
        }
        if (! Schema::hasTable('export_delivery_plan_codes')) {
            Schema::create('export_delivery_plan_codes', function (Blueprint $table) {
                $column = $table->string('code', 100)->primary();
                if (DB::getDriverName() === 'mysql') {
                    $column->collation('utf8mb4_bin');
                }
                // This ledger survives archival, renaming and even permanent removal.
                $table->unsignedBigInteger('export_delivery_plan_id')->index();
                $table->timestamp('created_at')->nullable();
            });
        }
        foreach (InventoryUnificationCheck::codeClaims() as $code => $ids) {
            DB::table('export_delivery_plan_codes')->insertOrIgnore(['code' => $code, 'export_delivery_plan_id' => $ids[0], 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        // Canonical identities and merged foreign keys must not be split or made reusable on rollback.
    }
};
