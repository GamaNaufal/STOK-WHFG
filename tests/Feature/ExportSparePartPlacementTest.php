<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportMasterLocation;
use App\Models\PartSetting;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletMoveService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportReceivingBatchService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportSparePartPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private PartSetting $a;

    private PartSetting $b;

    private int $sequence = 96500000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->a = PartSetting::create(['part_number' => 'SPARE-A', 'qty_box' => 10]);
        $this->b = PartSetting::create(['part_number' => 'SPARE-B', 'qty_box' => 20]);
    }

    private function batch(array $counts = [5, 3])
    {
        $service = app(ExportReceivingBatchService::class);
        $batch = $service->start($this->operator);
        foreach ([$this->a, $this->b] as $i => $part) {
            for ($n = 0; $n < ($counts[$i] ?? 0); $n++) {
                $batch = $service->scan($batch->id, ['box_number' => (string) ++$this->sequence, 'part_number' => $part->part_number,
                    'revision' => $batch->revision, 'scan_token' => (string) Str::uuid()], $this->operator);
            }
        }

        return $service->process($batch->id, $batch->revision, $this->operator);
    }

    private function boxes($batch)
    {
        return ExportStockBox::whereIn('id', $batch->items()->select('export_stock_box_id'))->orderBy('first_received_at')->orderBy('id')->get();
    }

    private function rows($boxes, array $destinations): array
    {
        return $boxes->values()->map(fn ($box, $i) => ['id' => $box->id, 'revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'destination' => $destinations[$i]])->all();
    }

    public function test_spare_limit_is_shared_across_parts_and_survives_pallet_move(): void
    {
        $batch = $this->batch([2, 1]);
        $location = ExportMasterLocation::create(['code' => 'LIMIT-NEW']);
        $rows = $this->rows($this->boxes($batch), array_fill(0, 3, 'location:'.$location->id));
        $withLimit = fn ($limit) => array_map(fn ($row) => $row + ['box_limit' => $limit], $rows);
        $payload = fn ($list) => ['return_batch' => $batch->id, 'placements_json' => json_encode($list)];
        $route = route('export.receiving.place-bulk');

        $this->postJson($route, $payload($withLimit(2)))->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->assertFalse($location->fresh()->is_occupied);
        $this->assertDatabaseCount('export_stock_pallets', 0);
        $this->assertDatabaseCount('export_stock_movements', 0);

        $valid = $withLimit(3);
        $inconsistent = $valid;
        $inconsistent[2]['box_limit'] = 4;
        $this->postJson($route, $payload($inconsistent))->assertUnprocessable()->assertJsonValidationErrors('box_limit');
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()->assertSee('Maksimal box pallet baru');
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode($withLimit(2))])
            ->assertOk()->assertSee('melampaui kapasitas')->assertDontSee('LOKASI: LIMIT-NEW');
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode($valid)])
            ->assertOk()->assertSee('Maksimal 3 box')->assertSee('LOKASI: LIMIT-NEW');
        $this->postJson($route, $payload($valid))->assertRedirect();
        $this->postJson($route, $payload($valid))->assertRedirect();
        $this->postJson($route, $payload($withLimit(4)))->assertUnprocessable()->assertJsonValidationErrors('request_token');

        $pallet = $location->stockPallet;
        $this->assertSame(3, $pallet->box_limit);
        $this->assertSame(3, $pallet->boxes()->count());
        $move = app(ExportPalletMoveService::class);
        $newLocation = ExportMasterLocation::create(['code' => 'LIMIT-MOVED']);
        $move->move(['pallet_id' => $pallet->id, 'location_id' => $newLocation->id,
            'fingerprint' => $move->inspect($pallet)['fingerprint'], 'request_token' => (string) Str::uuid()], $this->operator);
        $this->assertSame($pallet->id, $newLocation->stockPallet->id);
        $this->assertSame(3, $newLocation->stockPallet->box_limit);
        $this->assertFalse($location->fresh()->is_occupied);
    }

    public function test_adding_to_full_pallet_keeps_all_batch_boxes_available_for_redistribution(): void
    {
        $service = app(ExportStockReceivingService::class);
        $original = $this->boxes($this->batch([1, 0]))->sole();
        $firstLocation = ExportMasterLocation::create(['code' => 'LIMIT-FULL']);
        $original = $service->place($original->id, ['revision' => $original->revision, 'request_token' => (string) Str::uuid(),
            'location_id' => $firstLocation->id, 'box_limit' => 1], $this->operator);
        $pallet = $original->currentPallet;
        $next = $this->batch([1, 1]);
        $otherLocation = ExportMasterLocation::create(['code' => 'LIMIT-SPARE']);
        $rows = $this->rows($this->boxes($next), ['location:'.$otherLocation->id, 'pallet:'.$pallet->id]);
        $route = route('export.receiving.place-bulk');
        $this->get(route('export.receiving.transactions.show', $next))->assertOk()->assertSee('1/1 box · Penuh');
        $this->postJson($route, ['return_batch' => $next->id, 'placements_json' => json_encode($rows)])
            ->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->assertDatabaseCount('export_stock_movements', 1);
        $this->assertFalse($otherLocation->fresh()->is_occupied);
        $this->assertSame(['received', 'received'], $this->boxes($next)->pluck('state')->all());
        $rows[1]['destination'] = 'location:'.$otherLocation->id;
        $rows[1]['box_limit'] = 0;
        $this->postJson($route, ['return_batch' => $next->id, 'placements_json' => json_encode($rows)])->assertRedirect();
        $this->assertSame(2, $otherLocation->stockPallet->boxes()->count());
        $this->assertNull($otherLocation->stockPallet->box_limit);
        $this->assertSame(1, $pallet->boxes()->count());
        $this->assertSame(2, ExportStockPallet::count());
    }

    public function test_spare_parts_are_separate_groups_within_the_same_receiving_transaction(): void
    {
        $batch = $this->batch();
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()->assertSee('Part SPARE-A')->assertSee('Part SPARE-B')
            ->assertSee('Bagi ke beberapa tujuan')->assertSee('data-spare-confirm-all', false)
            ->assertViewHas('groups', fn ($groups) => $groups->count() === 2 && $groups[0]['pending']->count() === 5 && $groups[1]['pending']->count() === 3);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($rows) => $rows->total() === 1 && (int) $rows[0]->total_boxes === 8);
        $this->assertDatabaseCount('export_stock_movements', 0);
    }

    public function test_different_parts_can_use_separate_targets_atomically_and_replay_does_not_change_fifo(): void
    {
        $batch = $this->batch();
        $boxes = $this->boxes($batch);
        $one = ExportMasterLocation::create(['code' => 'SPARE-ONE']);
        $two = ExportMasterLocation::create(['code' => 'SPARE-TWO']);
        $dates = $boxes->pluck('first_received_at', 'id')->map->toDateTimeString()->all();
        $rows = $this->rows($boxes, [...array_fill(0, 5, 'location:'.$one->id), ...array_fill(0, 3, 'location:'.$two->id)]);
        $payload = ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)];
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect(route('export.receiving.transactions.show', $batch));
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->assertSame(5, $one->stockPallet->boxes()->count());
        $this->assertSame(3, $two->stockPallet->boxes()->count());
        $this->assertSame($dates, $this->boxes($batch)->pluck('first_received_at', 'id')->map->toDateTimeString()->all());
        $this->assertDatabaseCount('export_stock_movements', 8);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($rows) => $rows->isEmpty());
        $next = $this->batch([1, 0]);
        $this->get(route('export.receiving.transactions.show', $next))->assertSee('Belum dipilih')->assertDontSee('Seluruh box pada transaksi ini sudah ditempatkan.');
    }

    public function test_confirming_one_part_exposes_the_new_pallet_to_the_remaining_part_in_that_batch(): void
    {
        $batch = $this->batch([1, 1]);
        [$first, $second] = $this->boxes($batch)->all();
        $location = ExportMasterLocation::create(['code' => 'SPARE-SHARED']);
        $details = route('export.receiving.transactions.show', $batch);
        $confirm = route('export.receiving.place-bulk');

        $row = $this->rows(collect([$first]), ['location:'.$location->id])[0];
        $this->post($confirm, ['return_batch' => $batch->id,
            'placements_json' => json_encode([$row + ['box_limit' => 2]], JSON_THROW_ON_ERROR)])
            ->assertRedirect($details);

        $pallet = $location->stockPallet;
        $this->assertSame('received', $second->fresh()->state);
        $this->get($details)->assertOk()
            ->assertSee('data-spare-draft-key="'.$this->operator->id.'.TRX-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT).'"', false)
            ->assertSee('data-spare-created-pallets="{&quot;'.$location->id.'&quot;:'.$pallet->id.'}"', false)
            ->assertSee('value="pallet:'.$pallet->id.'" data-location-id="'.$location->id.'"', false)
            ->assertDontSee('data-part="SPARE-A"', false)->assertSee('data-part="SPARE-B"', false);

        $row = $this->rows(collect([$second]), ['pallet:'.$pallet->id])[0];
        $this->post($confirm, ['return_batch' => $batch->id, 'placements_json' => json_encode([$row], JSON_THROW_ON_ERROR)])
            ->assertRedirect($details);
        $this->assertSame(2, $pallet->boxes()->count());
    }

    public function test_split_part_print_and_confirmation_have_exactly_the_same_box_destinations(): void
    {
        $batch = $this->batch();
        $boxes = $this->boxes($batch);
        $locations = collect(['ONE', 'TWO', 'THREE'])->map(fn ($code) => ExportMasterLocation::create(['code' => 'SPLIT-'.$code]));
        $rows = $this->rows($boxes, [...array_fill(0, 3, 'location:'.$locations[0]->id), ...array_fill(0, 2, 'location:'.$locations[1]->id), ...array_fill(0, 3, 'location:'.$locations[2]->id)]);
        $audits = AuditLog::count();
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode(collect($rows)->map(fn ($row) => ['id' => $row['id'], 'destination' => $row['destination']])->all())])
            ->assertOk()->assertViewHas('guideWarning', null)->assertViewHas('printGroups', function ($groups) use ($boxes) {
                return count($groups) === 3 && count($groups[0]['rows']) === 3 && count($groups[1]['rows']) === 2 && count($groups[2]['rows']) === 3
                    && $groups[1]['rows'][0]['box']->id === $boxes[3]->id;
            });
        $this->assertDatabaseCount('export_stock_movements', 0);
        $this->assertSame($audits, AuditLog::count());
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])->assertRedirect();
        foreach ($locations as $i => $location) {
            $this->assertSame([3, 2, 3][$i], $location->stockPallet->boxes()->count());
        }
        $this->get(route('export.receiving.transactions.print', $batch))->assertOk()->assertSee('LOKASI: SPLIT-ONE')->assertSee('LOKASI: SPLIT-TWO')->assertSee('LOKASI: SPLIT-THREE');
    }

    public function test_split_validation_and_foreign_transaction_fail_without_partial_movement(): void
    {
        $batch = $this->batch([2, 0]);
        $other = $this->batch([1, 0]);
        $one = ExportMasterLocation::create(['code' => 'VALID']);
        $two = ExportMasterLocation::create(['code' => 'UNAVAILABLE', 'is_occupied' => true]);
        $rows = $this->rows($this->boxes($batch), ['location:'.$one->id, 'location:'.$two->id]);
        $url = route('export.receiving.place-bulk');
        $this->postJson($url, ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_movements', 0);
        $this->assertFalse($one->fresh()->is_occupied);
        $this->postJson($url, ['return_batch' => $batch->id, 'destination' => 'location:'.$one->id, 'placements_json' => json_encode($rows)])->assertUnprocessable()->assertJsonValidationErrors('placements');
        $rows[1]['destination'] = 'wrong:1';
        $this->postJson($url, ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])->assertUnprocessable()->assertJsonValidationErrors('placements.1.destination');
        $foreign = $this->rows($this->boxes($other), ['location:'.$one->id]);
        $this->postJson($url, ['return_batch' => $batch->id, 'placements_json' => json_encode($foreign)])->assertUnprocessable();
        $this->postJson(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode(collect($foreign)->map(fn ($row) => ['id' => $row['id'], 'destination' => $row['destination']])->all())])->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_movements', 0);
    }

    public function test_reserved_boxes_cannot_be_redirected_to_a_custom_spare_target(): void
    {
        $target = ExportMasterLocation::create(['code' => 'RESERVED']);
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'SPARE-PLAN', 'customer_name' => 'Customer', 'delivery_deadline' => '2026-11-20', 'source_type' => 'manual',
            'items' => [['part_number' => $this->a->part_number, 'pcs_quantity' => 10]]], $this->operator);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $target->id, 'items' => [['part_setting_id' => $this->a->id, 'box_quantity' => 1]]]]], $this->operator);
        $batch = $this->batch([1, 1]);
        $boxes = $this->boxes($batch);
        $spare = ExportMasterLocation::create(['code' => 'SPARE']);
        $rows = $this->rows($boxes, ['location:'.$spare->id, 'location:'.$spare->id]);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])->assertUnprocessable();
        $this->postJson(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode([['id' => $boxes[0]->id, 'destination' => 'location:'.$spare->id]])])->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_movements', 0);
    }

    public function test_print_aggregates_capacity_across_parts_and_revoked_permission_still_applies_to_post(): void
    {
        $stock = $this->batch([34, 0]);
        $stockBoxes = $this->boxes($stock);
        $location = ExportMasterLocation::create(['code' => 'LOAD']);
        app(ExportStockReceivingService::class)->placeMany($stockBoxes->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision, 'request_token' => (string) Str::uuid(), 'location_id' => $location->id])->all(), $this->operator);
        $pallet = $location->stockPallet;
        $batch = $this->batch([1, 1]);
        $assignments = $this->boxes($batch)->map(fn ($box) => ['id' => $box->id, 'destination' => 'pallet:'.$pallet->id])->all();
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode($assignments)])->assertOk()
            ->assertViewHas('printGroups', fn ($groups) => count($groups) === 2 && $groups[0]['load'] === 36 && $groups[1]['load'] === 36);
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->post(route('export.receiving.transactions.print',$batch),['spare_placements_json' => json_encode($assignments)])->assertForbidden();
        $this->assertDatabaseCount('export_stock_movements',34);
    }
}
