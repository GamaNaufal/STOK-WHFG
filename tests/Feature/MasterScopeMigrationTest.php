<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterScopeMigrationTest extends TestCase
{
    public function test_upgrade_preserves_domestic_ids_capacities_locations_and_foreign_references(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.master_scope_legacy' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('master_scope_legacy');
        Schema::clearResolvedInstance('db.schema');

        try {
            Schema::create('part_settings', function (Blueprint $table) {
                $table->id();
                $table->string('part_number')->unique();
                $table->unsignedInteger('qty_box');
            });
            Schema::create('master_locations', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->boolean('is_occupied')->default(false);
            });
            Schema::create('legacy_stock_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_location_id')->constrained('master_locations');
            });
            $partId = DB::table('part_settings')->insertGetId(['part_number' => 'P-1', 'qty_box' => 20]);
            $locationId = DB::table('master_locations')->insertGetId(['code' => 'A-1', 'is_occupied' => true]);
            $linkId = DB::table('legacy_stock_links')->insertGetId(['master_location_id' => $locationId]);

            $migration = require database_path('migrations/2026_10_28_000002_add_scope_to_master_data.php');
            $migration->up();
            $migration->up();

            $this->assertDatabaseHas('part_settings', ['id' => $partId, 'part_number' => 'P-1', 'qty_box' => 20, 'scope' => 'domestic']);
            $this->assertDatabaseHas('master_locations', ['id' => $locationId, 'code' => 'A-1', 'is_occupied' => true, 'scope' => 'domestic']);
            $this->assertDatabaseHas('legacy_stock_links', ['id' => $linkId, 'master_location_id' => $locationId]);
            DB::table('part_settings')->insert(['part_number' => 'P-1', 'qty_box' => 70, 'scope' => 'export']);
            DB::table('master_locations')->insert(['code' => 'A-1', 'scope' => 'export']);
            $this->assertDatabaseCount('part_settings', 2);
            $this->assertDatabaseCount('master_locations', 2);
        } finally {
            DB::setDefaultConnection($originalConnection);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('master_scope_legacy');
        }
    }
}
