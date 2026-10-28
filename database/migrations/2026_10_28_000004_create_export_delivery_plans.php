<?php

use App\Services\AuditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_delivery_plans', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->date('delivery_deadline')->index();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->string('source_type', 16)->default('manual');
            $table->string('source_name')->nullable();
            $table->unsignedBigInteger('total_pcs');
            $table->unsignedBigInteger('total_boxes');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('export_delivery_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_delivery_plan_id')->constrained('export_delivery_plans')->cascadeOnDelete();
            $table->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('part_number', 100);
            $table->unsignedInteger('pcs_quantity');
            $table->unsignedInteger('pcs_per_box');
            $table->unsignedInteger('box_quantity');
            $table->timestamps();
            $table->unique(['export_delivery_plan_id', 'part_setting_id'], 'export_plan_part_unique');
        });

        DB::transaction(function () {
            $record = DB::table('role_feature_access')->where('role', 'export_operator')->lockForUpdate()->first();
            if (! $record) {
                return;
            }
            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            $after = array_values(array_unique([...$before, 'export_delivery_plans']));
            DB::table('role_feature_access')->where('id', $record->id)
                ->update(['permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => 'export_operator', 'permissions' => $before], ['role' => 'export_operator', 'permissions' => $after],
                'Migration mengaktifkan Rencana Delivery Ekspor.');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback rencana delivery memerlukan peninjauan agar histori tidak hilang.');
    }
};
