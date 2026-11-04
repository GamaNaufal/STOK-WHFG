<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->string('active_scope', 16)->nullable()->unique();
            $table->uuid('start_token')->unique();
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('source_max_id')->default(0);
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('finished_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['scope', 'status', 'id']);
        });
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_session_id')->constrained()->restrictOnDelete();
            $table->char('box_number', 8);
            $table->json('initial_snapshot')->nullable();
            $table->json('current_snapshot')->nullable();
            $table->boolean('present')->default(true);
            $table->string('change_kind', 24)->default('unchanged');
            $table->timestamp('first_scanned_at')->nullable();
            $table->foreignId('first_scanned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['stock_opname_session_id', 'box_number'], 'opname_item_identity');
            $table->index(['stock_opname_session_id', 'present', 'first_scanned_at'], 'opname_item_result');
        });
        Schema::create('stock_opname_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_session_id')->constrained()->restrictOnDelete();
            $table->uuid('request_token')->unique();
            $table->string('input', 256);
            $table->string('outcome', 16);
            $table->string('reason');
            $table->json('snapshot')->nullable();
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('scanned_at');
            $table->index(['stock_opname_session_id', 'outcome', 'id'], 'opname_scan_result');
        });
        $grants = [
            'warehouse_operator' => ['stock_input', ['stock_opname_local', 'stock_opname_local_manage']],
            'export_operator' => ['export_stock_input', ['stock_opname_export', 'stock_opname_export_manage']],
            'admin_warehouse' => ['stock_view', ['stock_opname_local', 'stock_opname_local_manage', 'stock_opname_export', 'stock_opname_export_manage']],
            'supervisi' => ['reports', ['stock_opname_local', 'stock_opname_export']],
        ];
        foreach ($grants as $role => [$base, $added]) {
            $record = DB::table('role_feature_access')->where('role', $role)->first();
            if (!$record) continue;
            $permissions = json_decode($record->permissions, true, flags: JSON_THROW_ON_ERROR);
            if (!in_array($base, $permissions, true)) continue;
            DB::table('role_feature_access')->where('id', $record->id)->update([
                'permissions' => json_encode(array_values(array_unique([...$permissions, ...$added])), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Opname history is a permanent operational record.
        if (DB::table('stock_opname_sessions')->exists()) {
            throw new RuntimeException('Rollback ditolak karena sudah ada history stock opname.');
        }
        Schema::dropIfExists('stock_opname_scans');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opname_sessions');
        $keys = ['stock_opname_local', 'stock_opname_local_manage', 'stock_opname_export', 'stock_opname_export_manage'];
        foreach (DB::table('role_feature_access')->get() as $record) {
            $permissions = json_decode($record->permissions, true, flags: JSON_THROW_ON_ERROR);
            DB::table('role_feature_access')->where('id', $record->id)->update([
                'permissions' => json_encode(array_values(array_diff($permissions, $keys)), JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
