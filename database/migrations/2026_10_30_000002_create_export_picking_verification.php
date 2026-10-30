<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_fulfillment_boxes', function (Blueprint $table) {
            $table->boolean('picking_required')->default(false);
            $table->foreignId('picking_source_pallet_id')->nullable()->constrained('export_stock_pallets')->nullOnDelete();
            $table->unsignedInteger('picking_verified_revision')->nullable();
            $table->timestamp('picking_verified_at')->nullable();
            $table->foreignId('picking_verified_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('export_picking_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_planned_pallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('export_stock_box_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('box_number', 8);
            $table->string('issue_type', 32);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->json('context');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('return_pallet_id')->nullable()->constrained('export_stock_pallets')->restrictOnDelete();
            $table->foreignId('return_location_id')->nullable()->constrained('master_locations')->restrictOnDelete();
            $table->string('return_location_code')->nullable();
            $table->foreignId('part_setting_id')->nullable()->constrained('part_settings')->restrictOnDelete();
            $table->unsignedInteger('pcs_quantity')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('registration_token')->unique();
            $table->uuid('confirmation_token')->nullable()->unique();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'export_planned_pallet_id'], 'export_pick_issue_target');
            $table->index(['box_number', 'status'], 'export_pick_issue_box');
            $table->index(['reported_by', 'status'], 'export_pick_issue_operator');
        });
        Schema::create('export_picking_scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->foreignId('export_planned_pallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('export_fulfillment_box_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('export_picking_issue_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('box_number', 8);
            $table->string('outcome', 24);
            $table->timestamps();
        });

        // Existing reservations keep their identity and stock position; mixed sources need scans too.
        $mixed = DB::table('export_stock_boxes')->select('current_pallet_id')->whereNotNull('current_pallet_id')
            ->groupBy('current_pallet_id')->havingRaw('COUNT(DISTINCT part_number) > 1')->pluck('current_pallet_id');
        $boxIds = DB::table('export_stock_boxes')->whereIn('current_pallet_id', $mixed)->pluck('id');
        DB::table('export_fulfillment_boxes')->whereNull('placed_at')->whereIn('export_stock_box_id', $boxIds)
            ->update(['picking_required' => true]);

        $role = DB::table('role_feature_access')->where('role', 'export_operator')->first();
        if ($role) {
            $permissions = json_decode($role->permissions, true, 512, JSON_THROW_ON_ERROR);
            if (in_array('export_stock_input', $permissions, true) && !in_array('export_picking_verification', $permissions, true)) {
                $permissions[] = 'export_picking_verification';
                DB::table('role_feature_access')->where('id', $role->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
                \App\Services\AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $role->id, null,
                    ['role' => 'export_operator', 'permissions' => json_decode($role->permissions, true)],
                    ['role' => 'export_operator', 'permissions' => $permissions], 'Migration mengaktifkan Picking Verification Ekspor untuk operator yang berizin penerimaan ekspor.');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback verifikasi picking memerlukan rekonsiliasi issue, pengembalian dan scan.');
    }
};
