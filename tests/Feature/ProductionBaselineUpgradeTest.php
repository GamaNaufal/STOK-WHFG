<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionBaselineUpgradeTest extends TestCase
{
    public function test_populated_october_baseline_keeps_deadlines_stock_history_and_picking_after_all_pending_migrations(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.production_upgrade_probe' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('production_upgrade_probe');
        Schema::clearResolvedInstance('db.schema');
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));

        try {
            $baseline = array_values(array_filter(glob(database_path('migrations/*.php')),
                fn ($path) => basename($path) <= '2026_10_15_000001_create_role_feature_access_table.php'));
            $this->assertSame(0, Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]));
            $actor = User::factory()->create(['role' => 'warehouse_operator']);
            $future = now()->addDays(17)->toDateTimeString();
            $past = now()->subDay()->toDateTimeString();
            $ids = [];
            foreach ([
                'future' => ['reactivated_until' => $future],
                'past' => ['reactivated_until' => $past],
                'normal' => ['created_at' => now()->subMonth()->toDateTimeString()],
                'handled' => ['expired_status' => 'handled', 'reactivated_until' => null],
                'withdrawn' => ['is_withdrawn' => true, 'reactivated_until' => $future],
                'deleted' => ['deleted_at' => now()->subDay()->toDateTimeString(), 'reactivated_until' => $future],
            ] as $name => $overrides) {
                $number = sprintf('970000%02d', count($ids) + 1);
                $ids[$name] = DB::table('boxes')->insertGetId(array_merge([
                    'box_number' => $number, 'part_number' => 'LEGACY-UPGRADE', 'pcs_quantity' => 20,
                    'qr_code' => $number, 'user_id' => $actor->id, 'is_withdrawn' => false,
                    'expired_status' => 'active', 'reactivated_until' => null,
                    'created_at' => now()->subMonthsNoOverflow(13)->toDateTimeString(),
                ], $overrides));
            }
            DB::table('part_settings')->insert(['part_number' => 'LEGACY-UPGRADE', 'qty_box' => 20]);
            $orderId = DB::table('delivery_orders')->insertGetId([
                'sales_user_id' => $actor->id, 'customer_name' => 'Legacy delivery',
                'delivery_date' => '2026-12-01', 'status' => 'processing',
            ]);
            $sessionId = DB::table('delivery_pick_sessions')->insertGetId([
                'delivery_order_id' => $orderId, 'created_by' => $actor->id, 'status' => 'scanning',
                'started_at' => now()->toDateTimeString(),
            ]);
            DB::table('delivery_pick_items')->insert([
                'pick_session_id' => $sessionId, 'box_id' => $ids['future'],
                'part_number' => 'LEGACY-UPGRADE', 'pcs_quantity' => 20, 'status' => 'pending',
            ]);
            DB::table('audit_logs')->insert([
                'type' => 'box_edit', 'action' => 'updated', 'model' => 'Box', 'model_id' => $ids['future'],
                'description' => 'Existing history', 'old_values' => '{"pcs_quantity":20}',
            ]);
            $tables = ['boxes', 'users', 'part_settings', 'delivery_orders', 'delivery_pick_sessions', 'delivery_pick_items', 'audit_logs'];
            $snapshots = [];
            foreach ($tables as $table) {
                $snapshots[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            }

            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
            foreach ($snapshots as $table => $rows) {
                foreach ($rows as $row) {
                    $after = (array) DB::table($table)->where('id', $row['id'])->first();
                    $this->assertSame($row, array_intersect_key($after, $row), $table.' existing business columns');
                }
                $this->assertSame(count($rows), DB::table($table)->count(), $table.' row count');
            }
            $this->assertSame(count(glob(database_path('migrations/*.php'))), DB::table('migrations')->count());
            $this->assertSame($future, DB::table('boxes')->where('id', $ids['future'])->value('legacy_reactivated_until'));
            $this->assertSame($past, DB::table('boxes')->where('id', $ids['past'])->value('legacy_reactivated_until'));
            $this->assertNull(DB::table('boxes')->where('id', $ids['withdrawn'])->value('legacy_reactivated_until'));
            $this->assertNull(DB::table('boxes')->where('id', $ids['deleted'])->value('legacy_reactivated_until'));
            $this->assertSame(6, DB::table('box_identities')->where('last_cycle', 1)->count());
            $this->assertSame(0, Artisan::call('inventory:unification-check'), Artisan::output());
            $this->assertTrue(Box::active()->whereKey($ids['future'])->exists());
            $this->assertFalse(Box::active()->whereKey($ids['past'])->exists());
            app(ExpiredBoxService::class)->syncStatuses();
            $this->assertSame('active', Box::findOrFail($ids['future'])->expired_status);
            $this->assertSame('expired', Box::findOrFail($ids['past'])->expired_status);
            $this->assertSame('handled', Box::findOrFail($ids['handled'])->expired_status);
            $this->assertSame(0, DB::table('expired_box_handlings')->count());
            $this->assertSame(0, DB::table('export_customers')->count());
            $this->travelTo($future);
            $this->assertFalse(Box::active()->whereKey($ids['future'])->exists());
            $this->assertDatabaseHas('delivery_pick_items', ['pick_session_id' => $sessionId, 'box_id' => $ids['future'], 'status' => 'pending']);
        } finally {
            $this->travelBack();
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('production_upgrade_probe');
        }
    }
}
