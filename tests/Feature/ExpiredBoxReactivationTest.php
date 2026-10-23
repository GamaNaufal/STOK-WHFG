<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\ExpiredBoxReport;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredBoxReactivationTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $ageMonths = 13): array
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0));
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        PartSetting::create(['part_number' => 'P-RENEW', 'qty_box' => 20]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-RENEW']);
        $location = MasterLocation::create(['code' => 'RENEW-01', 'is_occupied' => false]);
        StockLocation::create([
            'pallet_id' => $pallet->id, 'master_location_id' => $location->id,
            'warehouse_location' => $location->code, 'stored_at' => now()->subMonthsNoOverflow($ageMonths),
        ]);
        $box = Box::create([
            'box_number' => '99110001', 'part_number' => 'P-RENEW', 'pcs_quantity' => 20,
            'qty_box' => 1, 'qr_code' => '99110001', 'user_id' => $operator->id,
            'expired_status' => 'active', 'created_at' => now()->subMonthsNoOverflow($ageMonths),
        ]);
        $pallet->boxes()->attach($box->id);
        PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => 'P-RENEW', 'box_quantity' => 1, 'pcs_quantity' => 20]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $operator->id, 'customer_name' => 'Renewal',
            'delivery_date' => now()->addDay(), 'status' => 'approved',
        ]);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => 'P-RENEW', 'quantity' => 20, 'fulfilled_quantity' => 0]);

        return [$operator, $supervisor, $box, $pallet, $location, $order];
    }

    public function test_warning_remains_available_to_fifo_without_handle(): void
    {
        [$operator, , $box, , , $order] = $this->inventory(10);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertSame('warning', $box->fresh()->expired_status);
        $this->assertTrue(Box::active()->whereKey($box->id)->exists());
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertOk();
        $this->assertDatabaseHas('delivery_pick_items', ['box_id' => $box->id]);
    }

    public function test_warning_is_visible_but_handle_is_rejected_without_changing_inventory(): void
    {
        [, $supervisor, $box, $pallet, $location] = $this->inventory(10);
        $this->actingAs($supervisor)->get(route('expired-box.index'))->assertOk()
            ->assertViewHas('warningBoxes', fn ($boxes) => $boxes->contains('id', $box->id))
            ->assertSee('Box belum expired, tidak perlu Handle.')
            ->assertDontSee('class="js-handle-btn"', false);
        $this->post(route('expired-box.handle', $box->id), ['location_id' => $location->id])
            ->assertSessionHas('error');
        $this->assertSame('warning', $box->fresh()->expired_status);
        $this->assertNull($box->fresh()->reactivated_until);
        $this->assertNull($box->fresh()->handled_at);
        $this->assertDatabaseMissing('expired_box_reports', ['box_id' => $box->id, 'status' => 'handled']);
        $this->assertDatabaseMissing('audit_logs', ['model' => 'Box', 'model_id' => $box->id, 'action' => 'handled']);
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $pallet->id, 'master_location_id' => $location->id]);
        $this->assertTrue(Box::active()->whereKey($box->id)->exists());
    }

    public function test_expired_stock_remains_visible_in_all_stock_views_and_details_after_location_is_released(): void
    {
        [$operator, , $box, $pallet, , $order] = $this->inventory();
        $box->update(['is_not_full' => true]);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $pallet->id]);
        foreach (['part', 'box_id', 'pallet', 'not_full'] as $mode) {
            $this->actingAs($operator)->get(route('stock-view.index', ['view_mode' => $mode]))
                ->assertOk()->assertSee('Expired')->assertViewHas('summaryTotalBox', 1)
                ->assertViewHas('summaryExpiredBox', 1)->assertViewHas('summaryExpiredPcs', 20);
        }
        $this->get(route('stock-view.index', ['search' => $box->box_number]))->assertOk()
            ->assertViewHas('viewMode', 'box_id')
            ->assertViewHas('groupedByBoxId', fn ($rows) => $rows->first()['is_expired'] === true);
        $this->getJson('/api/stock/part-detail/P-RENEW')->assertOk()->assertJsonPath('pallets.0.is_expired', true);
        $this->getJson('/api/stock/pallet-detail/'.$pallet->id)->assertOk()->assertJsonPath('items.0.status_label', 'Expired');
        $this->getJson('/api/stock/by-part')->assertOk()->assertExactJson([]);
        $this->postJson(route('delivery.pick.start', $order->id))->assertStatus(422);
    }

    public function test_stock_view_deduplicates_expired_boxes_and_labels_elapsed_renewals_before_sync(): void
    {
        [$operator, , $box] = $this->inventory(10);
        $box->update(['reactivated_until' => now(), 'expired_status' => 'active']);
        $otherPallet = Pallet::create(['pallet_number' => 'SHARED-EXPIRED']);
        $otherPallet->boxes()->attach($box->id);
        $this->actingAs($operator)->get(route('stock-view.index', ['view_mode' => 'box_id']))
            ->assertOk()->assertViewHas('summaryTotalBox', 1)->assertViewHas('summaryExpiredBox', 1);
        $box->update(['is_withdrawn' => true]);
        $this->get(route('stock-view.index'))->assertViewHas('summaryTotalBox', 0);
        $box->update(['is_withdrawn' => false]);
        $box->delete();
        $this->get(route('stock-view.index'))->assertViewHas('summaryTotalBox', 0);
    }

    public function test_expired_orphan_and_export_breakdowns_remain_visible(): void
    {
        [$operator, , $box, $pallet] = $this->inventory();
        app(ExpiredBoxService::class)->syncStatuses();
        $this->actingAs($operator);
        $parts = $this->get(route('stock-view.index'))->viewData('groupedByPart');
        $partRows = (new \App\Exports\StockViewByPartExport($parts))->collection();
        $this->assertSame(1, $partRows->first()['Box Expired']);
        $this->assertSame(20, $partRows->first()['PCS Expired']);
        $pallets = $this->get(route('stock-view.index', ['view_mode' => 'pallet']))->viewData('groupedByPallet');
        $palletRows = (new \App\Exports\StockViewByPalletExport($pallets))->collection();
        $this->assertSame(1, $palletRows->first()['Box Expired']);
        $pallet->boxes()->detach($box->id);
        $response = $this->get(route('stock-view.index', ['view_mode' => 'box_id', 'search' => $box->box_number]));
        $response->assertOk()->assertViewHas('summaryExpiredBox', 1);
        $boxes = $response->viewData('groupedByBoxId');
        $this->assertNull($boxes->first()['pallet_id']);
        $boxRows = (new \App\Exports\StockViewByBoxIdExport($boxes))->collection();
        $this->assertSame('Expired', $boxRows->first()['Status']);
    }

    private function workflow(): \App\Services\ExpiredBoxHandlingService
    {
        return app(\App\Services\ExpiredBoxHandlingService::class);
    }

    private function readyForPacking(Box $box, User $supervisor): \App\Models\ExpiredBoxHandling
    {
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        $this->workflow()->decideQa($handling->id, 'passed', 'QA lolos, kembali warehouse', $supervisor->id);
        $this->workflow()->sendToPacking($handling->id, $supervisor->id);

        return $handling->fresh();
    }

    private function receipt(\App\Models\ExpiredBoxHandling $handling, array $override = []): array
    {
        return array_merge([
            'box_number' => '99110002', 'part_number' => $handling->part_number,
            'pcs_quantity' => $handling->pcs_quantity,
            'label_issued_at' => now()->toDateTimeString(),
            'location_id' => MasterLocation::first()->id, 'confirmed' => 1,
        ], $override);
    }

    private function returnFromPacking(Box $box, User $supervisor): Box
    {
        $handling = $this->readyForPacking($box, $supervisor);
        $this->workflow()->receive($handling->id, $this->receipt($handling), $supervisor->id);

        return Box::findOrFail($handling->fresh()->replacement_box_id);
    }

    public function test_handle_sends_to_qa_without_reactivation_and_is_idempotent(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        $this->actingAs($supervisor)->post(route('expired-box.handle', $box->id))->assertSessionHas('success');
        $handling = \App\Models\ExpiredBoxHandling::sole();
        $this->assertSame('at_qa', $handling->status);
        $this->assertSame('handled', $box->fresh()->expired_status);
        $this->assertNull($box->fresh()->reactivated_until);
        $this->assertCount(1, $handling->source_snapshot['pallet_boxes']);
        $this->assertDatabaseMissing('pallet_boxes', ['box_id' => $box->id]);
        $this->post(route('expired-box.handle', $box->id))->assertRedirect(route('expired-box.show', $handling));
        $this->assertDatabaseCount('expired_box_handlings', 1);
        $this->assertSame(1, AuditLog::where('action', 'sent_qa')->count());
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertSame('handled', $box->fresh()->expired_status);
        $this->assertFalse(Box::active()->whereKey($box->id)->exists());
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertStatus(422);
        $this->get(route('stock-view.index'))->assertViewHas('summaryTotalBox', 0);
    }

    public function test_complete_workflow_preserves_age_quantity_and_one_month_deadline(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        $oldDate = $box->created_at->toDateTimeString();
        $replacement = $this->returnFromPacking($box, $supervisor);
        $this->assertSame($oldDate, $replacement->created_at->toDateTimeString());
        $this->assertSame('2026-02-28 10:00:00', $replacement->reactivated_until->toDateTimeString());
        $this->assertSame(20, $replacement->pcs_quantity);
        $this->assertSame('handled', $box->fresh()->expired_status);
        $this->assertDatabaseCount('stock_inputs', 0);
        $this->assertDatabaseCount('stock_withdrawals', 0);
        $this->assertSame(1, Box::active()->count());
        $handling = \App\Models\ExpiredBoxHandling::sole();
        $this->workflow()->receive($handling->id, $this->receipt($handling), $supervisor->id);
        $this->assertDatabaseCount('boxes', 2);
        $this->assertSame(1, AuditLog::where('action', 'packing_received')->count());
        $this->actingAs($supervisor)->get(route('expired-box.show', $handling))->assertOk()->assertSee('99110002')->assertSee('28 Feb 2026');
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertOk();
        $this->assertDatabaseHas('delivery_pick_items', ['box_id' => $replacement->id]);
        $this->assertDatabaseMissing('delivery_pick_items', ['box_id' => $box->id]);
    }

    public function test_deadline_blocks_fifo_without_scheduler_and_next_cycle_uses_new_identity(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $replacement = $this->returnFromPacking($box, $supervisor);
        $this->travelTo($replacement->reactivated_until->copy()->subSecond());
        $this->assertTrue(Box::active()->whereKey($replacement->id)->exists());
        $this->travel(1)->seconds();
        $this->assertFalse(Box::active()->whereKey($replacement->id)->exists());
        $next = $this->workflow()->start($replacement->id, $supervisor->id);
        $this->assertSame($replacement->box_number, $next->source_box_number);
        $this->assertDatabaseCount('expired_box_handlings', 2);
    }

    public function test_rejected_box_is_permanently_deleted_but_snapshot_and_identity_survive(): void
    {
        [, $supervisor, $box, $pallet] = $this->inventory();
        $input = \App\Models\StockInput::create(['pallet_id' => $pallet->id, 'user_id' => $supervisor->id, 'warehouse_location' => 'RENEW-01', 'pcs_quantity' => 20, 'box_quantity' => 1, 'stored_at' => $box->created_at]);
        $input->boxes()->attach($box->id);
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        $this->actingAs($supervisor)->post(route('expired-box.qa', $handling), ['decision' => 'rejected', 'notes' => 'Tidak lolos QA', 'confirmed' => 1])->assertSessionHas('success');
        $this->assertNull(Box::withTrashed()->find($box->id));
        $this->assertSame('rejected', $handling->fresh()->status);
        $this->assertSame($input->id, $handling->fresh()->history_snapshot['stock_input_boxes'][0]['stock_input_id']);
        $this->assertDatabaseHas('stock_inputs', ['id' => $input->id]);
        $this->assertDatabaseHas('box_identities', ['box_number' => $box->box_number]);
        $this->get(route('expired-box.trace', $handling))->assertOk()->assertJsonPath('source_box_number', $box->box_number);
        $this->post(route('expired-box.qa', $handling), ['decision' => 'rejected', 'notes' => 'Tidak lolos QA', 'confirmed' => 1])->assertSessionHas('success');
        $this->assertSame(1, AuditLog::where('action', 'qa_rejected')->count());
        $this->expectException(\RuntimeException::class);
        Box::create($box->only(['box_number', 'part_number', 'pcs_quantity', 'qty_box', 'qr_code', 'user_id']));
    }

    public function test_qa_requires_confirmation_and_rejection_reason_and_cannot_skip_stages(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        $this->actingAs($supervisor)->post(route('expired-box.qa', $handling), ['decision' => 'rejected'])->assertSessionHasErrors(['notes', 'confirmed']);
        $this->post(route('expired-box.packing', $handling), ['confirmed' => 1])->assertSessionHas('error');
        $this->post(route('expired-box.receive', $handling), $this->receipt($handling))->assertSessionHas('error');
        $this->get(route('expired-box.print', $handling))->assertStatus(409);
        $this->assertSame('at_qa', $handling->fresh()->status);
        $this->assertNotNull($box->fresh());
        $this->post(route('expired-box.qa', $handling), ['decision' => 'passed', 'confirmed' => 1])->assertSessionHas('success');
        $this->get(route('expired-box.print', $handling))->assertOk()->assertSee($handling->reference)->assertSee($box->box_number);
        $this->post(route('expired-box.qa', $handling), ['decision' => 'rejected', 'notes' => 'ubah', 'confirmed' => 1])->assertSessionHas('error');
    }

    public function test_receipt_rejects_mismatches_reused_ids_and_invalid_dates(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $handling = $this->readyForPacking($box, $supervisor);
        $this->actingAs($supervisor);
        foreach ([['pcs_quantity' => 19], ['part_number' => 'OTHER']] as $override) {
            $this->post(route('expired-box.receive', $handling), $this->receipt($handling, $override))->assertSessionHas('error');
        }
        foreach ([['box_number' => $box->box_number], ['box_number' => 'abc'], ['label_issued_at' => now()->subDay()->toDateTimeString()], ['label_issued_at' => now()->addDay()->toDateTimeString()]] as $override) {
            $this->post(route('expired-box.receive', $handling), $this->receipt($handling, $override))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('boxes', 1);
        $this->assertSame('at_packing', $handling->fresh()->status);
    }

    public function test_location_conflict_rolls_back_replacement_identity_and_receipt(): void
    {
        [, $supervisor, $box, , $location] = $this->inventory();
        $handling = $this->readyForPacking($box, $supervisor);
        $other = Pallet::create(['pallet_number' => 'OTHER']);
        $location->update(['is_occupied' => true, 'current_pallet_id' => $other->id]);
        $this->actingAs($supervisor)->post(route('expired-box.receive', $handling), $this->receipt($handling))->assertSessionHas('error');
        $this->assertSame($other->id, $location->fresh()->current_pallet_id);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '99110002']);
        $this->assertDatabaseCount('boxes', 1);
        $this->assertSame('at_packing', $handling->fresh()->status);
        $free = MasterLocation::create(['code' => 'RENEW-02']);
        $this->post(route('expired-box.receive', $handling), $this->receipt($handling, ['location_id' => $free->id]))->assertSessionHas('success');
    }

    public function test_late_receipt_records_expired_box_without_renewing_deadline(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $handling = $this->readyForPacking($box, $supervisor);
        $issued = now()->toDateTimeString();
        $this->travel(35)->days();
        $this->workflow()->receive($handling->id, $this->receipt($handling, ['label_issued_at' => $issued]), $supervisor->id);
        $replacement = Box::findOrFail($handling->fresh()->replacement_box_id);
        $this->assertSame('expired', $replacement->expired_status);
        $this->assertSame('2026-02-28 10:00:00', $replacement->reactivated_until->toDateTimeString());
        $this->assertFalse(Box::active()->whereKey($replacement->id)->exists());
        $this->assertDatabaseHas('expired_box_reports', ['box_id' => $replacement->id, 'status' => 'expired']);
    }

    public function test_assigned_and_pick_locked_boxes_cannot_start_qa(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        $box->update(['assigned_delivery_order_id' => $order->id]);
        $this->actingAs($supervisor)->post(route('expired-box.handle', $box->id))->assertSessionHas('error');
        $box->update(['assigned_delivery_order_id' => null]);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id, 'status' => 'pending']);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $box->id, 'part_number' => $box->part_number, 'pcs_quantity' => 20, 'status' => 'pending']);
        $this->post(route('expired-box.handle', $box->id))->assertSessionHas('error');
        $this->assertDatabaseCount('expired_box_handlings', 0);
    }

    public function test_feature_permissions_apply_to_all_workflow_routes(): void
    {
        [$operator, $supervisor, $box] = $this->inventory();
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        $this->actingAs($operator);
        foreach (['show', 'print', 'trace'] as $action) {
            $this->get(route('expired-box.'.$action, $handling))->assertForbidden();
        }
        foreach (['qa', 'packing', 'receive'] as $action) {
            $this->post(route('expired-box.'.$action, $handling))->assertForbidden();
        }
        $this->post(route('expired-box.handle', $box->id))->assertForbidden();
        $this->actingAs($supervisor)->get(route('expired-box.show', $handling))->assertOk();
        $this->deleteJson(route('stock-view.box-delete', $box->id))->assertStatus(422);
        $this->assertNotNull($box->fresh());
    }

    public function test_failed_audit_rolls_back_permanent_qa_rejection(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->workflow()->decideQa($handling->id, 'rejected', 'QA gagal', $supervisor->id);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertNotNull($box->fresh());
        $this->assertSame('at_qa', $handling->fresh()->status);
        $this->assertNull($handling->fresh()->history_snapshot);
    }

    public function test_existing_destination_pallet_is_preserved_and_delivery_lock_blocks_receipt(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        $handling = $this->readyForPacking($box, $supervisor);
        $pallet = Pallet::create(['pallet_number' => 'DESTINATION']);
        $location = MasterLocation::create(['code' => 'DEST', 'is_occupied' => true, 'current_pallet_id' => $pallet->id]);
        StockLocation::create(['pallet_id' => $pallet->id, 'master_location_id' => $location->id, 'warehouse_location' => $location->code, 'stored_at' => now()]);
        $other = Box::create(['box_number' => '99110003', 'part_number' => 'P-RENEW', 'pcs_quantity' => 20, 'qr_code' => '99110003', 'user_id' => $operator->id]);
        $pallet->boxes()->attach($other->id);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id, 'status' => 'scanning']);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $other->id, 'part_number' => 'P-RENEW', 'pcs_quantity' => 20, 'status' => 'pending']);
        $data = $this->receipt($handling, ['pallet_id' => $pallet->id]);
        $this->actingAs($supervisor)->post(route('expired-box.receive', $handling), $data)->assertSessionHas('error');
        $this->assertDatabaseMissing('boxes', ['box_number' => '99110002']);
        $session->update(['status' => 'cancelled']);
        $this->post(route('expired-box.receive', $handling), $data)->assertSessionHas('success');
        $this->assertSame($pallet->id, $location->fresh()->current_pallet_id);
        $this->assertDatabaseHas('pallet_items', ['pallet_id' => $pallet->id, 'box_quantity' => 2, 'pcs_quantity' => 40]);
    }

    public function test_orphan_expired_box_can_be_sent_to_qa(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $box->pallets()->detach();
        $handling = $this->workflow()->start($box->id, $supervisor->id);
        $this->assertSame('at_qa', $handling->status);
        $this->assertSame($box->created_at->toDateTimeString(), $handling->original_stored_at->toDateTimeString());
    }

    public function test_registry_reservation_rolls_back_when_box_insert_fails(): void
    {
        [, $supervisor] = $this->inventory();
        try {
            Box::create(['box_number' => '99110009', 'part_number' => 'P-RENEW', 'pcs_quantity' => 20, 'user_id' => $supervisor->id]);
            $this->fail('QR is required by the schema.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseMissing('box_identities', ['box_number' => '99110009']);
        }
    }

    public function test_cutover_preserves_old_deadline_but_requires_qa_before_reuse(): void
    {
        [, , $box] = $this->inventory();
        $box->update(['reactivated_until' => now()->addMonthNoOverflow()]);
        $previousDeadline = $box->reactivated_until->toDateTimeString();
        $migration = require database_path('migrations/2026_10_23_000001_create_expired_box_handlings.php');
        $migration->down();
        $migration->up();
        $this->assertSame($previousDeadline, $box->fresh()->legacy_reactivated_until);
        $this->assertFalse(Box::active()->whereKey($box->id)->exists());
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertSame('expired', $box->fresh()->expired_status);
        $this->assertDatabaseCount('expired_box_handlings', 0);
        $this->assertDatabaseHas('box_identities', ['box_number' => $box->box_number]);
    }

    public function test_expired_renewal_is_rejected_without_verification_lock_but_blocks_final_scan(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        $box = $this->returnFromPacking($box, $supervisor);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id, 'status' => 'scanning', 'started_at' => now()]);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $box->id, 'part_number' => 'P-RENEW', 'pcs_quantity' => 20, 'status' => 'scanned']);
        $this->travelTo($box->fresh()->reactivated_until);
        $this->actingAs($operator)->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $box->box_number])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'session_status' => null])
            ->assertJsonPath('message', 'Masa aktif hasil packing box ini sudah habis. Box tidak dapat dipakai untuk delivery dan scan tidak dihitung. Gunakan box lain di picklist; sesi tetap aktif. Laporkan box ini ke Supervisi/Admin.');
        $this->assertSame('scanning', $session->fresh()->status);
        $this->assertNull($session->fresh()->verification_box_ids);
        $this->assertDatabaseMissing('delivery_issues', ['pick_session_id' => $session->id]);

        $this->postJson(route('delivery.pick.scan.submit', $session->id), ['box_number' => $box->box_number])
            ->assertStatus(422)
            ->assertJson(['session_status' => 'blocked'])
            ->assertJsonPath('message', 'Masa aktif hasil packing box ini sudah habis. Box tidak dapat dikirim; sesi diblokir. Hubungi Admin Warehouse/Admin untuk menangani issue dan penggantian box.');
        $this->postJson(route('delivery.pick.complete', $session->id))->assertStatus(423);
        $this->assertSame('blocked', $session->fresh()->status);
        $this->assertDatabaseHas('delivery_issues', ['pick_session_id' => $session->id, 'issue_type' => 'box_expired']);
        $this->assertSame(1, DeliveryIssue::where('pick_session_id', $session->id)->count());

        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $issue = DeliveryIssue::where('pick_session_id', $session->id)->firstOrFail();
        $this->actingAs($adminWarehouse)->post(route('delivery.pick.issue.approve', $issue->id))->assertRedirect();
        $this->actingAs($operator)->postJson(route('delivery.pick.scan.submit', $session->id), ['box_number' => $box->box_number])
            ->assertStatus(422)->assertJson(['session_status' => 'blocked']);
        $this->assertSame(2, DeliveryIssue::where('pick_session_id', $session->id)->count());
        $this->postJson(route('delivery.pick.scan.submit', $session->id), ['box_number' => $box->box_number])->assertStatus(423);
        $this->assertFalse($box->fresh()->is_withdrawn);
        $this->assertDatabaseCount('stock_withdrawals', 0);
    }

    public function test_handle_history_uses_compact_bootstrap_pagination(): void
    {
        [, $supervisor, $box, $pallet, $location] = $this->inventory();

        foreach (range(1, 16) as $sequence) {
            ExpiredBoxReport::create([
                'box_id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'pallet_id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'warehouse_location' => $location->code,
                'stored_at' => now()->subMonthsNoOverflow(13),
                'age_months' => 13,
                'status' => 'handled',
                'handled_by' => $supervisor->id,
                'handled_at' => now()->subMinutes($sequence),
                'reactivated_until' => now()->addMonthNoOverflow(),
            ]);
        }

        $firstPage = $this->actingAs($supervisor)->get(route('expired-box.index'));
        $firstPage->assertOk()->assertSee('class="page-item', false);
        $firstPage->assertViewHas('handledHistory', fn ($history) => $history->perPage() === 15
            && $history->total() === 16
            && $history->count() === 15);

        $secondPage = $this->actingAs($supervisor)->get(route('expired-box.index', ['page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertViewHas('handledHistory', fn ($history) => $history->total() === 16
            && $history->firstItem() === 16
            && $history->count() === 1);
    }
}
