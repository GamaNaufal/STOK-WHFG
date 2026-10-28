<?php

use App\Services\AuditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $record = DB::table('role_feature_access')->where('role', 'export_operator')->lockForUpdate()->first();
            if (! $record) {
                return; // Accounts without saved settings use the new feature defaults.
            }

            $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
            $after = array_values(array_unique(array_merge($before, ['export_parts', 'export_locations'])));
            if ($before === $after) {
                return;
            }

            DB::table('role_feature_access')->where('id', $record->id)
                ->update(['permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            AuditService::record('role_feature_access', 'updated', 'RoleFeatureAccess', $record->id, null,
                ['role' => 'export_operator', 'permissions' => $before],
                ['role' => 'export_operator', 'permissions' => $after],
                'Migration mengaktifkan dua fitur master ekspor untuk Operator Export.');
        });
    }

    public function down(): void
    {
        // Preserve permissions subsequently reviewed by the administrator.
    }
};
