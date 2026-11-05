<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxTransaction;
use App\Models\ExportMasterLocation;
use App\Models\ExportStockBox;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxHandlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpiredBoxPackingReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $count = 3, int $pcs = 80): array
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0, 0));
        $user = User::factory()->create(['role' => 'supervisi']);
        $this->actingAs($user);
        PartSetting::create(['part_number' => 'PACK-A', 'qty_box' => 80]);
        $pallet = Pallet::create(['pallet_number' => 'PACK-OLD']);
        $origin = MasterLocation::create(['code' => 'PACK-OLD', 'is_occupied' => true, 'current_pallet_id' => $pallet->id]);
        StockLocation::create(['pallet_id' => $pallet->id, 'master_location_id' => $origin->id,
            'warehouse_location' => $origin->code, 'stored_at' => now()->subMonthsNoOverflow(13)]);
        $boxes = collect();
        for ($i = 1; $i <= $count; $i++) {
            $box = Box::create(['box_number' => sprintf('%08d', $i), 'part_number' => 'PACK-A',
                'pcs_quantity' => $pcs, 'qty_box' => 80, 'is_not_full' => $pcs < 80,
                'not_full_reason' => $pcs < 80 ? 'Approved not full' : null,
                'user_id' => $user->id, 'qr_code' => 'test', 'created_at' => now()->subMonthsNoOverflow(12 + $i)]);
            $pallet->boxes()->attach($box->id);
            $boxes->push($box);
        }
        PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => 'PACK-A', 'box_quantity' => $count, 'pcs_quantity' => $pcs * $count]);
        $location = MasterLocation::create(['code' => 'PACK-NEW']);

        return [$user, $boxes, $location];
    }

    private function start($boxes, User $user)
    {
        $ids = app(ExpiredBoxBulkService::class)->execute(['action' => 'start', 'confirmed' => 1,
            'rows' => $boxes->map(fn ($box) => ['id' => $box->id])->all()], $user->id);

        return ExpiredBoxHandling::whereIn('id', $ids)->orderBy('id')->get();
    }

    private function packing($boxes, User $user)
    {
        $members = $this->start($boxes, $user);
        $this->qaAndPack($members, $user);

        return ExpiredBoxTransaction::findOrFail($members->first()->transaction_id);
    }

    private function qaAndPack($members, User $user): void
    {
        $service = app(ExpiredBoxBulkService::class);
        $service->execute(['action' => 'qa', 'confirmed' => 1,
            'rows' => $members->map(fn ($item) => ['id' => $item->id, 'decision' => 'passed'])->all()], $user->id);
        $service->execute(['action' => 'packing', 'confirmed' => 1,
            'rows' => $members->map(fn ($item) => ['id' => $item->id])->all()], $user->id);
    }

    private function payload(MasterLocation $location, array $numbers): array
    {
        return ['action' => 'receive', 'receipt_token' => (string) Str::uuid(),
            'confirmed' => 1, 'location_id' => $location->id, 'new_box_numbers' => $numbers];
    }

    public function test_scans_receive_in_parts_and_inherit_oldest_fifo_without_operator_entering_part_or_pcs(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.update', $transaction);
        $first = $this->payload($location, ['00900001', '00900002']);
        $this->post($url, $first)->assertSessionHas('success');
        $this->assertSame('at_packing', $transaction->fresh()->stage);
        $this->assertSame(2, Box::active()->count());
        $this->assertSame(1, $transaction->handlings()->where('status', 'at_packing')->count());
        $this->assertSame($boxes[2]->created_at->toDateTimeString(), Box::where('box_number', '00900001')->sole()->created_at->toDateTimeString());
        $this->assertSame($boxes[1]->created_at->toDateTimeString(), Box::where('box_number', '00900002')->sole()->created_at->toDateTimeString());
        $this->assertDatabaseHas('expired_box_handlings', ['source_box_id' => $boxes[2]->id, 'replacement_box_number' => '00900001', 'replacement_mapping' => 'automatic_fifo']);
        $this->assertDatabaseHas('boxes', ['box_number' => '00900001', 'part_number' => 'PACK-A', 'pcs_quantity' => 80]);
        $this->assertSame('2026-02-28 10:00:00', Box::where('box_number', '00900001')->sole()->reactivated_until->toDateTimeString());
        $this->post($url, $first)->assertSessionHas('success');
        $this->assertDatabaseCount('expired_box_receipts', 1);
        $this->assertSame(1, AuditLog::where('action', 'bulk_receive')->count());
        $this->get(route('expired-box.transactions.show', $transaction))->assertOk()->assertSee('Penerimaan boleh bertahap')->assertSee('Riwayat penerimaan packing')->assertSee('00900001');

        $this->travel(2)->days();
        $second = $this->payload($location, ['00900003']);
        $second['pallet_id'] = Box::where('box_number', '00900001')->sole()->pallets()->sole()->id;
        $this->post($url, $second)->assertSessionHas('success');
        $this->assertSame('completed', $transaction->fresh()->stage);
        $this->post($url, $first)->assertSessionHas('success');
        $this->post($url, $second)->assertSessionHas('success');
        $this->assertDatabaseCount('expired_box_receipts', 2);
        $this->assertDatabaseCount('pallets', 2);
        $this->assertSame(3, Box::active()->count());
        $this->assertSame(now()->addMonthNoOverflow()->toDateTimeString(), Box::where('box_number', '00900003')->sole()->reactivated_until->toDateTimeString());
        $this->assertDatabaseCount('stock_inputs', 0);
        $this->assertDatabaseCount('stock_withdrawals', 0);
    }

    public function test_qa_rejections_are_not_expected_back_from_packing(): void
    {
        [$user, $boxes, $location] = $this->inventory(2);
        $members = $this->start($boxes, $user);
        $transaction = ExpiredBoxTransaction::findOrFail($members->first()->transaction_id);
        $url = route('expired-box.transactions.update', $transaction);
        $this->post($url, ['action' => 'qa', 'confirmed' => 1, 'rows' => [
            ['id' => $members[0]->id, 'decision' => 'passed'],
            ['id' => $members[1]->id, 'decision' => 'rejected', 'notes' => 'QA reject'],
        ]])->assertSessionHas('success');
        $this->post($url, ['action' => 'packing', 'confirmed' => 1, 'rows' => [['id' => $members[0]->id]]])->assertSessionHas('success');
        $this->post($url, $this->payload($location, ['00900001']))->assertSessionHas('success');
        $this->assertSame('completed', $transaction->fresh()->stage);
        $this->assertNull(Box::withTrashed()->find($boxes[1]->id));
        $this->assertSame(1, Box::active()->count());
        $this->get(route('expired-box.transactions.show', $transaction))->assertOk()->assertSee('1 box diterima dengan ID baru dan 1 box ditolak QA.');
    }

    public function test_new_transaction_rejects_different_parts_or_different_actual_pcs_before_mutating_inventory(): void
    {
        [$user, $boxes] = $this->inventory(2);
        $url = route('expired-box.bulk.store');
        $payload = ['action' => 'start', 'confirmed' => 1, 'rows' => $boxes->map(fn ($box) => ['id' => $box->id])->all()];
        foreach ([['part_number' => 'PACK-B'], ['part_number' => 'PACK-A', 'pcs_quantity' => 20, 'is_not_full' => true]] as $change) {
            $boxes[1]->update($change);
            $this->post($url, $payload)->assertSessionHasErrors('rows');
            $this->get(route('expired-box.bulk.review', ['action' => 'start', 'ids' => $boxes->pluck('id')->all()]))->assertRedirect(route('expired-box.transactions.create'));
            $this->assertDatabaseCount('expired_box_transactions', 0);
            $this->assertDatabaseCount('expired_box_handlings', 0);
            $this->assertSame(0, Box::where('expired_status', 'handled')->count());
        }
        $this->get(route('expired-box.transactions.create'))->assertOk()->assertSee('PACK-A · 80 PCS/box')->assertSee('PACK-A · 20 PCS/box')->assertSee('data-box-group', false);
    }

    public function test_not_full_receipt_preserves_actual_pcs_and_does_not_use_master_capacity(): void
    {
        [$user, $boxes, $location] = $this->inventory(2, 20);
        $transaction = $this->packing($boxes, $user);
        $payload = $this->payload($location, ['00900001']);
        $payload += ['part_number' => 'FORGED', 'pcs_quantity' => 999, 'received_at' => now()->addYear()->toDateTimeString()];
        $this->post(route('expired-box.transactions.update', $transaction), $payload)->assertSessionHas('success');
        $box = Box::where('box_number', '00900001')->sole();
        $this->assertSame(20, $box->pcs_quantity);
        $this->assertTrue((bool) $box->is_not_full);
        $this->assertSame('PACK-A', $box->part_number);
        $this->assertSame(80, (int) PartSetting::sole()->qty_box);
        $this->assertSame(now()->addMonthNoOverflow()->toDateTimeString(), $box->reactivated_until->toDateTimeString());
        $this->assertNull(ExpiredBoxHandling::where('replacement_box_number', '00900001')->sole()->label_issued_at);
    }

    public function test_reused_malformed_duplicate_and_excess_ids_cannot_create_stock(): void
    {
        [$user, $boxes, $location] = $this->inventory(2);
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.update', $transaction);
        foreach ([['123'], [$boxes[0]->box_number], ['00900001', '00900001'], ['00900001', '00900002', '00900003']] as $numbers) {
            $this->post($url, $this->payload($location, $numbers))->assertSessionHasErrors();
            $this->assertDatabaseCount('boxes', 2);
            $this->assertDatabaseCount('expired_box_receipts', 0);
            $this->assertFalse($location->fresh()->is_occupied);
        }
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00900001']);
    }

    public function test_scanner_checks_global_identity_without_creating_stock_and_preserves_permissions(): void
    {
        [$user, $boxes] = $this->inventory();
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.scan', $transaction);
        $group = $transaction->handlings()->first()->group_key;
        $this->postJson($url, ['box_number' => '00900001', 'group_key' => $group])->assertOk()->assertJsonPath('outcome', 'ok');
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00900001']);
        $this->postJson($url, ['box_number' => '123', 'group_key' => $group])->assertUnprocessable();
        $this->postJson($url, ['box_number' => $boxes[0]->box_number, 'group_key' => $group])->assertUnprocessable();
        $part = PartSetting::sole();
        $export = ExportStockBox::create(['box_number' => '00900002', 'part_number' => $part->part_number, 'part_setting_id' => $part->id,
            'pcs_quantity' => 80, 'first_received_at' => now(), 'received_by' => $user->id, 'receive_token' => (string) Str::uuid()]);
        $export->forceDelete();
        $this->postJson($url, ['box_number' => '00900002', 'group_key' => $group])->assertUnprocessable();
        $this->postJson($url, ['box_number' => '00900003', 'group_key' => str_repeat('a', 64)])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']))->postJson($url, ['box_number' => '00900001', 'group_key' => $group])->assertForbidden();
    }

    public function test_changed_payload_or_different_operator_cannot_reuse_a_receipt_token(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.update', $transaction);
        $payload = $this->payload($location, ['00900001']);
        $this->post($url, $payload)->assertSessionHas('success');
        $changed = $payload;
        $changed['new_box_numbers'] = ['00900002'];
        $this->post($url, $changed)->assertSessionHasErrors('receipt_token');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post($url, $payload)->assertSessionHasErrors('receipt_token');
        $this->assertDatabaseCount('expired_box_receipts', 1);
        $this->assertSame(1, Box::active()->count());
    }

    public function test_later_submission_rechecks_remaining_quantity_and_allows_another_receipt_destination(): void
    {
        [$user, $boxes, $location] = $this->inventory(2);
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.update', $transaction);
        $this->post($url, $this->payload($location, ['00900001']))->assertSessionHas('success');
        $other = MasterLocation::create(['code' => 'PACK-OTHER']);
        $this->post($url, $this->payload($other, ['00900002', '00900003']))->assertSessionHasErrors('new_box_numbers');
        $this->post($url, $this->payload($other, ['00900002']))->assertSessionHas('success');
        $this->assertSame('PACK-OTHER', ExpiredBoxHandling::where('replacement_box_number', '00900002')->sole()->destination_location);
        $this->assertSame('completed', $transaction->fresh()->stage);
        $this->assertDatabaseCount('expired_box_receipts', 2);
    }

    public function test_wrong_scope_or_occupied_destination_rolls_back_the_whole_receipt(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $transaction = $this->packing($boxes, $user);
        $export = ExportMasterLocation::create(['code' => 'PACK-EXPORT']);
        $payload = $this->payload($location, ['00900001', '00900002']);
        $payload['location_id'] = $export->id;
        $url = route('expired-box.transactions.update', $transaction);
        $this->post($url, $payload)->assertSessionHasErrors('location_id');
        $occupied = Pallet::create(['pallet_number' => 'OCCUPIED']);
        $location->update(['is_occupied' => true, 'current_pallet_id' => $occupied->id]);
        $this->post($url, $this->payload($location, ['00900001']))->assertSessionHas('error');
        $this->assertDatabaseCount('expired_box_receipts', 0);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00900001']);
        $this->assertSame(3, $transaction->handlings()->where('status', 'at_packing')->count());
    }

    public function test_receipt_audit_failure_rolls_back_new_ids_and_pallet_and_can_retry_same_token(): void
    {
        [$user, $boxes, $location] = $this->inventory();
        $transaction = $this->packing($boxes, $user);
        $url = route('expired-box.transactions.update', $transaction);
        $payload = $this->payload($location, ['00900001', '00900002']);
        AuditLog::creating(function ($log) {
            if ($log->action === 'bulk_receive') {
                throw new \RuntimeException('Receipt audit failed');
            }
        });
        try {
            $this->post($url, $payload)->assertSessionHas('error', 'Receipt audit failed');
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('expired_box_receipts', 0);
        $this->assertDatabaseCount('boxes', 3);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00900001']);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertSame(2, Box::active()->count());
    }

    public function test_legacy_mixed_transaction_can_be_received_by_selected_group_without_changing_membership(): void
    {
        [$user, $boxes, $location] = $this->inventory(2);
        $boxes[1]->update(['pcs_quantity' => 20, 'is_not_full' => true]);
        $workflow = app(ExpiredBoxHandlingService::class);
        $first = $workflow->start($boxes[0]->id, $user->id);
        $second = $workflow->start($boxes[1]->id, $user->id);
        // Historical transactions could contain mixed PCS before this revision.
        $second->update(['transaction_id' => $first->transaction_id]);
        $this->qaAndPack(ExpiredBoxHandling::where('transaction_id', $first->transaction_id)->get(), $user);
        $url = route('expired-box.transactions.update', $first->transaction_id);
        $payload = $this->payload($location, ['00900001']);
        $this->post($url, $payload)->assertSessionHasErrors('group_key');
        $this->get(route('expired-box.transactions.show', $first->transaction_id))->assertOk()->assertSee('Pilih kelompok transaksi lama');
        $payload['group_key'] = $second->group_key;
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('boxes', ['box_number' => '00900001', 'pcs_quantity' => 20]);
        $this->assertSame('at_packing', $first->fresh()->status);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertSame(2, $first->transaction->handlings()->count());
    }
}
