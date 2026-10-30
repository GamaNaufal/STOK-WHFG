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
        // Low-volume export writes share one lock, including plan finalization.
        Schema::create('export_inventory_locks', function (Blueprint $table) { $table->unsignedTinyInteger('id')->primary(); });
        DB::table('export_inventory_locks')->insert(['id' => 1]);
        Schema::create('export_stock_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_location_id')->nullable()->unique()->constrained('master_locations')->restrictOnDelete();
            $table->foreignId('initial_location_id')->constrained('master_locations')->restrictOnDelete();
            $table->string('location_code');
            $table->foreignId('export_planned_pallet_id')->nullable()->unique()->constrained('export_planned_pallets')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });
        Schema::create('export_stock_boxes', function (Blueprint $table) {
            $table->id();
            // Separate export identity: the same eight digits may exist domestically.
            $table->char('box_number', 8)->unique();
            $table->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $table->string('part_number', 100);
            $table->unsignedInteger('pcs_quantity');
            $table->string('state', 16)->default('received');
            $table->unsignedInteger('revision')->default(1);
            $table->uuid('receive_token')->unique();
            $table->timestamp('first_received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('current_pallet_id')->nullable()->constrained('export_stock_pallets')->restrictOnDelete();
            $table->timestamp('stored_at')->nullable();
            $table->foreignId('stored_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['part_setting_id', 'pcs_quantity', 'first_received_at', 'id'], 'export_stock_fifo_index');
        });
        Schema::table('export_fulfillment_boxes', function (Blueprint $table) {
            // Existing monitoring records are retained without inventing receipts.
            $table->foreignId('export_stock_box_id')->nullable()->unique()->constrained('export_stock_boxes')->restrictOnDelete();
        });
        Schema::create('export_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_stock_box_id')->constrained('export_stock_boxes')->restrictOnDelete();
            $table->uuid('request_token')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('from_pallet_id')->nullable()->constrained('export_stock_pallets')->restrictOnDelete();
            $table->foreignId('to_pallet_id')->constrained('export_stock_pallets')->restrictOnDelete();
            $table->foreignId('export_fulfillment_box_id')->nullable()->constrained('export_fulfillment_boxes')->nullOnDelete();
            $table->string('from_location_code')->nullable();
            $table->string('to_location_code');
            $table->foreignId('placed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->timestamps();
        });
        DB::transaction(function () {
            $record = DB::table('role_feature_access')->where('role', 'export_operator')->lockForUpdate()->first();
            if (!$record) return;
            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            $after = array_values(array_unique([...$before, 'export_stock_input']));
            DB::table('role_feature_access')->where('id', $record->id)->update([
                'permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => 'export_operator', 'permissions' => $before], ['role' => 'export_operator', 'permissions' => $after],
                'Migration mengaktifkan Penerimaan Box Ekspor.');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback penerimaan ekspor memerlukan rekonsiliasi stok, penempatan, dan histori.');
    }
};
