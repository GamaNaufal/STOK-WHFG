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
        Schema::table('export_pallet_plans', function (Blueprint $table) {
            $table->boolean('entered_fulfillment')->default(false);
        });
        DB::table('export_pallet_plans')->where(function ($query) {
            $query->where('status', 'final')->orWhereExists(fn ($q) => $q->selectRaw('1')
                ->from('export_pallet_location_reservations as claim')
                ->whereColumn('claim.export_pallet_plan_id', 'export_pallet_plans.id'))
                ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('audit_logs as audit')
                    ->where('audit.type', 'export_pallet_plan')->where('audit.model', 'ExportPalletPlan')
                    ->where('audit.action', 'finalized')->whereColumn('audit.model_id', 'export_pallet_plans.id'));
        })->update(['entered_fulfillment' => true]);
        // Active box commitments only. Future receipt/return transactions must audit
        // changes and lock the delivery parent before changing these records.
        Schema::create('export_fulfillment_boxes', function (Blueprint $table) {
            $table->id();
            $table->char('box_number', 8)->unique();
            $table->foreignId('export_planned_pallet_item_id')->constrained('export_planned_pallet_items')->restrictOnDelete();
            $table->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $table->unsignedInteger('pcs_quantity');
            $table->timestamp('first_received_at');
            $table->timestamp('reserved_at');
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('placed_location_id')->nullable()->constrained('master_locations')->restrictOnDelete();
            $table->timestamps();
            $table->index(['export_planned_pallet_item_id', 'placed_at'], 'export_fulfillment_item_placement_index');
        });

        DB::transaction(function () {
            $record = DB::table('role_feature_access')->where('role', 'export_operator')->lockForUpdate()->first();
            if (! $record) return;
            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            $after = array_values(array_unique([...$before, 'export_fulfillment']));
            DB::table('role_feature_access')->where('id', $record->id)
                ->update(['permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => 'export_operator', 'permissions' => $before], ['role' => 'export_operator', 'permissions' => $after],
                'Migration mengaktifkan Pemenuhan Permintaan Ekspor.');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback Pemenuhan Permintaan memerlukan peninjauan reservasi dan penempatan box.');
    }
};
