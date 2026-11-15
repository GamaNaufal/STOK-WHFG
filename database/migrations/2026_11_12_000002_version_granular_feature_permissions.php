<?php

use App\Services\GranularFeaturePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('role_feature_access', 'permissions_version')) {
            Schema::table('role_feature_access', fn (Blueprint $table) => $table->unsignedTinyInteger('permissions_version')->default(1));
        }
        DB::transaction(function () {
            foreach (DB::table('role_feature_access')->orderBy('id')->lockForUpdate()->get() as $record) {
                if ((int) $record->permissions_version >= GranularFeaturePermissions::VERSION) continue;
                $before = json_decode($record->permissions, true, 512, JSON_THROW_ON_ERROR);
                $after = GranularFeaturePermissions::expand($record->role, $before);
                DB::table('role_feature_access')->where('id', $record->id)->update([
                    'permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'permissions_version' => GranularFeaturePermissions::VERSION,
                ]);
                DB::table('audit_logs')->insert(['type' => 'role_feature_access', 'action' => 'migrated', 'model' => 'RoleFeatureAccess',
                    'model_id' => $record->id, 'user_id' => null,
                    'old_values' => json_encode(['role' => $record->role, 'permissions' => $before]),
                    'new_values' => json_encode(['role' => $record->role, 'permissions' => $after, 'permissions_version' => GranularFeaturePermissions::VERSION]),
                    'description' => 'Konversi izin menu ke izin tindakan, mempertahankan akses efektif existing.', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Versi izin rinci tidak dapat diturunkan otomatis karena dapat mengaktifkan kembali izin yang dicabut. Gunakan backup dan rekonsiliasi hak akses.');
    }
};
