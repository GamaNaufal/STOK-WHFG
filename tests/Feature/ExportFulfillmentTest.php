<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportPalletPlan;
use App\Models\ExportPartSetting;
use App\Models\ExportPlannedPalletItem;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ExportPartSetting $a;
    private ExportPartSetting $b;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->a = ExportPartSetting::create(['part_number' => 'FUL-A', 'qty_box' => 10]);
        $this->b = ExportPartSetting::create(['part_number' => 'FUL-B', 'qty_box' => 20]);
    }

    private function plan(string $code = 'FUL-001', string $deadline = '2026-11-20', bool $final = true, int $aBoxes = 4): ExportDeliveryPlan
    {
        $delivery = app(ExportDeliveryPlanService::class)->save([
            'plan_code' => $code, 'customer_name' => 'Customer '.$code, 'delivery_deadline' => $deadline,
            'source_type' => 'manual', 'items' => [
                ['part_number' => $this->a->part_number, 'pcs_quantity' => $aBoxes * 10],
                ['part_number' => $this->b->part_number, 'pcs_quantity' => 40],
            ],
        ], $this->operator);
        $rows = [];
        foreach ([[$aBoxes - 2, 2], [2, 0]] as $i => [$a, $b]) {
            $location = ExportMasterLocation::create(['code' => $code.'-'.($i + 1)]);
            $items = [['part_setting_id' => $this->a->id, 'box_quantity' => $a]];
            if ($b) $items[] = ['part_setting_id' => $this->b->id, 'box_quantity' => $b];
            $rows[] = ['master_location_id' => $location->id, 'items' => $items];
        }
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
            'action' => $final ? 'finalize' : 'draft', 'pallets' => $rows], $this->operator);
        return $delivery->fresh(['palletPlan.pallets.items']);
    }

    private function box(ExportPlannedPalletItem $item, bool $placed = false, array $override = []): ExportFulfillmentBox
    {
        $item->loadMissing('pallet');
        return ExportFulfillmentBox::create(array_replace([
            'box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
            'export_planned_pallet_item_id' => $item->id, 'part_setting_id' => $item->part_setting_id,
            'pcs_quantity' => $item->pcs_per_box, 'first_received_at' => '2026-10-01 08:00:00',
            'reserved_at' => '2026-10-29 08:00:00', 'reserved_by' => $this->operator->id,
            'placed_at' => $placed ? '2026-10-29 08:10:00' : null,
            'placed_by' => $placed ? $this->operator->id : null,
            'placed_location_id' => $placed ? $item->pallet->master_location_id : null,
        ], $override));
    }

    public function test_empty_monitor_does_not_create_planning_inventory_or_fulfillment(): void
    {
        $before = AuditLog::count();
        $this->get(route('export.fulfillment.index'))->assertOk()->assertSee('Belum ada permintaan untuk dipenuhi');
        foreach (['export_pallet_plans', 'export_fulfillment_boxes', 'boxes', 'pallets', 'stock_locations'] as $table) $this->assertDatabaseCount($table, 0);
        $this->assertSame($before, AuditLog::count());
    }

    public function test_final_plans_enter_automatically_and_reopened_plans_remain_visible_on_hold(): void
    {
        $draft = $this->plan('DRAFT-ONLY', final: false);
        $delivery = $this->plan('FINAL-VISIBLE');
        $this->get(route('export.fulfillment.index'))->assertOk()->assertSee('FINAL-VISIBLE')->assertDontSee('DRAFT-ONLY');
        $this->get(route('export.fulfillment.show', $draft))->assertNotFound();
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('metrics', fn ($m) => $m['required'] === 6 && $m['fulfilled'] === 0 && $m['remaining'] === 6)
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'waiting');
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => 1, 'action' => 'reopen'])->assertSessionHasNoErrors();
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertSee('Perencanaan perlu diselesaikan kembali.')
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'paused');
        $rows = app(ExportPalletPlanningService::class)->rows($delivery->palletPlan()->first());
        foreach ($rows as &$row) $row['master_location_id'] = null;
        unset($row);
        $this->putJson(route('export.pallet-plans.update', $delivery), ['revision' => 2, 'delivery_revision' => 1,
            'action' => 'draft', 'pallets' => $rows])->assertRedirect();
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'paused');
        $this->post(route('export.pallet-plans.transition', $delivery), ['revision' => 3, 'action' => 'cancel'])->assertSessionHasNoErrors();
        $this->get(route('export.fulfillment.show', $delivery))->assertNotFound();
        $this->get(route('export.fulfillment.index'))->assertDontSee('FINAL-VISIBLE');
    }

    public function test_permission_is_independent_revocable_and_enforced_for_direct_requests(): void
    {
        $delivery = $this->plan();
        foreach (['warehouse_operator', 'admin_warehouse', 'supervisi', 'ppc', 'sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('export.fulfillment.index'))->assertForbidden();
            $this->get(route('export.fulfillment.show', $delivery))->assertForbidden();
        }
        $setting = RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_fulfillment']]);
        $this->actingAs($this->operator)->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertDontSee(route('export.pallet-plans.show', $delivery), false);
        $this->get(route('dashboard'))->assertOk()->assertSee('Pantau pemenuhan');
        $setting->update(['permissions' => []]);
        $this->get(route('export.fulfillment.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('export.fulfillment.index'))->assertOk();
    }

    public function test_reservations_are_not_fulfilled_until_placement_and_domestic_boxes_do_not_count(): void
    {
        $delivery = $this->plan();
        [$p1, $p2] = $delivery->palletPlan->pallets;
        $this->box($p1->items[0], true);
        $pending = $this->box($p1->items[0], override: ['first_received_at' => '2026-09-01 08:00:00']);
        $this->box($p1->items[1], true);
        $this->box($p1->items[1], true);
        Box::create(['box_number' => '99999999', 'part_number' => 'FUL-A', 'pcs_quantity' => 10,
            'qr_code' => 'DOMESTIC-TEST-BOX', 'user_id' => $this->operator->id]);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertSee('00000002')
            ->assertDontSee('99999999')->assertSee('Menunggu penempatan')
            ->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 3 && $m['reserved'] === 1 && $m['remaining'] === 3 && $m['unreserved'] === 2 && $m['percent'] === 50)
            ->assertViewHas('pallets', fn ($p) => $p[0]['metrics']['fulfilled'] === 3 && $p[1]['metrics']['remaining'] === 2)
            ->assertViewHas('boxes', fn ($boxes) => $boxes->first()->id === $pending->id && $boxes->total() === 4)
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'partial');
        $this->assertDatabaseCount('boxes', 1);
        $this->assertDatabaseCount('pallets', 0);
    }

    public function test_full_reservation_remains_in_progress_and_confirmation_uses_capacity_snapshot(): void
    {
        $delivery = $this->plan();
        foreach ($delivery->palletPlan->pallets as $pallet) foreach ($pallet->items as $item) {
            for ($n = 0; $n < $item->box_quantity; $n++) $this->box($item);
        }
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 0 && $m['reserved'] === 6 && $m['remaining'] === 6 && $m['unreserved'] === 0)
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'partial');
        $this->a->update(['qty_box' => 100]);
        foreach (ExportFulfillmentBox::with('plannedItem.pallet')->get() as $box) {
            $box->update(['placed_at' => now(), 'placed_by' => $this->operator->id, 'placed_location_id' => $box->plannedItem->pallet->master_location_id]);
        }
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 6 && $m['reserved'] === 0 && $m['remaining'] === 0 && $m['percent'] === 100)
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'complete');
    }

    public function test_wrong_part_quantity_location_and_incomplete_confirmation_are_visible_but_not_counted(): void
    {
        $delivery = $this->plan();
        $item = $delivery->palletPlan->pallets[0]->items[0];
        $this->box($item, true, ['part_setting_id' => $this->b->id]);
        $this->box($item, true, ['pcs_quantity' => 9]);
        $this->box($item, true, ['placed_location_id' => $delivery->palletPlan->pallets[1]->master_location_id]);
        $this->box($item, true, ['placed_by' => null]);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertSee('Periksa pencatatan box.')
            ->assertViewHas('metrics', fn ($m) => $m['invalid'] === 4 && $m['fulfilled'] === 0 && $m['reserved'] === 0 && $m['remaining'] === 6)
            ->assertViewHas('boxes', fn ($boxes) => $boxes->every(fn ($box) => $box->display_status === 'invalid'))
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'attention');
    }

    public function test_excess_on_one_part_cannot_hide_unfilled_requirements_on_another(): void
    {
        $delivery = $this->plan();
        foreach ($delivery->palletPlan->pallets as $pallet) for ($i = 0; $i < 3; $i++) $this->box($pallet->items[0], true);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('overview', fn ($p) => $p->fulfillment_status === 'attention')
            ->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 6 && $m['required'] === 6 && $m['remaining'] === 2 && $m['excess'] === 2 && $m['percent'] === 66)
            ->assertViewHas('pallets', fn ($p) => $p[0]['metrics']['remaining'] === 2);
    }

    public function test_index_filters_searches_orders_deadlines_and_ignores_archived_plans(): void
    {
        $late = $this->plan('LATE-CODE', '2026-12-02');
        $early = $this->plan('EARLY-CODE', '2026-11-01');
        $this->box($late->palletPlan->pallets[0]->items[0]);
        $this->get(route('export.fulfillment.index'))->assertOk()->assertSeeInOrder(['EARLY-CODE', 'LATE-CODE']);
        $this->get(route('export.fulfillment.index', ['status' => 'partial', 'q' => 'Customer LATE']))->assertOk()
            ->assertViewHas('plans', fn ($plans) => $plans->total() === 1 && $plans->first()->delivery_id === $late->id);
        $this->get(route('export.fulfillment.index', ['q' => 'NO-MATCH']))->assertOk()->assertSee('Tidak ada rencana yang cocok');
        $early->delete();
        $this->get(route('export.fulfillment.index'))->assertDontSee('EARLY-CODE');
        $this->get(route('export.fulfillment.show', $early))->assertNotFound();
        $this->getJson(route('export.fulfillment.index', ['q' => ['bad'], 'status' => 'unknown']))->assertUnprocessable();
    }

    public function test_box_list_is_paginated_in_original_receipt_order_with_stable_box_ties(): void
    {
        $delivery = $this->plan(aBoxes: 30);
        $item = $delivery->palletPlan->pallets[0]->items[0];
        for ($i = 0; $i < 26; $i++) $this->box($item);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 26 && $boxes->count() === 25 && $boxes->first()->box_number === '00000001');
        $this->get(route('export.fulfillment.show', [$delivery, 'page' => 2]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->count() === 1 && $boxes->first()->box_number === '00000026');
    }

    public function test_commitments_block_reopen_cancel_and_delivery_changes_atomically(): void
    {
        $delivery = $this->plan();
        $box = $this->box($delivery->palletPlan->pallets[0]->items[0], true);
        $audits = AuditLog::count();
        foreach (['reopen', 'cancel'] as $action) $this->postJson(route('export.pallet-plans.transition', $delivery),
            ['revision' => 1, 'action' => $action])->assertUnprocessable()->assertJsonValidationErrors('fulfillment');
        $this->putJson(route('export.delivery-plans.update', $delivery), [
            'request_token' => (string) \Illuminate\Support\Str::uuid(),
            'revision' => 1, 'plan_code' => 'CHANGED', 'customer_name' => 'Changed', 'delivery_deadline' => '2026-12-01',
            'source_type' => 'manual', 'items' => [['part_number' => $this->a->part_number, 'pcs_quantity' => 20]],
        ])->assertUnprocessable()->assertJsonValidationErrors('fulfillment');
        $this->assertSame('FUL-001', $delivery->fresh()->plan_code);
        $this->assertSame(6, $delivery->fresh()->total_boxes);
        $this->assertSame(1, $delivery->fresh()->revision);
        $this->assertSame('final', $delivery->palletPlan()->first()->status);
        $this->assertSame($audits, AuditLog::count());
        $this->assertDatabaseHas('export_fulfillment_boxes', ['id' => $box->id]);
        $this->assertDatabaseCount('export_pallet_location_reservations', 2);
    }

    public function test_defensive_draft_save_cannot_delete_committed_items(): void
    {
        $delivery = $this->plan();
        $plan = $delivery->palletPlan;
        $box = $this->box($plan->pallets[0]->items[0]);
        $plan->update(['status' => 'draft']);
        $this->putJson(route('export.pallet-plans.update', $delivery), ['revision' => 1, 'delivery_revision' => 1,
            'action' => 'draft', 'pallets' => [['items' => []]]])->assertUnprocessable()->assertJsonValidationErrors('fulfillment');
        $this->assertDatabaseHas('export_fulfillment_boxes', ['id' => $box->id]);
        $this->assertDatabaseCount('export_planned_pallets', 2);
    }

    public function test_one_box_cannot_be_committed_to_multiple_requirements(): void
    {
        $delivery = $this->plan();
        $box = $this->box($delivery->palletPlan->pallets[0]->items[0]);
        $this->expectException(QueryException::class);
        $this->box($delivery->palletPlan->pallets[1]->items[0], override: ['box_number' => $box->box_number]);
    }

    public function test_migration_preserves_saved_permissions_and_initializes_empty_tracking(): void
    {
        $final = $this->plan('LEGACY-FINAL');
        $revised = $this->plan('LEGACY-REVISED');
        $draft = $this->plan('LEGACY-DRAFT', final: false);
        $this->post(route('export.pallet-plans.transition', $revised), ['revision' => 1, 'action' => 'reopen'])->assertSessionHasNoErrors();
        $rows = app(ExportPalletPlanningService::class)->rows($revised->palletPlan()->first());
        foreach ($rows as &$row) $row['master_location_id'] = null;
        unset($row);
        $this->putJson(route('export.pallet-plans.update', $revised), ['revision' => 2, 'delivery_revision' => 1,
            'action' => 'draft', 'pallets' => $rows])->assertRedirect();
        Schema::drop('export_fulfillment_boxes');
        Schema::table('export_pallet_plans', fn ($table) => $table->dropColumn('entered_fulfillment'));
        $setting = RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_parts']]);
        $migration = require database_path('migrations/2026_10_29_000001_create_export_fulfillment_monitoring.php');
        $migration->up();
        $this->assertSame(['export_parts', 'export_fulfillment'], $setting->fresh()->permissions);
        $this->assertDatabaseCount('export_fulfillment_boxes', 0);
        $this->assertSame(1, AuditLog::where('type', 'role_feature_access')->whereNull('user_id')->count());
        $this->assertTrue($final->palletPlan()->first()->entered_fulfillment);
        $this->assertTrue($revised->palletPlan()->first()->entered_fulfillment);
        $this->assertFalse($draft->palletPlan()->first()->entered_fulfillment);
    }
}
