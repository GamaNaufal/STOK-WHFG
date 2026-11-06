<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\ExportExpiredBoxHandling;
use App\Models\ExportExpiredBoxReceipt;
use App\Models\ExportExpiredBoxTransaction;
use App\Models\ExportMasterLocation;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\MasterLocation;
use App\Models\PartSetting;
use App\Models\User;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxHandlingService;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportExpiredBoxTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $count = 3, int $pcs = 80): array
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(10, 0));
        $actor = User::factory()->create(['role' => 'admin_warehouse']);
        $this->actingAs($actor);
        $part = PartSetting::create(['part_number' => 'EX-QA', 'qty_box' => 80]);
        $location = ExportMasterLocation::create(['code' => 'EX-QA-OLD', 'is_occupied' => true]);
        $pallet = ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id, 'location_code' => $location->code, 'created_by' => $actor->id]);
        $boxes = collect();
        for ($i = 1; $i <= $count; $i++) {
            $boxes->push(ExportStockBox::create(['box_number' => sprintf('71%06d', $i), 'part_setting_id' => $part->id, 'part_number' => $part->part_number,
                'pcs_quantity' => $pcs, 'standard_pcs_quantity' => 80, 'is_not_full' => $pcs < 80,
                'receipt_pcs_quantity' => $pcs, 'state' => 'stored', 'revision' => 1, 'receive_token' => (string) Str::uuid(),
                'first_received_at' => now()->subMonthsNoOverflow(12 + $i), 'received_by' => $actor->id,
                'stored_at' => now()->subMonthsNoOverflow(12 + $i), 'stored_by' => $actor->id, 'current_pallet_id' => $pallet->id]));
        }
        $destination = ExportMasterLocation::create(['code' => 'EX-QA-NEW']);

        return [$actor, $part, $boxes, $pallet, $destination];
    }

    private function start($boxes, User $actor)
    {
        $ids = app(ExpiredBoxBulkService::class)->execute(['action' => 'start', 'confirmed' => 1,
            'rows' => $boxes->map(fn ($box) => ['id' => $box->id])->all()], $actor->id, 'export');

        return ExportExpiredBoxHandling::whereIn('id', $ids)->orderBy('id')->get();
    }

    private function packing($boxes, User $actor)
    {
        $members = $this->start($boxes, $actor);
        $service = app(ExpiredBoxBulkService::class);
        $service->execute(['action' => 'qa', 'confirmed' => 1, 'rows' => $members->map(fn ($h) => ['id' => $h->id, 'decision' => 'passed'])->all()], $actor->id, 'export');
        $service->execute(['action' => 'packing', 'confirmed' => 1, 'rows' => $members->map(fn ($h) => ['id' => $h->id])->all()], $actor->id, 'export');

        return ExportExpiredBoxTransaction::findOrFail($members->first()->transaction_id);
    }

    private function receipt(ExportMasterLocation $location, array $numbers): array
    {
        return ['action' => 'receive', 'confirmed' => 1, 'receipt_token' => (string) Str::uuid(), 'location_id' => $location->id, 'new_box_numbers' => $numbers];
    }

    private function plan(PartSetting $part, User $actor, int $boxes = 1)
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'QA-PLAN', 'customer_name' => 'QA Customer',
            'delivery_deadline' => now()->addDays(5)->toDateString(), 'source_type' => 'manual',
            'items' => [['part_number' => $part->part_number, 'pcs_quantity' => 80 * $boxes]]], $actor);
        $location = ExportMasterLocation::create(['code' => 'EX-QA-PLAN']);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id, 'items' => [['part_setting_id' => $part->id, 'box_quantity' => $boxes]]]]], $actor);

        return $delivery->fresh(['palletPlan.pallets.items']);
    }

    public function test_export_workspace_shows_groups_and_ajax_scope_without_mixing_local_inventory(): void
    {
        [$actor, $part, $boxes] = $this->inventory();
        $this->get(route('export.expired-box.index'))->assertOk()->assertViewHas('expiredBoxes', fn ($items) => $items->count() === 3)->assertSee('EX-QA')->assertSee('3 box')->assertSee('data-warehouse-scope="export"', false);
        $this->get(route('expired-box.index'))->assertOk()->assertViewHas('expiredBoxes', fn ($items) => $items->isEmpty());
        $this->getJson(route('export.expired-box.index', ['search' => $part->part_number]))->assertOk()->assertJsonStructure(['html', 'url'])->assertJsonPath('html', fn ($html) => str_contains($html, 'EX-QA'));
        $this->get(route('export.expired-box.transactions.create'))->assertOk()->assertSee('71')->assertSee('data-box-group', false);
        $this->getJson(route('export.expired-box.index', ['location_id' => MasterLocation::create(['code' => 'LOCAL'])->id]))->assertUnprocessable();
    }

    public function test_same_numeric_source_primary_key_in_local_and_export_has_separate_qa_history(): void
    {
        [$actor, $part, $boxes] = $this->inventory(1);
        $local = Box::create(['box_number' => '72000001', 'part_number' => $part->part_number, 'pcs_quantity' => 80, 'qty_box' => 80,
            'qr_code' => 'local', 'user_id' => $actor->id, 'created_at' => now()->subMonthsNoOverflow(13)]);
        $this->assertSame($local->id, $boxes[0]->id);
        app(ExpiredBoxHandlingService::class)->start($local->id, $actor->id);
        $members = $this->start($boxes, $actor);
        $this->assertSame(1, ExpiredBoxHandling::count());
        $this->assertSame(1, ExportExpiredBoxHandling::count());
        $this->get(route('expired-box.show', $members[0]->id))->assertNotFound();
        $this->get(route('export.expired-box.show', ExpiredBoxHandling::sole()->id))->assertNotFound();
    }

    public function test_export_receipt_is_partial_idempotent_and_preserves_fifo_and_actual_not_full_pcs(): void
    {
        [$actor, $part, $boxes, $sourcePallet, $location] = $this->inventory(3, 20);
        $transaction = $this->packing($boxes, $actor);
        $this->assertNotNull($sourcePallet->fresh()->retired_at);
        $url = route('export.expired-box.transactions.update', $transaction);
        $this->get(route('export.expired-box.transactions.show', $transaction))->assertOk()->assertSee('Scan hasil packing')->assertSee('20 PCS/box')->assertDontSee('name="label_issued_at"', false);
        $payload = $this->receipt($location, ['73000001', '73000002']);
        $this->post($url, $payload)->assertSessionHas('success');
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertSame(1, ExportExpiredBoxReceipt::count());
        $this->assertSame('at_packing', $transaction->fresh()->stage);
        $new = ExportStockBox::where('box_number', '73000001')->sole();
        $this->assertSame(20, $new->pcs_quantity);
        $this->assertTrue($new->is_not_full);
        $this->assertSame($boxes[2]->first_received_at->toDateTimeString(), $new->first_received_at->toDateTimeString());
        $this->assertSame(now()->addMonthNoOverflow()->toDateTimeString(), $new->reactivated_until->toDateTimeString());
        $this->assertSame('stored', $new->state);
        $this->assertSame(2, ExportStockBox::eligible()->count());
        $this->assertDatabaseCount('stock_inputs', 0);
        $this->assertDatabaseCount('stock_withdrawals', 0);
        $this->assertSame(1, AuditLog::where('type', 'export_expired_box')->where('action', 'bulk_receive')->count());
        $this->travel(2)->days();
        $last = $this->receipt($location, ['73000003']);
        $last['pallet_id'] = $new->current_pallet_id;
        $this->post($url, $last)->assertSessionHas('success');
        $this->assertSame('completed', $transaction->fresh()->stage);
        $this->assertSame(2, ExportExpiredBoxReceipt::count());
        $this->assertSame(3, ExportStockBox::count());
        $this->assertSame(3, ExportStockBox::eligible()->count());
        $this->travelTo($new->reactivated_until);
        $this->assertSame(1, ExportStockBox::eligible()->count());
        $this->assertSame('Expired', $new->fresh()->expiry_label);
        $this->assertCount(1, $this->start(collect([$new->fresh()]), $actor));
    }

    public function test_packing_receipt_cannot_exceed_spare_pallet_capacity(): void
    {
        [$actor, $part, $boxes, , $location] = $this->inventory(2);
        $transaction = $this->packing($boxes, $actor);
        $receiving = app(ExportStockReceivingService::class);
        $existing = $receiving->receive(['box_number' => '73200000', 'part_number' => $part->part_number,
            'receive_token' => (string) Str::uuid()], $actor);
        $existing = $receiving->place($existing->id, ['revision' => $existing->revision, 'request_token' => (string) Str::uuid(),
            'location_id' => $location->id, 'box_limit' => 2], $actor);
        $url = route('export.expired-box.transactions.update', $transaction);
        $payload = $this->receipt($location, ['73200001', '73200002']);
        $payload['pallet_id'] = $existing->current_pallet_id;
        $this->get(route('export.expired-box.transactions.show', $transaction))->assertOk()->assertSee('1/2 box · Sisa 1');
        $this->post($url, $payload)->assertSessionHasErrors('destination');
        $this->assertDatabaseMissing('box_identities', ['box_number' => '73200001']);
        $this->assertSame(0, ExportExpiredBoxReceipt::count());
        $this->assertSame(1, $existing->currentPallet->boxes()->count());

        $payload['new_box_numbers'] = ['73200001'];
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertSame(2, $existing->currentPallet->boxes()->count());
        $this->assertSame('at_packing', $transaction->fresh()->stage);
    }

    public function test_qa_rejected_members_leave_inventory_and_history_and_global_id_survive(): void
    {
        [$actor, $part, $boxes, $pallet, $location] = $this->inventory(2);
        $members = $this->start($boxes, $actor);
        $transaction = ExportExpiredBoxTransaction::sole();
        $url = route('export.expired-box.transactions.update', $transaction);
        $this->post($url, ['action' => 'qa', 'confirmed' => 1, 'rows' => [
            ['id' => $members[0]->id, 'decision' => 'passed'], ['id' => $members[1]->id, 'decision' => 'rejected', 'notes' => 'QA tidak lolos'],
        ]])->assertSessionHas('success');
        $this->assertNull(ExportStockBox::find($boxes[1]->id));
        $this->assertSoftDeleted('export_stock_boxes', ['id' => $boxes[1]->id]);
        $this->assertNotNull($members[1]->fresh()->history_snapshot);
        $this->assertDatabaseHas('box_identities', ['box_number' => $boxes[1]->box_number]);
        $this->post($url, ['action' => 'packing', 'confirmed' => 1, 'rows' => [['id' => $members[0]->id]]])->assertSessionHas('success');
        $this->post($url, $this->receipt($location, ['73000001']))->assertSessionHas('success');
        $this->assertSame('completed', $transaction->fresh()->stage);
        $this->get(route('export.expired-box.index', ['tab' => 'history', 'search' => $boxes[1]->box_number]))->assertOk()->assertSee($transaction->reference);
    }

    public function test_atomic_qa_rejects_wrong_group_and_packing_receipt_rejects_local_location_and_reused_ids(): void
    {
        [$actor, $part, $boxes, $pallet, $location] = $this->inventory(2);
        $transaction = $this->packing($boxes, $actor);
        $url = route('export.expired-box.transactions.update', $transaction);
        $payload = $this->receipt($location, ['73000001']);
        $payload['location_id'] = MasterLocation::create(['code' => 'LOCAL'])->id;
        $this->post($url, $payload)->assertSessionHasErrors('location_id');
        $this->post($url, $this->receipt($location, [$boxes[0]->box_number]))->assertSessionHasErrors('new_box_numbers.0');
        $this->post($url, $this->receipt($location, ['73000001', '73000002', '73000003']))->assertSessionHasErrors('new_box_numbers');
        $this->assertDatabaseMissing('box_identities', ['box_number' => '73000001']);
        $this->assertSame(0, ExportExpiredBoxReceipt::count());
        $this->assertSame(2, ExportExpiredBoxHandling::where('status', 'at_packing')->count());
    }

    public function test_warehouse_and_export_operators_cannot_access_qa_and_allowed_roles_share_one_menu(): void
    {
        [$actor, $part, $boxes] = $this->inventory(1);
        foreach (['export_operator', 'warehouse_operator', 'sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('export.expired-box.index'))->assertForbidden();
            $this->get(route('expired-box.index'))->assertForbidden();
        }
        foreach (['supervisi', 'admin_warehouse', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('export.expired-box.index'))->assertOk();
            $this->get(route('expired-box.index'))->assertOk();
            $navigation = app(\App\Services\NavigationContextService::class)->build($user, 'export.expired-box.transactions.show');
            $this->assertSame(1, collect($navigation['items'])->where('label', 'Expired Box dan Handle')->count());
            $this->assertTrue(collect($navigation['items'])->firstWhere('route', 'export.expired-box.index')['active']);
        }
    }

    public function test_expired_inventory_is_never_reserved_and_reserved_stock_must_be_revised_before_qa(): void
    {
        [$actor, $part, $boxes] = $this->inventory(1);
        $delivery = $this->plan($part, $actor);
        $this->assertDatabaseCount('export_fulfillment_boxes', 0);
        $boxes[0]->update(['reactivated_until' => now()->addDay(), 'expired_status' => 'active']);
        app(ExportStockAllocationService::class)->allocate($actor);
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
        $boxes[0]->update(['reactivated_until' => now()->subSecond()]);
        $this->post(route('export.expired-box.bulk.store'), ['action' => 'start', 'confirmed' => 1, 'rows' => [['id' => $boxes[0]->id]]])->assertSessionHasErrors('rows.0.id');
        $this->assertSame(0, ExportExpiredBoxHandling::count());
        $this->get(route('export.expired-box.index'))->assertOk()->assertSee('1 terikat permintaan');
    }

    public function test_audit_failure_rolls_back_all_replacement_ids_and_allows_retry_with_original_token(): void
    {
        [$actor, $part, $boxes, $pallet, $location] = $this->inventory(2);
        $transaction = $this->packing($boxes, $actor);
        $payload = $this->receipt($location, ['73000001', '73000002']);
        AuditLog::creating(function ($log) {
            if ($log->type === 'export_expired_box' && $log->action === 'bulk_receive') {
                throw new \RuntimeException('QA receipt audit failed');
            }
        });
        try {
            $this->post(route('export.expired-box.transactions.update', $transaction), $payload)->assertSessionHas('error', 'QA receipt audit failed');
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseMissing('box_identities', ['box_number' => '73000001']);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->assertSame(0, ExportExpiredBoxReceipt::count());
        $this->post(route('export.expired-box.transactions.update', $transaction), $payload)->assertSessionHas('success');
        $this->assertSame(2, ExportStockBox::eligible()->count());
    }

    public function test_not_full_source_obeys_expiry_and_packing_validity_without_scheduler(): void
    {
        [$actor, $part, $boxes] = $this->inventory(2);
        $request = new \App\Models\ExportNotFullRequest(['part_setting_id' => $part->id, 'fixed_qty' => 80]);
        $service = app(\App\Services\ExportNotFullService::class);
        $this->assertNull($service->source($request));
        $boxes[1]->update(['reactivated_until' => now()->addMonthNoOverflow()]);
        $this->assertSame($boxes[1]->id, $service->source($request)->id);
        $this->travelTo($boxes[1]->fresh()->reactivated_until);
        $this->assertNull($service->source($request));
    }

    public function test_migration_grants_warehouse_admin_and_preserves_explicit_supervisor_denial(): void
    {
        $supervisor = \App\Models\RoleFeatureAccess::create(['role' => 'supervisi', 'permissions' => ['reports']]);
        $warehouse = \App\Models\RoleFeatureAccess::create(['role' => 'admin_warehouse', 'permissions' => ['stock_input']]);
        $migration = require database_path('migrations/2026_11_05_000002_support_export_expired_boxes.php');
        $migration->down();
        $migration->up();
        $this->assertSame(['reports'], $supervisor->fresh()->permissions);
        $this->assertContains('expired_box', $warehouse->fresh()->permissions);
        $this->assertContains('export_expired_box', $warehouse->fresh()->permissions);
        $this->assertContains('stock_input', $warehouse->fresh()->permissions);
    }
}
