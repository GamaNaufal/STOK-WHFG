<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_announcement_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('delivery_announcement_locks')->insert(['id' => 1]);

        Schema::create('delivery_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 255);
            $table->string('customer_key', 255)->index();
            $table->string('part_number', 100);
            $table->string('part_key', 100);
            $table->text('message');
            $table->string('quota_type', 12);
            $table->unsignedInteger('quota');
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('delivery_announcements')->restrictOnDelete();
            $table->string('scope', 12);
            $table->unsignedBigInteger('delivery_id');
            $table->string('delivery_reference', 100);
            $table->string('state', 12);
            $table->boolean('delivery_granted')->default(false);
            $table->text('closed_message')->nullable();
            $table->json('eligible_boxes');
            $table->json('reserved_boxes');
            $table->json('shipped_boxes');
            $table->timestamps();
            $table->unique(['announcement_id', 'scope', 'delivery_id'], 'announcement_target_unique');
            $table->index(['scope', 'delivery_id']);
        });

        Schema::create('delivery_announcement_impressions', function (Blueprint $table) {
            $table->id();
            $table->string('identity', 64)->unique();
            $table->foreignId('target_id')->constrained('delivery_announcement_targets')->restrictOnDelete();
            $table->string('surface', 45);
            $table->string('context', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('delivery_reference', 100);
            $table->text('message');
            $table->json('box_numbers');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
        });

        $this->grantAccess(true);
    }

    public function down(): void
    {
        $this->grantAccess(false);
        Schema::dropIfExists('delivery_announcement_impressions');
        Schema::dropIfExists('delivery_announcement_targets');
        Schema::dropIfExists('delivery_announcements');
        Schema::dropIfExists('delivery_announcement_locks');
    }

    private function grantAccess(bool $grant): void
    {
        $key = 'delivery_announcements';
        DB::transaction(function () use ($grant, $key) {
            foreach (DB::table('role_feature_access')->whereIn('role', ['admin_warehouse', 'supervisi'])->lockForUpdate()->get() as $role) {
                $permissions = json_decode($role->permissions, true, flags: JSON_THROW_ON_ERROR);
                $updated = $grant ? array_values(array_unique([...$permissions, $key])) : array_values(array_diff($permissions, [$key]));
                if ($updated !== $permissions) {
                    DB::table('role_feature_access')->where('id', $role->id)->update([
                        'permissions' => json_encode($updated, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
};
