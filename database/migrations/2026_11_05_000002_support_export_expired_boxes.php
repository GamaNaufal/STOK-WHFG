<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['expired_box_transactions', 'expired_box_handlings', 'expired_box_receipts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('scope', 16)->default('domestic')->index());
        }
        Schema::table('expired_box_handlings', function (Blueprint $table) {
            $table->dropUnique(['source_box_id']);
            $table->dropUnique(['replacement_box_id']);
            $table->unique(['scope', 'source_box_id'], 'expired_handling_source_scope_unique');
            $table->unique(['scope', 'replacement_box_id'], 'expired_handling_replacement_scope_unique');
        });
        Schema::table('export_stock_boxes', function (Blueprint $table) {
            $table->dateTime('reactivated_until')->nullable()->index();
            $table->string('expired_status', 16)->default('active')->index();
        });
        // Grant only the explicitly approved roles, including existing custom permission sets.
        foreach (['supervisi', 'admin_warehouse'] as $role) {
            $record = DB::table('role_feature_access')->where('role', $role)->first();
            if ($record) {
                $permissions = json_decode($record->permissions, true) ?: [];
                if ($role === 'supervisi' && ! in_array('expired_box', $permissions, true)) {
                    continue;
                }
                DB::table('role_feature_access')->where('id', $record->id)->update([
                    'permissions' => json_encode(array_values(array_unique([...$permissions, 'expired_box', 'export_expired_box']))),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('expired_box_transactions')->where('scope', 'export')->exists()
            || DB::table('export_stock_boxes')->whereNotNull('reactivated_until')->exists()) {
            throw new RuntimeException('Rollback ditolak: histori QA/packing ekspor sudah tersedia.');
        }
        Schema::table('export_stock_boxes', function (Blueprint $table) {
            $table->dropIndex(['reactivated_until']);
            $table->dropIndex(['expired_status']);
            $table->dropColumn(['reactivated_until', 'expired_status']);
        });
        Schema::table('expired_box_handlings', function (Blueprint $table) {
            $table->dropUnique('expired_handling_source_scope_unique');
            $table->dropUnique('expired_handling_replacement_scope_unique');
            $table->unique('source_box_id');
            $table->unique('replacement_box_id');
        });
        foreach (['expired_box_transactions', 'expired_box_handlings', 'expired_box_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['scope']);
                $table->dropColumn('scope');
            });
        }
    }
};
