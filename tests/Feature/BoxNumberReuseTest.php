<?php

namespace Tests\Feature;

use App\Models\{Box, DeliveryOrder, ExpiredBoxHandling, ExportFulfillmentBox, ExportMasterLocation, ExportPickingScan, ExportStockBox, MasterLocation, NotFullBoxRequest, PartSetting, StockInput, User};
use App\Services\{ExpiredBoxHandlingService, ExportDeliveryPlanService, ExportFulfillmentCompletionService, ExportPalletPlanningService, ExportStockAdminService, ExportStockHistoryService, ExportStockReceivingService, StockOpnameService};
use App\Support\BoxIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoxNumberReuseTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private PartSetting $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actor = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->actor);
        $this->part = PartSetting::create(['part_number' => 'REUSE-A', 'qty_box' => 10]);
    }

    private function local(string $number = '00123456', array $extra = []): Box
    {
        return Box::create($extra + ['box_number' => $number, 'part_number' => $this->part->part_number,
            'pcs_quantity' => 10, 'qty_box' => 10, 'qr_code' => $number.'|REUSE-A|10', 'user_id' => $this->actor->id]);
    }

    private function receive(string $number = '00123456', ?PartSetting $part = null, ?string $token = null): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(['box_number' => $number,
            'part_number' => ($part ?? $this->part)->part_number, 'receive_token' => $token ?? (string) Str::uuid()], $this->actor);
    }

    public function test_manual_local_delete_and_scan_create_a_new_receipt_without_rewriting_the_old_mapping(): void
    {
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '00123456'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'REUSE-A'])->assertOk();
        $location = MasterLocation::create(['code' => 'REUSE-LOCAL']);
        $this->postJson(route('stock-input.store'), ['pallet_id' => session('current_pallet_id'), 'location_id' => $location->id])->assertOk();
        $old = Box::sole();
        $input = StockInput::sole();
        $this->deleteJson(route('stock-view.box-delete', $old->id))->assertOk();
        $this->assertSoftDeleted($old);

        PartSetting::create(['part_number' => 'REUSE-B', 'qty_box' => 25]);
        $this->travel(2)->days();
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '00123456'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'REUSE-B'])->assertOk();
        $this->postJson(route('stock-input.store'), ['pallet_id' => session('current_pallet_id'), 'location_id' => $location->id])->assertOk();
        $new = Box::sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(2, (int) $new->identity_cycle);
        $this->assertSame('REUSE-B', $new->part_number);
        $this->assertSame(25, (int) $new->pcs_quantity);
        $this->assertTrue($new->created_at->gt($old->created_at));
        $this->assertSame([$old->id], $input->boxes()->withTrashed()->pluck('boxes.id')->all());
        $this->assertDatabaseHas('boxes', ['id' => $old->id, 'part_number' => 'REUSE-A', 'pcs_quantity' => 10]);
        $this->getJson(route('stock-view.box-history', $new->id))->assertOk()->assertJsonPath('identity_cycle', 2)
            ->assertJsonCount(2, 'uses')->assertJsonPath('uses.0.id', $new->id)->assertJsonPath('uses.1.id', $old->id);
    }

    public function test_new_receipt_is_blocked_by_any_active_box_even_when_expired(): void
    {
        $old = $this->local(extra: ['created_at' => now()->subMonths(15), 'expired_status' => 'expired']);
        $this->assertTrue(BoxIdentity::unavailable($old->box_number));
        $this->postJson(route('export.receiving.store'), ['box_number' => $old->box_number,
            'part_number' => 'REUSE-A', 'receive_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(1, BoxIdentity::lastCycle($old->box_number));
        $this->assertDatabaseCount('export_stock_boxes', 0);
    }

    public function test_local_shipment_releases_the_number_but_redo_cannot_restore_an_older_use(): void
    {
        $old = $this->local();
        $old->update(['is_withdrawn' => true, 'withdrawn_at' => now()]);
        $this->assertFalse(BoxIdentity::unavailable($old->box_number));
        // Redo remains possible until the number is actually reused.
        $old->update(['is_withdrawn' => false]);
        $old->update(['is_withdrawn' => true]);
        $new = $this->receive();
        $this->assertSame(2, (int) $new->identity_cycle);
        $new->delete();
        try {
            $old->update(['is_withdrawn' => false]);
            $this->fail('A historical use must not be reactivated after reuse.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('penerimaan lain', $error->getMessage());
        }
        $this->assertTrue($old->fresh()->is_withdrawn);
    }

    public function test_deleted_box_cannot_be_restored_over_a_new_active_use(): void
    {
        $old = $this->local();
        $old->delete();
        $new = $this->local(extra: ['pcs_quantity' => 30]);
        try {
            $old->restore();
            $this->fail('Restore must retain global uniqueness.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('penerimaan lain', $error->getMessage());
        }
        $this->assertSoftDeleted($old);
        $this->assertSame($new->id, Box::sole()->id);
    }

    public function test_manual_export_deletion_allows_new_part_and_history_selection_keeps_uses_separate(): void
    {
        $old = $this->receive();
        app(ExportStockAdminService::class)->deleteBox($old->id, ['revision' => $old->revision, 'reason' => 'Salah input'], $this->actor);
        $part = PartSetting::create(['part_number' => 'REUSE-B', 'qty_box' => 25]);
        $new = $this->receive(part: $part);
        $this->assertSame(2, (int) $new->identity_cycle);
        $this->assertSame(25, (int) $new->pcs_quantity);
        $url = route('export.stock.box-history', ['box_number' => $old->box_number]);
        $this->get($url)->assertOk()->assertViewHas('box', fn ($box) => $box->id === $new->id)
            ->assertSee('Pilih riwayat penggunaan ID Box')->assertSee('Penggunaan 2');
        $this->get($url.'&box_id='.$old->id)->assertOk()->assertViewHas('box', fn ($box) => $box->id === $old->id)
            ->assertSee('Penggunaan 1')->assertSee('Box ekspor dihapus dari stok aktif.');
        $this->assertFalse(app(ExportStockHistoryService::class)->events($new)->contains('description', 'Box ekspor dihapus dari stok aktif.'));
    }

    public function test_a_finished_export_number_can_be_allocated_again_without_changing_the_previous_delivery(): void
    {
        $old = $this->receive();
        $service = app(ExportStockReceivingService::class);
        $makePlan = function (string $code) {
            $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => $code,
                'delivery_deadline' => now()->addDay()->toDateString(), 'source_type' => 'manual',
                'items' => [['part_number' => 'REUSE-A', 'pcs_quantity' => 10]]], $this->actor);
            $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
                'action' => 'finalize', 'pallets' => [['master_location_id' => ExportMasterLocation::create(['code' => $code])->id,
                    'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]]], $this->actor);
            return [$delivery, $plan->pallets()->sole()];
        };
        [$delivery, $pallet] = $makePlan('REUSE-DELIVERY-1');
        $notice = $old->fresh()->fulfillment->allocationNotice;
        app(\App\Services\ExportStockAllocationNoticeService::class)->acknowledge($delivery->id, $notice->id, 1, $this->actor);
        $service->place($old->id, ['revision' => $old->fresh()->revision, 'request_token' => (string) Str::uuid()], $this->actor);
        $completion = app(ExportFulfillmentCompletionService::class);
        $completion->ready($delivery->id, $pallet->id, ['revision' => 1, 'fulfillment_revision' => $pallet->fresh()->fulfillment_revision,
            'request_token' => (string) Str::uuid(), 'location_id' => $pallet->master_location_id], $this->actor);
        $completion->complete($delivery->id, ['revision' => 1, 'request_token' => (string) Str::uuid(), 'shipped_confirmed' => 1], $this->actor);
        $oldCommitment = ExportFulfillmentBox::where('export_stock_box_id', $old->id)->sole();
        $new = $this->receive();
        [$other] = $makePlan('REUSE-DELIVERY-2');
        $this->assertSame(2, (int) $new->identity_cycle);
        $this->assertSame(2, ExportFulfillmentBox::where('box_number', $old->box_number)->count());
        $this->assertSame($old->id, $oldCommitment->fresh()->export_stock_box_id);
        $this->assertSame('shipped', $old->fresh()->state);
        $this->assertNotSame($oldCommitment->id, $new->fulfillment->id);
        $this->assertSame($other->id, $new->fulfillment->plannedItem->pallet->palletPlan->export_delivery_plan_id);
        $report = app(\App\Services\ShipmentReportService::class)->lines('export')->where('order_id', $delivery->id)->sole();
        $this->assertSame(10, (int) $report->pcs_quantity);
        $this->assertSame(1, (int) $report->box_quantity);
        ExportPickingScan::create(['request_token' => (string) Str::uuid(), 'export_planned_pallet_id' => $pallet->id,
            'export_fulfillment_box_id' => $oldCommitment->id, 'created_by' => $this->actor->id, 'box_number' => $old->box_number, 'outcome' => 'verified']);
        $this->assertSame(1, app(ExportStockHistoryService::class)->events($old)->where('label', 'Picking terverifikasi')->count());
        $this->assertSame(0, app(ExportStockHistoryService::class)->events($new)->where('label', 'Picking terverifikasi')->count());
    }

    public function test_reusing_a_number_does_not_carry_over_a_previous_opname_scan(): void
    {
        $old = $this->local();
        $service = app(StockOpnameService::class);
        $session = $service->start('domestic', (string) Str::uuid(), $this->actor);
        $scan = $service->scan($session->id, $old->box_number, (string) Str::uuid(), $this->actor);
        $this->assertSame('ok', $scan->outcome);
        $old->delete();
        $new = $this->local(extra: ['part_number' => 'REUSE-B', 'pcs_quantity' => 25]);
        $service->refresh($session->id);
        $item = $session->items()->sole();
        $this->assertNull($item->first_scanned_at);
        $this->assertSame($old->id, $item->initial_snapshot['id']);
        $this->assertSame($new->id, $item->current_snapshot['id']);
        $this->assertSame('ok', $service->scan($session->id, $new->box_number, (string) Str::uuid(), $this->actor)->outcome);
        $this->assertSame($old->id, $scan->fresh()->snapshot['id']);
    }

    public function test_an_old_unsaved_scan_cannot_attach_a_new_use_with_the_same_number(): void
    {
        $old = $this->local();
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => $old->box_number])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => 'REUSE-A'])->assertOk();
        $old->delete();
        $new = $this->local();
        $location = MasterLocation::create(['code' => 'REUSE-STALE']);
        $this->postJson(route('stock-input.store'), ['pallet_id' => session('current_pallet_id'), 'location_id' => $location->id])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertCount(0, $new->pallets);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->assertDatabaseCount('stock_inputs', 0);
        $this->withSession(['scanned_boxes' => [['box_number' => $new->box_number,
            'part_number' => 'REUSE-A', 'pcs_quantity' => 10]]])
            ->postJson(route('stock-input.store'), ['pallet_id' => session('current_pallet_id'), 'location_id' => $location->id])
            ->assertUnprocessable();
        $this->withSession(['pending_box' => ['box_number' => $new->box_number]])
            ->postJson(route('stock-input.scan-part'), ['part_number' => 'REUSE-A'])->assertUnprocessable();
        $this->assertNull(session('pending_box'));
    }

    public function test_failed_receipt_does_not_consume_a_cycle_or_change_the_old_receipt_token(): void
    {
        $old = $this->receive();
        $token = $old->receive_token;
        $old->delete();
        $other = $this->receive('00123457');
        try {
            ExportStockBox::create(['box_number' => $old->box_number, 'part_setting_id' => $this->part->id,
                'part_number' => 'REUSE-A', 'pcs_quantity' => 10, 'receive_token' => $other->receive_token,
                'received_by' => $this->actor->id, 'first_received_at' => now()]);
            $this->fail('Duplicate receipt token must roll back the claim.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, BoxIdentity::lastCycle($old->box_number));
        }
        $new = $this->receive();
        $this->assertSame(2, (int) $new->identity_cycle);
        $this->assertSame($token, $old->fresh()->receive_token);
    }

    public function test_the_same_label_can_have_multiple_qa_rejections_with_independent_snapshots(): void
    {
        $workflow = app(ExpiredBoxHandlingService::class);
        $old = $this->local(extra: ['created_at' => now()->subMonths(14)]);
        $first = $workflow->start($old->id, $this->actor->id);
        $this->assertTrue(BoxIdentity::unavailable($old->box_number));
        $workflow->decideQa($first->id, 'rejected', 'Kerusakan pertama', $this->actor->id);
        $this->assertFalse(BoxIdentity::unavailable($old->box_number));
        $new = $this->local(extra: ['created_at' => now()->subMonths(13), 'part_number' => 'REUSE-B', 'pcs_quantity' => 25]);
        $second = $workflow->start($new->id, $this->actor->id);
        $workflow->decideQa($second->id, 'rejected', 'Kerusakan kedua', $this->actor->id);
        $this->assertSame(2, ExpiredBoxHandling::where('source_box_number', $old->box_number)->count());
        $this->assertSame('REUSE-A', $first->fresh()->source_snapshot['part_number']);
        $this->assertSame(10, $first->fresh()->source_snapshot['pcs_quantity']);
        $this->assertSame('REUSE-B', $second->fresh()->source_snapshot['part_number']);
        $this->assertSame(25, $second->fresh()->source_snapshot['pcs_quantity']);
        $this->assertSame(3, (int) $this->receive()->identity_cycle);
    }

    public function test_historical_not_full_requests_do_not_block_reuse_but_pending_requests_do(): void
    {
        $order = DeliveryOrder::create(['sales_user_id' => $this->actor->id, 'customer_name' => 'REUSE',
            'delivery_date' => now()->addDay(), 'status' => 'approved']);
        \App\Models\DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => 'REUSE-A', 'quantity' => 5, 'fulfilled_quantity' => 0]);
        $location = MasterLocation::create(['code' => 'REUSE-NF']);
        $data = ['box_number' => '00123456', 'part_number' => 'REUSE-A', 'pcs_quantity' => 5,
            'fixed_qty' => 10, 'delivery_order_id' => $order->id, 'reason' => 'Koreksi penerimaan',
            'request_type' => 'supplement', 'target_type' => 'location', 'target_location_id' => $location->id];
        $previous = NotFullBoxRequest::create($data + ['requested_by' => $this->actor->id, 'status' => 'rejected']);
        $this->post(route('box-not-full.store'), $data)->assertSessionHas('success');
        $this->assertSame(2, NotFullBoxRequest::count());
        $this->assertTrue(BoxIdentity::unavailable('00123456'));
        $this->post(route('box-not-full.store'), $data)->assertSessionHas('error');
        $this->assertSame(2, NotFullBoxRequest::count());
        $pending = NotFullBoxRequest::where('status', 'pending')->sole();
        $this->post(route('box-not-full.approve', $pending))->assertSessionHas('success');
        $created = Box::sole();
        $created->update(['assigned_delivery_order_id' => null]);
        $created->delete();
        $this->assertFalse(BoxIdentity::unavailable('00123456'));
        $this->assertSame(2, (int) $this->receive()->identity_cycle);
        $this->assertSame('rejected', $previous->fresh()->status);
        $this->assertSame($created->id, $pending->fresh()->box_id);
    }

    public function test_a_stale_export_draft_must_be_scanned_again_after_another_use(): void
    {
        $service = app(\App\Services\ExportReceivingBatchService::class);
        $batch = $service->start($this->actor);
        $service->scan($batch->id, ['box_number' => '00123456', 'part_number' => 'REUSE-A',
            'scan_token' => (string) Str::uuid(), 'revision' => $batch->revision], $this->actor);
        $this->assertNotNull($batch->items()->sole()->expected_identity_cycle);
        $this->assertSame(0, (int) $batch->items()->sole()->expected_identity_cycle);
        $other = $this->local();
        $other->delete();
        $this->assertSame(1, BoxIdentity::lastCycle('00123456'));
        try {
            $service->process($batch->id, $batch->fresh()->revision, $this->actor);
            $this->fail('Old draft must not silently create another use.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('box_number', $error->errors());
        }
        $this->assertSame('draft', $batch->fresh()->status);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $this->assertSame(1, BoxIdentity::lastCycle('00123456'));
    }

    public function test_retrying_an_archived_receipt_token_returns_the_original_receipt(): void
    {
        $old = $this->receive();
        $old->delete();
        $new = $this->receive();
        $replay = $this->receive(token: $old->receive_token);
        $this->assertSame($old->id, $replay->id);
        $this->assertTrue($replay->trashed());
        $this->assertSame($new->id, ExportStockBox::sole()->id);
        $this->assertSame(2, BoxIdentity::lastCycle($old->box_number));
    }

    public function test_an_opname_opened_before_the_migration_retains_its_unchanged_status_and_scan(): void
    {
        $box = $this->local();
        $service = app(StockOpnameService::class);
        $session = $service->start('domestic', (string) Str::uuid(), $this->actor);
        $service->scan($session->id, $box->box_number, (string) Str::uuid(), $this->actor);
        $item = $session->items()->sole();
        $oldSnapshot = $item->initial_snapshot;
        unset($oldSnapshot['identity_cycle']);
        $item->update(['initial_snapshot' => $oldSnapshot, 'current_snapshot' => $oldSnapshot]);
        $service->refresh($session->id);
        $this->assertSame('unchanged', $item->fresh()->change_kind);
        $this->assertNotNull($item->fresh()->first_scanned_at);
        $this->assertSame('duplicate', $service->scan($session->id, $box->box_number, (string) Str::uuid(), $this->actor)->outcome);
    }
}
