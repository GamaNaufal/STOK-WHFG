<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateGrant(true);
    }

    public function down(): void
    {
        $this->updateGrant(false);
    }

    private function updateGrant(bool $grant): void
    {
        DB::transaction(function () use ($grant) {
            $key = 'export_stock_manage';
            $roles = DB::table('role_feature_access')->whereIn('role', ['admin_warehouse', 'supervisi'])
                ->lockForUpdate()->get();
            foreach ($roles as $role) {
                $before = json_decode($role->permissions, true, flags: JSON_THROW_ON_ERROR);
                $after = $grant ? array_values(array_unique([...$before, $key])) : array_values(array_diff($before, [$key]));
                if ($before !== $after) {
                    DB::table('role_feature_access')->where('id', $role->id)->update([
                        'permissions' => json_encode($after, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
};
