<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxHandlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredBoxBulkHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $count = 2): array
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0, 0));
        $user = User::factory()->create(['role' => 'supervisi']);
        $this->actingAs($user);
        $pallet = Pallet::create(['pallet_number' => 'PLT-BULK']);
        $location = MasterLocation::create(['code' => 'BULK-01', 'is_occupied' => true, 'current_pallet_id' => $pallet->id]);
        StockLocation::create(['pallet_id' => $pallet->id, 'master_location_id' => $location->id, 'warehouse_location' => $location->code, 'stored_at' => now()->subMonthsNoOverflow(13)]);
        $boxes = collect();
        foreach (range(1, $count) as $index) {
            $part = 'BULK-PART-'.$index;
            PartSetting::create(['part_number' => $part, 'qty_box' => 20]);
            $box = Box::create(['box_number' => (string) (98000000 + $index), 'part_number' => $part, 'pcs_quantity' => 20, 'qty_box' => 20, 'qr_code' => 'test', 'user_id' => $user->id, 'created_at' => now()->subMonthsNoOverflow(13)]);
            $pallet->boxes()->attach($box->id);
            PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => $part, 'box_quantity' => 1, 'pcs_quantity' => 20]);
            $boxes->push($box);
        }

        return [$user, $boxes, $location, $pallet];
    }

    private function start($boxes, User $user)
    {
        $ids = app(ExpiredBoxBulkService::class)->execute(['action' => 'start', 'rows' => $boxes->map(fn ($b) => ['id' => $b->id])->all(), 'confirmed' => 1], $user->id);

        return ExpiredBoxHandling::whereIn('id', $ids)->orderBy('id')->get();
    }

    private function atPacking($boxes, User $user)
    {
        $handlings = $this->start($boxes, $user);
        $service = app(ExpiredBoxBulkService::class);
        $service->execute(['action' => 'qa', 'rows' => $handlings->map(fn ($h) => ['id' => $h->id, 'decision' => 'passed'])->all(), 'confirmed' => 1], $user->id);
        $service->execute(['action' => 'packing', 'rows' => $handlings->map(fn ($h) => ['id' => $h->id])->all(), 'confirmed' => 1], $user->id);

        return $handlings->map->fresh();
    }

    private function receipt($handlings, MasterLocation $location): array
    {
        return ['action' => 'receive', 'confirmed' => 1, 'location_id' => $location->id,
            'rows' => $handlings->values()->map(fn ($h, $index) => [
                'id' => $h->id, 'box_number' => (string) (99000001 + $index),
                'part_number' => $h->part_number, 'pcs_quantity' => $h->pcs_quantity,
                'label_issued_at' => now()->toDateTimeString(),
            ])->all()];
    }

    public function test_multiple_boxes_start_qa_together_and_replay_has_no_duplicate_audit(): void
    {
        [$user, $boxes] = $this->inventory(3);
        $this->get(route('expired-box.bulk.review', ['action' => 'start', 'ids' => $boxes->pluck('id')->all()]))->assertOk()->assertSee('3 box dipilih');
        $payload = ['action' => 'start', 'rows' => $boxes->map(fn ($b) => ['id' => $b->id])->all(), 'confirmed' => 1];
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success')->assertRedirect();
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('expired_box_handlings', 3);
        $this->assertSame(3, Box::where('expired_status', 'handled')->count());
        $this->assertSame(1, AuditLog::where('action', 'bulk_start')->count());
        $this->assertDatabaseCount('pallet_boxes', 0);
        $this->get(route('expired-box.bulk.review', ['action' => 'result', 'ids' => ExpiredBoxHandling::pluck('id')->all()]))->assertOk()->assertSee('Catat hasil QA');
    }

    public function test_invalid_second_box_rolls_back_all_qa_handoffs(): void
    {
        [, $boxes] = $this->inventory();
        $boxes[1]->update(['created_at' => now()]);
        $this->post(route('expired-box.bulk.store'), ['action' => 'start', 'rows' => $boxes->map(fn ($b) => ['id' => $b->id])->all(), 'confirmed' => 1])->assertSessionHasErrors('rows.1.id');
        $this->assertDatabaseCount('expired_box_handlings', 0);
        $this->assertDatabaseCount('pallet_boxes', 2);
        $this->assertSame('expired', $boxes[0]->fresh()->expired_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sent_qa']);
    }

    public function test_qa_can_record_mixed_results_and_print_only_passed_boxes(): void
    {
        [$user, $boxes] = $this->inventory(3);
        $handlings = $this->start($boxes, $user);
        $this->get(route('expired-box.bulk.review', ['action' => 'qa', 'ids' => $handlings->pluck('id')->all()]))->assertOk()->assertSee('Hasil QA dapat berbeda');
        $payload = ['action' => 'qa', 'confirmed' => 1, 'rows' => [
            ['id' => $handlings[0]->id, 'decision' => 'passed'],
            ['id' => $handlings[1]->id, 'decision' => 'rejected', 'notes' => 'QA gagal'],
            ['id' => $handlings[2]->id, 'decision' => 'passed'],
        ]];
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertNull(Box::withTrashed()->find($boxes[1]->id));
        $this->assertNotNull($handlings[1]->fresh()->history_snapshot);
        $this->assertSame(2, ExpiredBoxHandling::where('status', 'awaiting_packing')->count());
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertSame(1, AuditLog::where('action', 'bulk_qa')->count());
        $passed = [$handlings[0]->id, $handlings[2]->id];
        $this->get(route('expired-box.bulk.review', ['action' => 'print', 'ids' => $passed]))->assertOk()->assertSee($boxes[0]->box_number)->assertSee($boxes[2]->box_number)->assertDontSee($boxes[1]->box_number);
        $this->get(route('expired-box.bulk.review', ['action' => 'print', 'ids' => $handlings->pluck('id')->all()]))->assertSessionHas('error');
    }

    public function test_invalid_qa_row_restores_a_preceding_permanent_deletion(): void
    {
        [$user, $boxes] = $this->inventory();
        $handlings = $this->start($boxes, $user);
        app(ExpiredBoxHandlingService::class)->decideQa($handlings[1]->id, 'passed', null, $user->id);
        $rows = $handlings->map(fn ($h) => ['id' => $h->id, 'decision' => 'rejected', 'notes' => 'QA gagal'])->all();
        $this->post(route('expired-box.bulk.store'), ['action' => 'qa', 'rows' => $rows, 'confirmed' => 1])->assertSessionHasErrors('rows.1.id');
        $this->assertNotNull($boxes[0]->fresh());
        $this->assertSame('at_qa', $handlings[0]->fresh()->status);
        $this->assertNull($handlings[0]->fresh()->history_snapshot);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'qa_rejected']);
    }

    public function test_packing_rejects_mixed_stages_without_partial_handoff(): void
    {
        [$user, $boxes] = $this->inventory();
        $handlings = $this->start($boxes, $user);
        app(ExpiredBoxHandlingService::class)->decideQa($handlings[0]->id, 'passed', null, $user->id);
        $this->post(route('expired-box.bulk.store'), ['action' => 'packing', 'rows' => $handlings->map(fn ($h) => ['id' => $h->id])->all(), 'confirmed' => 1])->assertSessionHasErrors('rows.1.id');
        $this->assertSame('awaiting_packing', $handlings[0]->fresh()->status);
        $this->assertNull($handlings[0]->fresh()->sent_packing_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sent_packing']);
    }

    public function test_receipt_uses_one_new_pallet_and_keeps_individual_id_and_expiry(): void
    {
        [$user, $boxes, $location] = $this->inventory(3);
        $handlings = $this->atPacking($boxes, $user);
        $this->get(route('expired-box.bulk.review', ['action' => 'receive', 'ids' => $handlings->pluck('id')->all()]))->assertOk()->assertSee('Tujuan penerimaan bersama');
        $payload = $this->receipt($handlings, $location);
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('pallets', 2);
        $this->assertDatabaseCount('stock_inputs', 0);
        $this->assertDatabaseCount('stock_withdrawals', 0);
        $this->assertDatabaseCount('boxes', 6);
        $this->assertSame(3, Box::active()->count());
        foreach ($handlings as $index => $handling) {
            $handling->refresh();
            $this->assertSame($payload['rows'][$index]['box_number'], $handling->replacement_box_number);
            $replacement = Box::findOrFail($handling->replacement_box_id);
            $this->assertSame($boxes[$index]->created_at->toDateTimeString(), $replacement->created_at->toDateTimeString());
            $this->assertSame('2026-02-28 10:00:00', $replacement->reactivated_until->toDateTimeString());
            $this->assertSame($location->fresh()->current_pallet_id, $replacement->pallets()->sole()->id);
        }
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('pallets', 2);
        $this->assertDatabaseCount('boxes', 6);
        $this->assertSame(1, AuditLog::where('action', 'bulk_receive')->count());
    }

    public function test_bad_receipt_rolls_back_prior_box_identity_pallet_location_and_audit(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $handlings = $this->atPacking($boxes, $user);
        $payload = $this->receipt($handlings, $location);
        $payload['rows'][1]['pcs_quantity'] = 19;
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHasErrors('rows.1.id');
        $this->assertDatabaseCount('boxes', 2);
        $this->assertDatabaseCount('pallets', 1);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '99000001']);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->assertSame(2, ExpiredBoxHandling::where('status', 'at_packing')->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'packing_received']);
        $payload['rows'][1]['pcs_quantity'] = 20;
        $payload['rows'][1]['box_number'] = $boxes[0]->box_number;
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHasErrors('rows.1.box_number');
        $this->assertDatabaseMissing('boxes', ['box_number' => '99000001']);
    }

    public function test_duplicate_replacement_ids_and_missing_qa_notes_are_rejected(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $handlings = $this->atPacking($boxes, $user);
        $payload = $this->receipt($handlings, $location);
        $payload['rows'][1]['box_number'] = $payload['rows'][0]['box_number'];
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHasErrors('rows.1.box_number');
        $this->post(route('expired-box.bulk.store'), ['action' => 'qa', 'confirmed' => 1, 'rows' => [['id' => $handlings[0]->id, 'decision' => 'rejected']]])->assertSessionHasErrors('rows.0.notes');
        $this->assertDatabaseCount('boxes', 2);
    }

    public function test_late_first_receipt_does_not_vacate_pallet_before_active_second_receipt(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $handlings = $this->atPacking($boxes, $user);
        $issued = now()->toDateTimeString();
        $this->travel(35)->days();
        $payload = $this->receipt($handlings, $location);
        $payload['rows'][0]['label_issued_at'] = $issued;
        $this->post(route('expired-box.bulk.store'), $payload)->assertSessionHas('success');
        $this->assertSame('expired', Box::where('box_number', '99000001')->sole()->expired_status);
        $this->assertSame('active', Box::where('box_number', '99000002')->sole()->expired_status);
        $this->assertTrue($location->fresh()->is_occupied);
        $this->assertSame(1, Box::active()->count());
    }

    public function test_batch_audit_failure_rolls_back_all_rows(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $handlings = $this->atPacking($boxes, $user);
        AuditLog::creating(function ($log) {
            if ($log->action === 'bulk_receive') {
                throw new \RuntimeException('Bulk audit failed');
            }
        });
        try {
            $this->post(route('expired-box.bulk.store'), $this->receipt($handlings, $location))->assertSessionHas('error', 'Bulk audit failed');
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('boxes', 2);
        $this->assertDatabaseCount('pallets', 1);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '99000001']);
        $this->assertFalse($location->fresh()->is_occupied);
    }

    public function test_batch_limit_duplicates_confirmation_and_authorization_are_enforced(): void
    {
        [, $boxes] = $this->inventory();
        $route = route('expired-box.bulk.store');
        $this->post($route, ['action' => 'start', 'confirmed' => 1, 'rows' => []])->assertSessionHasErrors('rows');
        $this->post($route, ['action' => 'start', 'rows' => [['id' => $boxes[0]->id]]])->assertSessionHasErrors('confirmed');
        $this->post($route, ['action' => 'start', 'confirmed' => 1, 'rows' => [['id' => $boxes[0]->id], ['id' => $boxes[0]->id]]])->assertSessionHasErrors('rows.1.id');
        $this->post($route, ['action' => 'start', 'confirmed' => 1, 'rows' => array_map(fn ($id) => ['id' => $id], range(1, 51))])->assertSessionHasErrors('rows');
        $this->get(route('expired-box.bulk.review', ['action' => 'start', 'ids' => range(1, 51)]))->assertSessionHasErrors('ids');
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs($operator)->post($route, ['action' => 'start', 'confirmed' => 1, 'rows' => [['id' => $boxes[0]->id]]])->assertForbidden();
        $this->get(route('expired-box.bulk.review', ['action' => 'start', 'ids' => [$boxes[0]->id]]))->assertForbidden();
        $this->assertDatabaseCount('expired_box_handlings', 0);
    }
}
