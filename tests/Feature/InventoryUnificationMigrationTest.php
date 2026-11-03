<?php

namespace Tests\Feature;

use App\Support\InventoryUnificationCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryUnificationMigrationTest extends TestCase
{
    private string $originalConnection;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.unification_legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('unification_legacy');
        Schema::clearResolvedInstance('db.schema');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_ends_with($path, '2026_11_03_000001_unify_part_master_and_box_identity.php')));
        Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
        $this->userId = DB::table('users')->insertGetId(['name' => 'Migration tester', 'email' => 'migration@example.test', 'password' => 'test', 'role' => 'admin', 'is_active' => 1]);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->originalConnection);
        Schema::clearResolvedInstance('db.schema');
        DB::purge('unification_legacy');
        parent::tearDown();
    }

    private function migrateShared(): void
    {
        (require database_path('migrations/2026_11_03_000001_unify_part_master_and_box_identity.php'))->up();
    }

    private function part(string $scope, int $capacity): int
    {
        return DB::table('part_settings')->insertGetId(['scope' => $scope, 'part_number' => 'SHARED', 'qty_box' => $capacity]);
    }

    private function plan(?string $code = 'RD-01', string $status = 'completed'): int
    {
        return DB::table('export_delivery_plans')->insertGetId(['plan_code' => $code, 'customer_name' => 'Customer',
            'delivery_deadline' => '2026-11-20', 'status' => $status, 'total_pcs' => 80, 'total_boxes' => 1,
            'created_by' => $this->userId, 'updated_by' => $this->userId]);
    }

    private function stock(int $part, string $number = '12345678'): int
    {
        return DB::table('export_stock_boxes')->insertGetId(['box_number' => $number, 'part_setting_id' => $part, 'part_number' => 'SHARED',
            'pcs_quantity' => 80, 'receive_token' => (string) \Illuminate\Support\Str::uuid(), 'first_received_at' => '2026-01-02 03:04:05', 'received_by' => $this->userId]);
    }

    private function assertBlocked(): void
    {
        try {
            $this->migrateShared();
            $this->fail('Conflicting legacy data must stop migration.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('inventory:unification-check', $error->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('part_settings', 'scope'));
        $this->assertFalse(Schema::hasColumn('box_identities', 'origin_scope'));
    }

    public function test_merge_keeps_domestic_id_capacity_and_historical_pcs_dates_and_location_scopes(): void
    {
        $domestic = $this->part('domestic', 100);
        $export = $this->part('export', 80);
        $plan = $this->plan(' rd-old ');
        DB::table('export_delivery_plan_items')->insert(['export_delivery_plan_id' => $plan, 'part_setting_id' => $export, 'position' => 1,
            'part_number' => 'SHARED', 'pcs_quantity' => 80, 'pcs_per_box' => 80, 'box_quantity' => 1]);
        $stock = $this->stock($export);
        DB::table('box_identities')->insert(['box_number' => '87654321']); // Permanently removed domestic box.
        foreach (['domestic', 'export'] as $scope) {
            DB::table('master_locations')->insert(['scope' => $scope, 'code' => 'A1', 'is_occupied' => 0]);
        }
        DB::table('audit_logs')->insert(['type' => 'export_delivery_plan', 'action' => 'updated', 'model' => 'ExportDeliveryPlan', 'model_id' => $plan,
            'old_values' => json_encode(['plan_code' => 'PREVIOUS-CODE']), 'new_values' => json_encode(['plan_code' => ' rd-old '])]);
        $this->migrateShared();
        $this->assertDatabaseCount('part_settings', 1);
        $this->assertDatabaseHas('part_settings', ['id' => $domestic, 'qty_box' => 100]);
        $this->assertDatabaseHas('export_delivery_plan_items', ['part_setting_id' => $domestic, 'pcs_quantity' => 80, 'pcs_per_box' => 80]);
        $this->assertDatabaseHas('export_stock_boxes', ['id' => $stock, 'part_setting_id' => $domestic, 'pcs_quantity' => 80, 'first_received_at' => '2026-01-02 03:04:05']);
        $this->assertDatabaseHas('box_identities', ['box_number' => '87654321', 'origin_scope' => 'domestic']);
        $this->assertDatabaseHas('box_identities', ['box_number' => '12345678', 'origin_scope' => 'export']);
        $this->assertDatabaseHas('export_delivery_plan_codes', ['code' => 'PREVIOUS-CODE', 'export_delivery_plan_id' => $plan]);
        $this->assertDatabaseHas('export_delivery_plans', ['id' => $plan, 'plan_code' => 'RD-OLD']);
        $this->assertFalse(Schema::hasColumn('part_settings', 'scope'));
        $this->assertDatabaseCount('master_locations', 2);
    }

    public function test_cross_scope_identity_collision_stops_before_merging_or_ddl(): void
    {
        $domestic = $this->part('domestic', 100);
        $export = $this->part('export', 80);
        $this->stock($export);
        DB::table('box_identities')->insert(['box_number' => '12345678']);
        $this->assertSame(['12345678'], InventoryUnificationCheck::report()['box_conflicts']);
        $this->assertBlocked();
        $this->assertDatabaseHas('part_settings', ['id' => $export, 'qty_box' => 80]);
        $this->assertDatabaseHas('part_settings', ['id' => $domestic, 'qty_box' => 100]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_and_historically_reused_codes_block_even_when_plans_are_archived(): void
    {
        $missing = $this->plan(null);
        $first = $this->plan('NEW-CODE');
        $second = $this->plan(' old-code ', 'cancelled');
        DB::table('export_delivery_plans')->where('id', $second)->update(['deleted_at' => now()]);
        DB::table('audit_logs')->insert(['type' => 'export_delivery_plan', 'action' => 'updated', 'model' => 'ExportDeliveryPlan', 'model_id' => $first,
            'old_values' => json_encode(['plan_code' => 'OLD-CODE'])]);
        $report = InventoryUnificationCheck::report();
        $this->assertSame([$missing], $report['missing_plan_codes']);
        $this->assertEqualsCanonicalizing([$first, $second], $report['duplicate_plan_codes']['OLD-CODE']);
        $this->assertBlocked();
        $this->assertDatabaseHas('export_delivery_plans', ['id' => $second, 'plan_code' => ' old-code ']);
    }

    public function test_active_capacity_mismatch_requires_resolution_before_master_merge(): void
    {
        $this->part('domestic', 100);
        $export = $this->part('export', 80);
        $plan = $this->plan('ACTIVE', 'draft');
        DB::table('export_delivery_plan_items')->insert(['export_delivery_plan_id' => $plan, 'part_setting_id' => $export, 'position' => 1,
            'part_number' => 'SHARED', 'pcs_quantity' => 80, 'pcs_per_box' => 80, 'box_quantity' => 1]);
        $this->assertCount(1, InventoryUnificationCheck::report()['active_capacity_conflicts']);
        $this->assertBlocked();
    }

    public function test_migration_removes_obsolete_permission_and_keeps_revocations_and_export_locations(): void
    {
        DB::table('role_feature_access')->insert(['role' => 'export_operator', 'permissions' => json_encode(['export_parts', 'export_locations'])]);
        DB::table('role_feature_access')->insert(['role' => 'sales', 'permissions' => '[]']);
        $this->migrateShared();
        $permissions = json_decode(DB::table('role_feature_access')->where('role', 'export_operator')->value('permissions'), true);
        $this->assertSame(['export_locations', 'parts'], $permissions);
        $this->assertSame('[]', DB::table('role_feature_access')->where('role','sales')->value('permissions'));
        $this->assertFalse(InventoryUnificationCheck::blocked(InventoryUnificationCheck::report()));
    }
}
