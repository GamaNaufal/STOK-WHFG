<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Box, ExportFulfillmentBox, ExportMasterLocation, ExportPartSetting, ExportStockBox, ExportStockMovement, ExportStockPallet, MasterLocation, PartSetting, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportStockReceivingTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $part;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'EX-A', 'qty_box' => 10]);
    }

    private function receive(?string $number = null, array $override = []): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(array_replace([
            'box_number' => $number ?? str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid(),
        ], $override), $this->operator);
    }

    private function placement(ExportStockBox $box, ?string $destination = null, array $overrides = [])
    {
        return $this->postJson(route('export.receiving.place', $box), array_replace([
            'revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid(), 'destination' => $destination,
        ], $overrides));
    }

    private function plan(string $code = 'EX-PLAN', string $deadline = '2026-11-20', int $pallets = 2)
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => 'Customer '.$code,
            'delivery_deadline' => $deadline, 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => $this->part->qty_box * $pallets]],
        ], $this->operator);
        $rows = [];
        for ($i = 1; $i <= $pallets; $i++) {
            $location = ExportMasterLocation::create(['code' => $code.'-'.$i]);
            $rows[] = ['master_location_id' => $location->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]];
        }
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
            'action' => 'finalize', 'pallets' => $rows], $this->operator);
        return $delivery->fresh(['palletPlan.pallets.items']);
    }

    public function test_empty_page_and_scan_lookups_are_read_only(): void
    {
        $this->get(route('export.receiving.index'))->assertOk()->assertSee('Belum ada box dalam daftar scan.');
        $this->postJson(route('export.receiving.check-box'), ['box_number' => '00000001'])->assertOk()->assertJsonPath('available', true);
        $this->getJson(route('export.receiving.part', ['part_number' => 'EX-A']))->assertOk()->assertJsonPath('pcs_quantity', 10);
        foreach (['export_stock_boxes', 'export_stock_pallets', 'export_fulfillment_boxes', 'audit_logs'] as $table) $this->assertDatabaseCount($table, 0);
    }

    public function test_domestic_number_is_allowed_but_export_duplicate_is_rejected(): void
    {
        Box::create(['box_number' => '00000001', 'part_number' => 'DOM', 'pcs_quantity' => 20, 'qr_code' => 'TEST', 'user_id' => $this->operator->id]);
        $this->postJson(route('export.receiving.check-box'), ['box_number' => '00000001'])->assertJsonPath('available', true);
        $box = $this->receive('00000001');
        $this->assertSame(10, $box->pcs_quantity);
        $this->assertNull($box->current_pallet_id);
        $this->postJson(route('export.receiving.store'), ['box_number' => '00000001', 'part_number' => 'EX-A', 'receive_token' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('box_number');
        $this->assertDatabaseCount('boxes', 1);
        $this->assertDatabaseCount('box_identities', 1);
        $this->assertDatabaseCount('export_stock_boxes', 1);
        $this->assertDatabaseCount('pallets', 0);
        $this->get(route('export.stock.index', ['box' => $box->id]))->assertOk()->assertSee('00000001');
    }

    public function test_invalid_identity_local_part_and_client_quantity_are_rejected(): void
    {
        PartSetting::create(['part_number' => 'DOM-ONLY', 'qty_box' => 50]);
        foreach (['123', '１２３４５６７８', '1234ABCD'] as $number) $this->postJson(route('export.receiving.store'),
            ['box_number' => $number, 'part_number' => 'EX-A', 'receive_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson(route('export.receiving.store'), ['box_number' => '00000001', 'part_number' => 'DOM-ONLY', 'receive_token' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $this->postJson(route('export.receiving.store'), ['box_number' => '00000001', 'part_number' => 'EX-A', 'pcs_quantity' => 5, 'receive_token' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('pcs_quantity');
        $this->assertDatabaseCount('export_stock_boxes', 0);
    }

    public function test_receive_retries_are_idempotent_and_master_default_cannot_be_overridden(): void
    {
        $token = (string) Str::uuid();
        $data = ['box_number' => '00000001', 'part_number' => 'EX-A', 'receive_token' => $token,
            'first_received_at' => '2000-01-01', 'received_by' => 9999];
        $this->postJson(route('export.receiving.store'), $data)->assertRedirect();
        $box = ExportStockBox::sole();
        $this->assertSame($this->operator->id, (int) $box->received_by);
        $this->assertSame(now()->toDateString(), $box->first_received_at->toDateString());
        $auditCount = AuditLog::count();
        $this->part->update(['qty_box' => 30]);
        $this->postJson(route('export.receiving.store'), $data)->assertRedirect();
        $this->assertSame(10, $box->fresh()->pcs_quantity);
        $this->assertSame($auditCount, AuditLog::count());
        $this->postJson(route('export.receiving.store'), array_replace($data, ['box_number' => '00000002']))
            ->assertUnprocessable()->assertJsonValidationErrors('receive_token');
        $this->assertDatabaseCount('export_stock_boxes', 1);
    }

    public function test_allocation_uses_nearest_deadline_then_smallest_pallet_without_overfilling(): void
    {
        $late = $this->plan('LATE', '2026-12-01', 1);
        $early = $this->plan('EARLY', '2026-11-01', 2);
        $a = $this->receive(); $b = $this->receive(); $c = $this->receive(); $extra = $this->receive();
        $this->assertSame($early->palletPlan->pallets[0]->items[0]->id, $a->fulfillment->export_planned_pallet_item_id);
        $this->assertSame($early->palletPlan->pallets[1]->items[0]->id, $b->fulfillment->export_planned_pallet_item_id);
        $this->assertSame($late->palletPlan->pallets[0]->items[0]->id, $c->fulfillment->export_planned_pallet_item_id);
        $this->assertNull($extra->fulfillment);
        $this->assertDatabaseCount('export_fulfillment_boxes', 3);
        $this->assertDatabaseCount('export_stock_pallets', 0);
    }

    public function test_confirmation_creates_physical_export_stock_and_updates_fulfillment_only_after_placement(): void
    {
        $delivery = $this->plan(pallets: 1);
        $location = $delivery->palletPlan->pallets[0]->master_location_id;
        $domestic = MasterLocation::create(['code' => 'EX-PLAN-1']);
        $box = $this->receive();
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 0 && $m['reserved'] === 1);
        $this->get(route('export.stock.index', ['box' => $box->id]))->assertOk()->assertSee('Pallet 1')->assertSee('Konfirmasi box sudah ditempatkan');
        $this->placement($box)->assertRedirect();
        $this->assertSame('stored', $box->fresh()->state);
        $this->assertTrue(ExportMasterLocation::find($location)->is_occupied);
        $this->assertFalse($domestic->fresh()->is_occupied);
        $this->assertNull(ExportMasterLocation::find($location)->current_pallet_id);
        $this->assertDatabaseCount('export_stock_pallets', 1);
        $this->assertDatabaseCount('stock_locations', 0);
        $this->assertDatabaseCount('pallets', 0);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 1 && $m['remaining'] === 0);
    }

    public function test_placement_retry_and_stale_revision_do_not_duplicate_movements(): void
    {
        $this->plan(pallets: 1); $box = $this->receive();
        $data = ['revision' => $box->revision, 'request_token' => (string) Str::uuid()];
        $this->postJson(route('export.receiving.place', $box), $data)->assertRedirect();
        $date = $box->fresh()->first_received_at->toDateTimeString();
        $audits = AuditLog::count();
        $this->postJson(route('export.receiving.place', $box), $data)->assertRedirect();
        $this->assertSame($audits, AuditLog::count());
        $this->assertDatabaseCount('export_stock_movements', 1);
        $this->assertSame($date, $box->fresh()->first_received_at->toDateTimeString());
        $this->postJson(route('export.receiving.place', $box), array_replace($data, ['request_token' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson(route('export.receiving.place', $box), $data + ['destination' => 'location:999'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_token');
    }

    public function test_reserved_target_cannot_be_overridden_and_unavailable_plan_is_rejected(): void
    {
        $delivery = $this->plan(pallets: 1); $box = $this->receive();
        $other = ExportMasterLocation::create(['code' => 'OTHER']);
        $this->placement($box, 'location:'.$other->id)->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $delivery->palletPlan->update(['status' => 'draft']);
        $this->placement($box)->assertUnprocessable()->assertJsonValidationErrors('allocation');
        $this->assertDatabaseCount('export_stock_pallets', 0);
        $this->assertNull($box->fresh()->current_pallet_id);
        $this->assertNull($box->fulfillment->placed_at);
    }

    public function test_spare_can_use_empty_export_location_or_existing_mixed_pallet(): void
    {
        $location = ExportMasterLocation::create(['code' => 'SPARE']);
        $domestic = MasterLocation::create(['code' => 'DOM']);
        $box = $this->receive();
        $this->placement($box, 'location:'.$domestic->id)->assertUnprocessable();
        $this->placement($box, 'location:'.$location->id)->assertRedirect();
        $pallet = $box->fresh()->current_pallet_id;
        ExportPartSetting::create(['part_number' => 'EX-B', 'qty_box' => 25]);
        $other = $this->receive(override: ['part_number' => 'EX-B']);
        $this->placement($other, 'pallet:'.$pallet)->assertRedirect();
        $this->assertSame(2, ExportStockPallet::find($pallet)->boxes()->count());
        $this->assertDatabaseCount('export_fulfillment_boxes', 0);
        $this->get(route('export.stock.index', ['box' => $other->id]))->assertOk()->assertSee('Penempatan sudah dikonfirmasi');
    }

    public function test_spare_cannot_claim_empty_reserved_location_or_an_occupied_location(): void
    {
        $delivery = $this->plan(pallets: 1);
        ExportPartSetting::create(['part_number' => 'SPARE-PART', 'qty_box' => 5]);
        $box = $this->receive(override: ['part_number' => 'SPARE-PART']);
        $this->placement($box, 'location:'.$delivery->palletPlan->pallets[0]->master_location_id)->assertUnprocessable();
        $occupied = ExportMasterLocation::create(['code' => 'OCCUPIED', 'is_occupied' => true]);
        $this->placement($box, 'location:'.$occupied->id)->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_pallets', 0);
    }

    public function test_finalization_reserves_old_stock_and_new_box_cannot_jump_fifo(): void
    {
        $location = ExportMasterLocation::create(['code' => 'OLD']);
        $this->travelTo(now()->subDays(3));
        $old = $this->receive(); $this->placement($old, 'location:'.$location->id)->assertRedirect();
        $receivedAt = $old->first_received_at->toDateTimeString();
        $source = $old->fresh()->current_pallet_id;
        $this->travelBack();
        $delivery = $this->plan(pallets: 1);
        $this->assertNotNull($old->fresh()->fulfillment);
        $new = $this->receive();
        $this->assertNull($new->fulfillment);
        $this->assertSame($source, $old->fresh()->current_pallet_id);
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('transactions', fn ($p) => $p->first()->kind === 'legacy' && (int) $p->first()->transaction_id === $old->id);
        $this->placement($old)->assertRedirect();
        $this->assertSame($receivedAt, $old->fresh()->first_received_at->toDateTimeString());
        $this->assertNotNull(ExportStockPallet::find($source)->retired_at);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->get(route('export.fulfillment.show', $delivery))->assertOk()->assertViewHas('metrics', fn ($m) => $m['fulfilled'] === 1);
    }

    public function test_changed_master_capacity_does_not_force_new_boxes_into_old_snapshot(): void
    {
        $delivery = $this->plan(pallets: 1);
        $this->part->update(['qty_box' => 20]);
        $box = $this->receive();
        $this->assertSame(20, $box->pcs_quantity);
        $this->assertNull($box->fulfillment);
        $this->get(route('export.fulfillment.show', $delivery))->assertViewHas('metrics', fn ($m) => $m['reserved'] === 0 && $m['required'] === 1);
    }

    public function test_manual_spare_relocation_keeps_fifo_and_retires_empty_source(): void
    {
        $a = ExportMasterLocation::create(['code' => 'FROM']); $b = ExportMasterLocation::create(['code' => 'TO']);
        $box = $this->receive(); $this->placement($box, 'location:'.$a->id)->assertRedirect();
        $before = $box->fresh()->first_received_at->toDateTimeString();
        $this->placement($box, 'location:'.$b->id)->assertRedirect();
        $this->assertFalse($a->fresh()->is_occupied); $this->assertTrue($b->fresh()->is_occupied);
        $this->assertSame($before, $box->fresh()->first_received_at->toDateTimeString());
        $this->assertDatabaseCount('export_stock_movements', 2);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('export.locations.destroy', $a))->assertSessionHas('error');
        $this->assertDatabaseHas('master_locations', ['id' => $a->id]);
        $this->delete(route('export.part-settings.destroy', $this->part))->assertSessionHas('error');
    }

    public function test_failure_to_audit_confirmation_rolls_back_stock_occupancy_and_fulfillment(): void
    {
        $this->plan(pallets: 1); $box = $this->receive();
        $before = $box->revision;
        Event::listen('eloquent.creating: '.AuditLog::class, function () { throw new \RuntimeException('Audit unavailable'); });
        try {
            app(ExportStockReceivingService::class)->place($box->id, ['revision' => $before, 'request_token' => (string) Str::uuid()], $this->operator);
            $this->fail('Audit failure was ignored');
        } catch (\RuntimeException $e) { $this->assertSame('Audit unavailable', $e->getMessage()); }
        finally { Event::forget('eloquent.creating: '.AuditLog::class); }
        $this->assertSame($before, $box->fresh()->revision);
        $this->assertNull($box->fresh()->current_pallet_id);
        $this->assertNull($box->fulfillment->placed_at);
        $this->assertDatabaseCount('export_stock_pallets', 0);
        $this->assertDatabaseCount('export_stock_movements', 0);
        $this->assertFalse(ExportMasterLocation::first()->is_occupied);
    }

    public function test_receiving_permission_is_independent_and_revocable(): void
    {
        foreach (['warehouse_operator', 'admin_warehouse', 'sales', 'ppc', 'supervisi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('export.receiving.index'))->assertForbidden();
            $this->postJson(route('export.receiving.store'), [])->assertForbidden();
        }
        $setting = RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_stock_input']]);
        $this->actingAs($this->operator)->get(route('export.receiving.index'))->assertOk();
        $this->getJson(route('export.receiving.part', ['part_number' => 'EX-A']))->assertOk();
        $setting->update(['permissions' => []]);
        $this->get(route('export.receiving.index'))->assertForbidden();
    }
}
