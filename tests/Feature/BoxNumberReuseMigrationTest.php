<?php

namespace Tests\Feature;

use App\Models\Box;
use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Tests\TestCase;

class BoxNumberReuseMigrationTest extends TestCase
{
    public function test_backfill_preserves_internal_ids_dates_and_historical_mappings_before_reuse(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.box_reuse_legacy' => ['driver' => 'sqlite', 'database' => ':memory:',
            'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('box_reuse_legacy');
        Schema::clearResolvedInstance('db.schema');
        try {
            $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
                fn ($path) => ! str_ends_with($path, '2026_11_15_000001_allow_box_number_reuse.php')));
            Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
            $user = DB::table('users')->insertGetId(['name' => 'Migration', 'email' => 'reuse-migration@example.test',
                'password' => 'test', 'role' => 'admin', 'is_active' => 1]);
            $old = ['box_number' => '00000001', 'part_number' => 'OLD', 'pcs_quantity' => 10, 'qr_code' => 'OLD',
                'user_id' => $user, 'created_at' => '2025-01-02 03:04:05', 'deleted_at' => '2026-01-02 03:04:05'];
            $boxId = DB::table('boxes')->insertGetId($old);
            DB::table('box_identities')->insert(['box_number' => '00000001', 'origin_scope' => 'domestic']);
            DB::table('expired_box_handlings')->insert(['source_box_id' => $boxId, 'source_box_number' => '00000001',
                'replacement_box_number' => '00000009', 'part_number' => 'OLD', 'pcs_quantity' => 10,
                'status' => 'completed', 'original_stored_at' => '2025-01-02 03:04:05', 'source_snapshot' => json_encode($old)]);
            $part = \App\Models\PartSetting::create(['part_number' => 'DRAFT', 'qty_box' => 10]);
            $batch = \App\Models\ExportReceivingBatch::create(['token' => (string) \Illuminate\Support\Str::uuid(), 'created_by' => $user]);
            foreach (['00000001', '00000002'] as $position => $number) {
                DB::table('export_receiving_batch_items')->insert(['export_receiving_batch_id' => $batch->id,
                    'box_number' => $number, 'part_setting_id' => $part->id, 'part_number' => 'DRAFT',
                    'pcs_quantity' => 10, 'position' => $position + 1, 'receive_token' => (string) \Illuminate\Support\Str::uuid()]);
            }
            $snapshot = DB::table('boxes')->where('id', $boxId)->first();
            $migration = require database_path('migrations/2026_11_15_000001_allow_box_number_reuse.php');
            $migration->up();
            $after = DB::table('boxes')->where('id', $boxId)->first();
            $this->assertEquals($snapshot, (object) array_diff_key((array) $after, ['identity_cycle' => true]));
            $this->assertSame(1, (int) $after->identity_cycle);
            $this->assertSame(1, (int) DB::table('box_identities')->value('last_cycle'));
            $this->assertSame([1, 0], $batch->items()->orderBy('position')->pluck('expected_identity_cycle')->map(fn ($cycle) => (int) $cycle)->all());
            $new = Box::create(['box_number' => '00000001', 'part_number' => 'NEW', 'pcs_quantity' => 25,
                'qr_code' => 'NEW', 'user_id' => $user]);
            $this->assertNotSame($boxId, $new->id);
            $this->assertSame(2, (int) $new->identity_cycle);
            $this->assertDatabaseHas('boxes', ['id' => $boxId] + $old);
            $this->assertDatabaseHas('expired_box_handlings', ['source_box_id' => $boxId, 'part_number' => 'OLD']);
            // Neither source nor replacement labels may remain globally unique in QA history.
            DB::table('expired_box_handlings')->insert(['source_box_id' => $new->id, 'source_box_number' => '00000001',
                'replacement_box_number' => '00000009', 'part_number' => 'NEW', 'pcs_quantity' => 25,
                'status' => 'completed', 'original_stored_at' => now(), 'source_snapshot' => '{}']);
            $this->assertDatabaseCount('expired_box_handlings', 2);
            try {
                $migration->down();
                $this->fail('Rollback cannot collapse reused histories.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('Rollback ditolak', $error->getMessage());
            }
        } finally {
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('box_reuse_legacy');
        }
    }
}
