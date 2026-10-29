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
        Schema::create('export_pallet_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_delivery_plan_id')->unique()->constrained('export_delivery_plans')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('delivery_revision');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
        Schema::create('export_planned_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_pallet_plan_id')->constrained('export_pallet_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('master_location_id')->nullable()->constrained('master_locations')->restrictOnDelete();
            $table->string('location_code')->nullable();
            $table->timestamps();
            $table->unique(['export_pallet_plan_id', 'position'], 'export_planned_pallet_position_unique');
        });
        Schema::create('export_planned_pallet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_planned_pallet_id')->constrained('export_planned_pallets')->cascadeOnDelete();
            $table->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $table->string('part_number', 100);
            $table->unsignedInteger('pcs_per_box');
            $table->unsignedInteger('box_quantity');
            $table->timestamps();
            $table->unique(['export_planned_pallet_id', 'part_setting_id'], 'export_planned_pallet_part_unique');
        });
        Schema::create('export_pallet_location_reservations', function (Blueprint $table) {
            $table->foreignId('master_location_id')->primary()->constrained('master_locations')->restrictOnDelete();
            $table->foreignId('export_pallet_plan_id');
            $table->foreign('export_pallet_plan_id', 'export_location_plan_fk')
                ->references('id')->on('export_pallet_plans')->cascadeOnDelete();
            $table->foreignId('export_planned_pallet_id')->unique('export_location_pallet_unique');
            $table->foreign('export_planned_pallet_id', 'export_location_pallet_fk')
                ->references('id')->on('export_planned_pallets')->cascadeOnDelete();
            $table->timestamp('created_at');
        });
        DB::transaction(function () {
            $record = DB::table('role_feature_access')->where('role', 'export_operator')->lockForUpdate()->first();
            if (! $record) return;
            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            $after = array_values(array_unique([...$before, 'export_pallet_plans']));
            DB::table('role_feature_access')->where('id', $record->id)
                ->update(['permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => 'export_operator', 'permissions' => $before], ['role' => 'export_operator', 'permissions' => $after],
                'Migration mengaktifkan Perencanaan Pallet Ekspor.');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback Perencanaan Pallet memerlukan peninjauan histori dan reservasi lokasi.');
    }
};
