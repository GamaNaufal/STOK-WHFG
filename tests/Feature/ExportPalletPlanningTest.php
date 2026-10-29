<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportPalletPlan;
use App\Models\ExportPartSetting;
use App\Models\MasterLocation;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportPalletPlanningTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $a;
    private ExportPartSetting $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->a = ExportPartSetting::create(['part_number' => 'EXP-A', 'qty_box' => 10]);
        $this->b = ExportPartSetting::create(['part_number' => 'EXP-B', 'qty_box' => 20]);
    }

    private function delivery(): ExportDeliveryPlan
    {
        return app(ExportDeliveryPlanService::class)->save([
            'plan_code' => 'RDPAL-001', 'customer_name' => 'Customer Ekspor', 'delivery_deadline' => '2026-11-20',
            'source_type' => 'manual', 'items' => [
                ['part_number' => $this->a->part_number, 'pcs_quantity' => 100],
                ['part_number' => $this->b->part_number, 'pcs_quantity' => 80],
            ],
        ], $this->operator);
    }

    private function row(?int $location, int $a, int $b = 0, ?int $id = null): array
    {
        $items = [];
        if ($a) $items[] = ['part_setting_id' => $this->a->id, 'box_quantity' => $a];
        if ($b) $items[] = ['part_setting_id' => $this->b->id, 'box_quantity' => $b];
        return ['id' => $id, 'master_location_id' => $location, 'items' => $items];
    }

    private function payload(ExportDeliveryPlan $delivery, array $rows, string $action = 'draft', array $overrides = []): array
    {
        $plan = $delivery->palletPlan()->first();
        return array_replace(['revision' => $plan?->revision ?? 0, 'delivery_revision' => $delivery->fresh()->revision,
            'action' => $action, 'pallets' => $rows], $overrides);
    }

    private function save(ExportDeliveryPlan $delivery, array $rows, string $action = 'draft')
    {
        return $this->putJson(route('export.pallet-plans.update', $delivery), $this->payload($delivery, $rows, $action));
    }

    private function finalized(ExportDeliveryPlan $delivery): array
    {
        $l1 = ExportMasterLocation::firstOrCreate(['code' => 'A1']);
        $l2 = ExportMasterLocation::firstOrCreate(['code' => 'A2']);
        $rows = [$this->row($l1->id, 6, 4), $this->row($l2->id, 4)];
        $this->save($delivery, $rows, 'finalize')->assertRedirect();
        return [$delivery->palletPlan()->firstOrFail()->load('pallets.items'), $l1, $l2];
    }

    public function test_access_is_limited_to_export_permission_and_no_get_creates_a_plan(): void
    {
        $delivery = $this->delivery();
        $this->get(route('export.pallet-plans.index'))->assertOk();
        $this->get(route('export.pallet-plans.edit', $delivery))->assertOk()->assertSee('Susunan pallet');
        $this->assertDatabaseCount('export_pallet_plans', 0);
        foreach (['warehouse_operator', 'admin_warehouse', 'supervisi', 'ppc', 'sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('export.pallet-plans.index'))->assertForbidden();
            $this->save($delivery, [$this->row(null, 1)])->assertForbidden();
        }
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_delivery_plans']]);
        $this->actingAs($this->operator)->get(route('export.pallet-plans.edit', $delivery))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('export.pallet-plans.index'))->assertOk();
    }

    public function test_partial_draft_supports_mixed_parts_and_empty_pallet_without_mutating_inventory(): void
    {
        $delivery = $this->delivery();
        $this->save($delivery, [$this->row(null, 3, 1), $this->row(null, 0)])->assertRedirect();
        $plan = ExportPalletPlan::with('pallets.items')->sole();
        $this->assertSame('draft', $plan->status);
        $this->assertSame([1, 2], $plan->pallets->pluck('position')->all());
        $this->assertCount(2, $plan->pallets[0]->items);
        $this->assertSame(10, $plan->pallets[0]->items[0]->pcs_per_box);
        $this->get(route('export.pallet-plans.show', $delivery))->assertOk()->assertSee('Kurang 7');
        foreach (['boxes', 'pallets', 'stock_locations', 'export_pallet_location_reservations'] as $table) $this->assertDatabaseCount($table, 0);
        $this->assertSame(1, AuditLog::where('type', 'export_pallet_plan')->count());
    }

    public function test_exact_finalization_claims_only_export_locations_and_is_not_directly_editable(): void
    {
        $delivery = $this->delivery();
        [$plan, $l1] = $this->finalized($delivery);
        $this->assertSame('final', $plan->status);
        $this->assertSame($this->operator->id, $plan->finalized_by);
        $this->assertDatabaseCount('export_pallet_location_reservations', 2);
        $this->assertFalse($l1->fresh()->is_occupied);
        $this->assertFalse(ExportMasterLocation::available()->whereKey($l1->id)->exists());
        $this->get(route('export.locations.index'))->assertOk()->assertSee('Direservasi');
        $this->getJson(route('export.locations.search', ['q' => 'A1']))->assertOk()->assertExactJson([]);
        $this->get(route('export.pallet-plans.edit', $delivery))->assertRedirect(route('export.pallet-plans.show', $delivery));
        $this->save($delivery, [$this->row(null, 1)])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_invalid_finalizations_are_atomic_and_cannot_forge_parts_or_locations(): void
    {
        $delivery = $this->delivery();
        $l1 = ExportMasterLocation::create(['code' => 'A1']);
        $domestic = MasterLocation::create(['code' => 'DOM']);
        $cases = [[$this->row($l1->id, 9, 4)], [$this->row($l1->id, 11, 4)],
            [$this->row(null, 10, 4)], [$this->row($l1->id, 0)],
            [$this->row($l1->id, 5, 4), $this->row($l1->id, 5)], [$this->row($domestic->id, 10, 4)],
            [['master_location_id' => $l1->id, 'items' => [['part_setting_id' => 99999, 'box_quantity' => 10]]]],
            [['master_location_id' => $l1->id, 'items' => [['part_setting_id' => $this->a->id, 'box_quantity' => 5], ['part_setting_id' => $this->a->id, 'box_quantity' => 5]]]],
        ];
        foreach ($cases as $rows) $this->save($delivery, $rows, 'finalize')->assertUnprocessable();
        $this->assertDatabaseCount('export_pallet_plans', 0);
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
        $this->assertSame(0, AuditLog::where('type', 'export_pallet_plan')->count());
    }

    public function test_reserved_location_is_unavailable_to_other_plans_without_partial_claims(): void
    {
        $first = $this->delivery();
        [, , $held] = $this->finalized($first);
        $second = $this->delivery();
        $free = ExportMasterLocation::create(['code' => 'A3']);
        $this->save($second, [$this->row($free->id, 6, 4), $this->row($held->id, 4)], 'finalize')
            ->assertUnprocessable()->assertJsonValidationErrors('pallets.1.master_location_id');
        $this->assertDatabaseMissing('export_pallet_location_reservations', ['master_location_id' => $free->id]);
        $this->assertDatabaseCount('export_pallet_plans', 1);
    }

    public function test_reopen_retains_claims_and_cancel_releases_them_but_preserves_allocations(): void
    {
        $delivery = $this->delivery();
        [$plan] = $this->finalized($delivery);
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => 1, 'action' => 'reopen'])->assertSessionHasNoErrors();
        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 2);
        $this->postJson(route('export.pallet-plans.transition', $delivery), ['revision' => 1, 'action' => 'cancel'])->assertUnprocessable();
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => 2, 'action' => 'cancel'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
        $this->assertDatabaseCount('export_planned_pallet_items', 3);
    }

    public function test_stale_revisions_and_foreign_pallet_ids_cannot_overwrite_a_plan(): void
    {
        $delivery = $this->delivery();
        $payload = $this->payload($delivery, [$this->row(null, 2)]);
        $this->putJson(route('export.pallet-plans.update', $delivery), $payload)->assertRedirect();
        $this->putJson(route('export.pallet-plans.update', $delivery), $payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->putJson(route('export.pallet-plans.update', $delivery), $this->payload($delivery, [$this->row(null, 3)], 'draft', ['delivery_revision' => 99]))
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_revision');
        $this->save($delivery, [$this->row(null, 3, 0, 99999)])->assertUnprocessable()->assertJsonValidationErrors('pallets.0.id');
        $this->assertSame(2, ExportPalletPlan::sole()->pallets->first()->items->first()->box_quantity);
    }

    public function test_pallet_numbering_is_local_and_survives_removal_without_unique_collisions(): void
    {
        $delivery = $this->delivery();
        $this->save($delivery, [$this->row(null, 2), $this->row(null, 0, 4), $this->row(null, 8)])->assertRedirect();
        $plan = ExportPalletPlan::with('pallets.items')->sole();
        $keep = $plan->pallets->slice(1)->values();
        $this->save($delivery, [$this->row(null, 0, 4, $keep[0]->id), $this->row(null, 10, 0, $keep[1]->id)])->assertRedirect();
        $plan->refresh();
        $this->assertSame([1, 2], $plan->pallets->pluck('position')->all());
        $this->assertSame($keep->pluck('id')->all(), $plan->pallets->pluck('id')->all());
        $other = $this->delivery();
        $this->save($other, [$this->row(null, 1)])->assertRedirect();
        $this->assertSame(1, $other->palletPlan()->first()->pallets->first()->position);
    }

    public function test_delivery_revisions_keep_allocations_claims_and_flag_shortages_or_excess(): void
    {
        $delivery = $this->delivery();
        [$plan] = $this->finalized($delivery);
        $ids = $plan->pallets->pluck('id')->all();
        $data = app(ExportDeliveryPlanService::class)->snapshot($delivery->load('items'));
        $data['items'] = [['part_number' => 'EXP-A', 'pcs_quantity' => 120], ['part_number' => 'EXP-B', 'pcs_quantity' => 80]];
        $delivery = app(ExportDeliveryPlanService::class)->save($data, $this->operator, $delivery->id);
        $plan->refresh();
        $this->assertSame('draft', $plan->status);
        $this->assertSame($ids, $plan->pallets->pluck('id')->all());
        $this->assertDatabaseCount('export_pallet_location_reservations', 2);
        $summary = app(ExportPalletPlanningService::class)->requirements($delivery, app(ExportPalletPlanningService::class)->rows($plan));
        $this->assertSame(2, $summary[0]['remaining']);
        $this->get(route('export.pallet-plans.show', $delivery))->assertOk()->assertSee('Rencana delivery telah direvisi');
        $rows = app(ExportPalletPlanningService::class)->rows($plan);
        $this->save($delivery, $rows, 'finalize')->assertUnprocessable();
        $rows[1]['items'][0]['box_quantity'] = 6;
        $this->save($delivery, $rows, 'finalize')->assertRedirect();
        $this->assertSame('final', $plan->fresh()->status);
        $this->assertSame(1, AuditLog::where('type', 'export_pallet_plan')->where('action', 'delivery_revised')->count());
    }

    public function test_removed_delivery_part_remains_in_draft_until_operator_removes_its_allocation(): void
    {
        $delivery = $this->delivery();
        [$plan] = $this->finalized($delivery);
        $data = app(ExportDeliveryPlanService::class)->snapshot($delivery->load('items'));
        $data['items'] = [['part_number' => 'EXP-A', 'pcs_quantity' => 80]];
        $delivery = app(ExportDeliveryPlanService::class)->save($data, $this->operator, $delivery->id);
        $plan->refresh();
        $this->get(route('export.pallet-plans.edit', $delivery))->assertOk()->assertSee('EXP-B');
        $rows = app(ExportPalletPlanningService::class)->rows($plan);
        $this->save($delivery, $rows)->assertRedirect();
        $this->save($delivery, $rows, 'finalize')->assertUnprocessable();
        $this->delete(route('export.part-settings.destroy', $this->b))->assertSessionHas('error');
        $this->assertDatabaseHas('export_planned_pallet_items', ['part_setting_id' => $this->b->id, 'box_quantity' => 4]);
        $this->assertDatabaseCount('export_pallet_location_reservations', 2);
    }

    public function test_archive_requires_cancellation_and_master_identity_is_protected(): void
    {
        $delivery = $this->delivery();
        [$plan, $loc] = $this->finalized($delivery);
        $this->get(route('export.delivery-plans.show', $delivery))->assertOk()
            ->assertSee('Batalkan perencanaan pallet terlebih dahulu')->assertDontSee('id="archive-plan"', false);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('pallet_plan');
        $this->put(route('export.locations.update', $loc), ['code' => 'RENAMED'])->assertSessionHas('error');
        $this->delete(route('export.locations.destroy', $loc))->assertSessionHas('error');
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => $plan->revision, 'action' => 'cancel'])->assertSessionHasNoErrors();
        $this->delete(route('export.delivery-plans.destroy', $delivery), ['revision' => 1])->assertRedirect();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $delivery->id]);
        $this->assertDatabaseCount('export_planned_pallet_items', 3);
        $this->get(route('export.pallet-plans.show', $delivery))->assertNotFound();
    }

    public function test_revising_location_releases_only_dropped_claim_and_reserves_new_location_on_finalization(): void
    {
        $delivery = $this->delivery();
        [$plan, $old, $retained] = $this->finalized($delivery);
        $new = ExportMasterLocation::create(['code' => 'NEW']);
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => 1, 'action' => 'reopen'])->assertSessionHasNoErrors();
        $rows = app(ExportPalletPlanningService::class)->rows($plan->fresh());
        $rows[0]['master_location_id'] = $new->id;
        $this->save($delivery, $rows)->assertRedirect();
        $this->assertDatabaseMissing('export_pallet_location_reservations', ['master_location_id' => $old->id]);
        $this->assertDatabaseMissing('export_pallet_location_reservations', ['master_location_id' => $new->id]);
        $this->assertDatabaseHas('export_pallet_location_reservations', ['master_location_id' => $retained->id]);
        $this->save($delivery, $rows, 'finalize')->assertRedirect();
        $this->assertDatabaseHas('export_pallet_location_reservations', ['master_location_id' => $new->id]);
    }

    public function test_occupied_locations_and_invalid_json_are_rejected(): void
    {
        $delivery = $this->delivery();
        $loc = ExportMasterLocation::create(['code' => 'OCCUPIED', 'is_occupied' => true]);
        $this->save($delivery, [$this->row($loc->id, 10, 4)], 'finalize')->assertUnprocessable()->assertJsonValidationErrors('pallets.0.master_location_id');
        $payload = $this->payload($delivery, [$this->row(null, 2)]);
        unset($payload['pallets']);
        $this->putJson(route('export.pallet-plans.update', $delivery), $payload + ['pallets_json' => '{bad-json'])
            ->assertUnprocessable()->assertJsonValidationErrors('pallets');
        $this->putJson(route('export.pallet-plans.update', $delivery), $payload + ['pallets_json' => json_encode([$this->row(null, 2)])])->assertRedirect();
        $payload = $this->payload($delivery, []);
        unset($payload['pallets']);
        $emptyLocation = $this->row(null, 2);
        $emptyLocation['master_location_id'] = '';
        $this->putJson(route('export.pallet-plans.update', $delivery), $payload + ['pallets_json' => json_encode([$emptyLocation])])->assertRedirect();
        $this->assertNull($delivery->palletPlan()->first()->pallets->first()->master_location_id);
    }

    public function test_migration_preserves_saved_role_settings_and_adds_planning_permission(): void
    {
        foreach (['export_pallet_location_reservations', 'export_planned_pallet_items', 'export_planned_pallets', 'export_pallet_plans'] as $table) Schema::drop($table);
        $access = RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_parts']]);
        $migration = require database_path('migrations/2026_10_28_000006_create_export_pallet_planning.php');
        $migration->up();
        $this->assertSame(['export_parts', 'export_pallet_plans'], $access->fresh()->permissions);
        $this->assertSame(1, AuditLog::where('type', 'role_feature_access')->whereNull('user_id')->count());
        $this->assertTrue(Schema::hasTable('export_pallet_plans'));
    }
}
