<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportMasterLocation;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\LocationAssignmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_export_operator_manages_the_same_master_and_locations_stay_separate(): void
    {
        $this->withoutVite();
        $part = PartSetting::create(['part_number' => 'SHARED', 'qty_box' => 20]);
        MasterLocation::create(['code' => 'LOCAL-AREA']);
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->get(route('part-settings.index'))->assertOk()->assertSee('SHARED');
        $this->get(route('export.locations.index'))->assertOk()->assertDontSee('LOCAL-AREA');
        $this->get(route('locations.index'))->assertForbidden();
        $this->postJson(route('part-settings.store'), ['part_number' => 'SHARED', 'qty_box' => 70])
            ->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $this->put(route('part-settings.update', $part), ['part_number' => 'SHARED', 'qty_box' => 30])->assertSessionHasNoErrors();
        $this->assertSame(30, PartSetting::sole()->qty_box);
        $this->assertSame('shared', AuditLog::where('type', 'master_part')->sole()->getNewValuesArray()['scope']);
        $this->get('/export/part-settings')->assertNotFound();
    }

    public function test_shared_capacity_is_frozen_by_active_export_plans_and_history_snapshots_survive(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($operator);
        $part = PartSetting::create(['part_number' => 'CAPACITY', 'qty_box' => 10]);
        $plan = app(\App\Services\ExportDeliveryPlanService::class)->save(['plan_code' => 'CAPACITY-RD', 'customer_name' => 'Customer',
            'delivery_deadline' => '2026-11-20', 'source_type' => 'manual', 'items' => [['part_number' => 'CAPACITY', 'pcs_quantity' => 20]]], $operator);
        $url = route('part-settings.update', $part);
        $this->put($url, ['part_number' => 'CAPACITY', 'qty_box' => 20])->assertSessionHasErrors('qty_box');
        $this->put($url, ['part_number' => 'CAPACITY', 'qty_box' => 10])->assertSessionHasNoErrors();
        $this->put($url, ['part_number' => 'RENAMED', 'qty_box' => 10])->assertSessionHas('error');
        $this->delete(route('part-settings.destroy', $part))->assertSessionHas('error');
        $plan->update(['status' => 'completed']);
        $plan->delete();
        $this->put($url, ['part_number' => 'CAPACITY', 'qty_box' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20, $part->fresh()->qty_box);
        $this->assertSame(10, $plan->items()->sole()->pcs_per_box);
        $this->delete(route('part-settings.destroy', $part))->assertSessionHas('error');
    }

    public function test_shared_part_lookup_is_available_without_granting_master_management(): void
    {
        PartSetting::create(['part_number' => 'COMMON', 'qty_box' => 10]);
        $operator = User::factory()->create(['role' => 'export_operator']);
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_delivery_plans']]);
        $this->actingAs($operator)->getJson(route('part-settings.search'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.part_number', 'COMMON');
        $this->get(route('part-settings.index'))->assertForbidden();
        $this->postJson(route('part-settings.store'), ['part_number' => 'OTHER', 'qty_box' => 10])->assertForbidden();
    }

    public function test_debug_default_does_not_expand_production_permissions_and_explicit_revocation_wins(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator']);
        $access = app(FeatureAccessService::class);
        $this->assertTrue($access->allows($operator, 'parts'));
        $this->app->detectEnvironment(fn () => 'production');
        request()->attributes->remove('feature_access.export_operator');
        $this->assertFalse($access->allows($operator, 'parts'));
        $this->app->detectEnvironment(fn () => 'testing');
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        request()->attributes->remove('feature_access.export_operator');
        $this->assertFalse($access->allows($operator, 'parts'));
    }

    public function test_location_ids_cannot_cross_scopes_even_for_admin(): void
    {
        $domestic = MasterLocation::create(['code' => 'A']);
        $export = ExportMasterLocation::create(['code' => 'A']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([['export.locations', $domestic], ['locations', $export]] as [$prefix,$location]) {
            $this->get(route($prefix.'.edit', $location))->assertNotFound();
            $this->putJson(route($prefix.'.update', $location), ['code' => 'HACK'])->assertNotFound();
            $this->deleteJson(route($prefix.'.destroy', $location))->assertNotFound();
        }
        $this->assertDatabaseCount('master_locations', 2);
    }

    public function test_domestic_scan_uses_the_same_master_capacity(): void
    {
        PartSetting::create(['part_number' => 'COMMON', 'qty_box' => 20]);
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']));
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '12345678'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'COMMON', 'pcs_quantity' => 70])->assertUnprocessable();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'COMMON', 'pcs_quantity' => 20])->assertOk();
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
}
