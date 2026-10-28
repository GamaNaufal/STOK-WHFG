<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExportMasterLocation;
use App\Models\ExportPartSetting;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\LocationAssignmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_masters_start_empty_and_domestic_rows_remain_visible_only_domestically(): void
    {
        $part = PartSetting::create(['part_number' => 'LOCAL-ONLY', 'qty_box' => 20]);
        $location = MasterLocation::create(['code' => 'LOCAL-AREA']);

        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->get(route('export.part-settings.index'))->assertOk()
            ->assertViewHas('parts', fn ($parts) => $parts->total() === 0)
            ->assertSee('Belum ada nomor part')->assertDontSee('LOCAL-ONLY');
        $this->get(route('export.locations.index'))->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->total() === 0)
            ->assertSee('Belum ada lokasi')->assertDontSee('LOCAL-AREA');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('export.part-settings.index'), false)
            ->assertSee(route('export.locations.index'), false)
            ->assertDontSee(route('part-settings.index'), false)
            ->assertDontSee(route('locations.index'), false);
        $this->get(route('part-settings.index'))->assertForbidden();
        $this->get(route('locations.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_warehouse']));
        $this->get(route('part-settings.index'))->assertOk()->assertSee('LOCAL-ONLY');
        $this->get(route('locations.index'))->assertOk()->assertSee('LOCAL-AREA');
        $this->get(route('export.part-settings.index'))->assertForbidden();
        $this->get(route('export.locations.index'))->assertForbidden();
        $this->assertSame('domestic', $part->fresh()->scope);
        $this->assertSame('domestic', $location->fresh()->scope);
    }

    public function test_same_part_number_has_independent_capacities_and_export_crud_does_not_change_domestic_history(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator']);
        $domestic = PartSetting::create(['part_number' => 'SHARED-PART', 'qty_box' => 20]);
        Box::create(['box_number' => '12345678', 'part_number' => 'SHARED-PART',
            'pcs_quantity' => 20, 'qr_code' => '12345678|SHARED-PART|20', 'user_id' => $operator->id]);
        $this->actingAs($operator)->post(route('export.part-settings.store'), [
            'part_number' => 'SHARED-PART', 'qty_box' => 70, 'scope' => 'domestic',
        ])->assertSessionHasNoErrors()->assertRedirect(route('export.part-settings.index'));
        $export = ExportPartSetting::where('part_number', 'SHARED-PART')->firstOrFail();
        $this->assertSame('export', $export->scope);
        $this->assertSame(70, $export->qty_box);
        $this->assertSame(20, $domestic->fresh()->qty_box);
        $this->postJson(route('export.part-settings.store'), ['part_number' => 'SHARED-PART', 'qty_box' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $this->get(route('export.part-settings.create'))->assertOk()
            ->assertSee(route('export.part-settings.store'), false);
        $this->get(route('export.part-settings.edit', $export))->assertOk()
            ->assertSee(route('export.part-settings.update', $export), false);
        $this->put(route('export.part-settings.update', $export), [
            'part_number' => 'SHARED-PART', 'qty_box' => 80, 'scope' => 'domestic',
        ])->assertSessionHasNoErrors()->assertRedirect(route('export.part-settings.index'));
        $this->assertSame(80, $export->fresh()->qty_box);
        $this->assertSame(20, $domestic->fresh()->qty_box);
        $audit = AuditLog::where('model', 'ExportPartSetting')->where('action', 'updated')->firstOrFail();
        $this->assertSame('export', $audit->getNewValuesArray()['scope']);
        $this->delete(route('export.part-settings.destroy', $export))->assertSessionHas('success');
        $this->assertNull(ExportPartSetting::find($export->id));
        $this->assertNotNull($domestic->fresh());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->delete(route('part-settings.destroy', $domestic))->assertSessionHas('error');
        $this->assertNotNull($domestic->fresh());
    }

    public function test_domestic_part_can_be_created_when_number_already_exists_only_in_export(): void
    {
        ExportPartSetting::create(['part_number' => 'EXPORT-FIRST', 'qty_box' => 90]);
        $this->actingAs(User::factory()->create(['role' => 'admin_warehouse']));
        $this->post(route('part-settings.store'), ['part_number' => 'EXPORT-FIRST', 'qty_box' => 15, 'scope' => 'export'])
            ->assertSessionHasNoErrors()->assertRedirect(route('part-settings.index'));
        $this->assertSame(15, PartSetting::where('part_number', 'EXPORT-FIRST')->firstOrFail()->qty_box);
        $this->assertSame(90, ExportPartSetting::where('part_number', 'EXPORT-FIRST')->firstOrFail()->qty_box);
    }

    public function test_export_location_crud_and_duplicate_checks_are_scoped_and_normalized(): void
    {
        $domestic = MasterLocation::create(['code' => 'A-1']);
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->post(route('export.locations.store'), ['code' => ' a-1 ', 'scope' => 'domestic', 'is_occupied' => true])
            ->assertSessionHasNoErrors()->assertRedirect(route('export.locations.index'));
        $export = ExportMasterLocation::where('code', 'A-1')->firstOrFail();
        $this->assertFalse($export->is_occupied);
        $this->assertSame('export', $export->scope);
        $this->postJson(route('export.locations.store'), ['code' => 'a-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->get(route('export.locations.edit', $export))->assertOk()
            ->assertSee(route('export.locations.update', $export), false);
        $this->put(route('export.locations.update', $export), ['code' => ' e-2 ', 'scope' => 'domestic'])
            ->assertSessionHasNoErrors()->assertRedirect(route('export.locations.index'));
        $this->assertSame('E-2', $export->fresh()->code);
        $this->assertSame('A-1', $domestic->fresh()->code);
        $export->update(['is_occupied' => true]);
        $this->put(route('export.locations.update', $export), ['code' => 'E-3'])->assertSessionHas('error');
        $this->assertSame('E-2', $export->fresh()->code);
        $export->update(['is_occupied' => false]);
        $this->delete(route('export.locations.destroy', $export))->assertSessionHas('success');
        $this->assertNotNull($domestic->fresh());
    }

    public function test_admin_cannot_use_ids_from_the_other_scope_even_with_full_permissions(): void
    {
        $domesticPart = PartSetting::create(['part_number' => 'D', 'qty_box' => 10]);
        $exportPart = ExportPartSetting::create(['part_number' => 'E', 'qty_box' => 70]);
        $domesticLocation = MasterLocation::create(['code' => 'D']);
        $exportLocation = ExportMasterLocation::create(['code' => 'E']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([['export.part-settings', $domesticPart], ['part-settings', $exportPart]] as [$prefix, $part]) {
            $this->get(route($prefix.'.edit', $part))->assertNotFound();
            $this->putJson(route($prefix.'.update', $part), ['part_number' => 'HACK', 'qty_box' => 1])->assertNotFound();
            $this->deleteJson(route($prefix.'.destroy', $part))->assertNotFound();
        }
        foreach ([['export.locations', $domesticLocation], ['locations', $exportLocation]] as [$prefix, $location]) {
            $this->get(route($prefix.'.edit', $location))->assertNotFound();
            $this->putJson(route($prefix.'.update', $location), ['code' => 'HACK'])->assertNotFound();
            $this->deleteJson(route($prefix.'.destroy', $location))->assertNotFound();
        }
        $this->assertDatabaseCount('part_settings', 2);
        $this->assertDatabaseCount('master_locations', 2);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_search_and_existing_models_exclude_the_other_scope(): void
    {
        $part = PartSetting::create(['part_number' => 'COMMON', 'qty_box' => 10]);
        $exportPart = ExportPartSetting::create(['part_number' => 'COMMON', 'qty_box' => 70]);
        $location = MasterLocation::create(['code' => 'A-1']);
        $exportLocation = ExportMasterLocation::create(['code' => 'A-1']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('part-settings.search'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $part->id)->assertJsonPath('data.0.qty_box', 10);
        $this->getJson(route('export.part-settings.search'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exportPart->id)->assertJsonPath('data.0.qty_box', 70);
        $this->getJson(route('api.locations.search'))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $location->id);
        $this->getJson(route('export.locations.search'))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $exportLocation->id);
        $this->assertNull(PartSetting::find($exportPart->id));
        $this->assertNull(MasterLocation::find($exportLocation->id));
    }

    public function test_domestic_scan_uses_domestic_capacity_and_rejects_export_only_part(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        PartSetting::create(['part_number' => 'COMMON', 'qty_box' => 20]);
        ExportPartSetting::create(['part_number' => 'COMMON', 'qty_box' => 70]);
        ExportPartSetting::create(['part_number' => 'EXPORT-ONLY', 'qty_box' => 80]);
        $this->actingAs($operator)->postJson(route('stock-input.scan-barcode'), ['barcode' => '12345678'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'COMMON', 'pcs_quantity' => 70])->assertUnprocessable();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'COMMON', 'pcs_quantity' => 20])->assertOk();
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '12345679'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'EXPORT-ONLY'])->assertStatus(400);
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_domestic_location_validation_and_assignment_reject_export_location_ids(): void
    {
        $export = ExportMasterLocation::create(['code' => 'EXPORT-AREA']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-ISOLATION']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['stock-input.store', 'move-pallet.store', 'merge-pallet.store'] as $route) {
            $this->postJson(route($route), ['pallet_id' => $pallet->id, 'location_id' => $export->id])
                ->assertUnprocessable()->assertJsonValidationErrors('location_id');
        }

        try {
            DB::transaction(fn () => app(LocationAssignmentService::class)->claim($export->id, $pallet->id));
            $this->fail('Domestic assignment accepted an export location');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(MasterLocation::class, $exception->getModel());
        }
        $this->assertFalse($export->fresh()->is_occupied);
        $this->assertDatabaseCount('stock_locations', 0);
    }

    public function test_location_observer_does_not_occupy_export_location_with_same_code(): void
    {
        $domestic = MasterLocation::create(['code' => 'A-1']);
        $export = ExportMasterLocation::create(['code' => 'A-1']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-DOMESTIC']);
        DB::transaction(fn () => app(LocationAssignmentService::class)->assign($domestic->id, $pallet));
        $this->assertTrue($domestic->fresh()->is_occupied);
        $this->assertFalse($export->fresh()->is_occupied);
        $this->assertNull($export->fresh()->current_pallet_id);
    }

    public function test_saved_export_permissions_are_upgraded_once_and_can_be_revoked_normally(): void
    {
        $record = RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $migration = require database_path('migrations/2026_10_28_000003_enable_export_master_features.php');
        $migration->up();
        $migration->up();
        $this->assertSame(['export_parts', 'export_locations'], $record->fresh()->permissions);
        $this->assertSame(1, AuditLog::where('type', 'role_feature_access')->count());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('access.update'), ['role' => 'export_operator', 'permissions' => []])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->get(route('export.part-settings.index'))->assertForbidden();
        $this->get(route('export.locations.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertSee('Belum ada fitur yang dapat diakses')
            ->assertDontSee(route('export.part-settings.index'), false)
            ->assertDontSee(route('export.locations.index'), false);
    }

    public function test_database_enforces_part_uniqueness_inside_each_scope(): void
    {
        ExportPartSetting::create(['part_number' => 'SAME', 'qty_box' => 70]);
        $this->expectException(QueryException::class);
        ExportPartSetting::create(['part_number' => 'SAME', 'qty_box' => 80]);
    }
}
