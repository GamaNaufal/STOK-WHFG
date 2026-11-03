<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_fulfillment_boxes', function (Blueprint $table) {
            $table->timestamp('picking_started_at')->nullable();
        });

        foreach (['export_operator' => 'export_stock_input', 'admin_warehouse' => 'move_pallet'] as $role => $base) {
            $record = DB::table('role_feature_access')->where('role', $role)->first();
            if (! $record) {
                continue;
            }
            $permissions = json_decode($record->permissions, true, flags: JSON_THROW_ON_ERROR);
            if (in_array($base, $permissions, true) && ! in_array('export_move_pallet', $permissions, true)) {
                DB::table('role_feature_access')->where('role', $role)->update([
                    'permissions' => json_encode([...$permissions, 'export_move_pallet'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('export_fulfillment_boxes', function (Blueprint $table) {
            $table->dropColumn('picking_started_at');
        });
        foreach (DB::table('role_feature_access')->get() as $record) {
            $permissions = json_decode($record->permissions, true, flags: JSON_THROW_ON_ERROR);
            DB::table('role_feature_access')->where('role', $record->role)->update([
                'permissions' => json_encode(array_values(array_diff($permissions, ['export_move_pallet'])), JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
