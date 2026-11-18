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

    private function storedPallet(array $counts, string $code, ?int $limit): ExportStockPallet
    {
        $location = ExportMasterLocation::create(['code' => $code]);
        $rows = $this->boxes($this->batch($counts))->map(fn ($box) => [
            'id' => $box->id, 'revision' => $box->revision, 'request_token' => (string) Str::uuid(),
            'location_id' => $location->id, 'box_limit' => $limit,
        ])->all();
        app(ExportStockReceivingService::class)->placeMany($rows, $this->operator);

        return $location->stockPallet;
    }

    public function test_first_part_placement_is_manual_and_next_receipt_recommends_the_same_pallet_without_writing_stock(): void
    {
        $batch = $this->batch([1, 0]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertSee('Pilih penempatan pertama untuk part ini')
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_recommendation'] === null
                && $groups[0]['spare_default_destination'] === '');
        $location = ExportMasterLocation::create(['code' => 'RECOMMEND-A1']);
        $box = $this->boxes($batch)->sole();
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'location_id' => $location->id, 'box_limit' => 3], $this->operator);
        $next = $this->batch([2, 0]);
        $audits = AuditLog::count();
        $dates = $this->boxes($next)->pluck('first_received_at')->all();

        $this->get(route('export.receiving.transactions.show', $next))->assertOk()
            ->assertSee('Tujuan wajib, RECOMMEND-A1')
            ->assertDontSee('data-spare-use-previous', false)
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === 'pallet:'.$location->stockPallet->id
                && $groups[0]['spare_recommendation']['current'] === 1 && $groups[0]['spare_recommendation']['limit'] === 3);
        $this->assertSame($audits, AuditLog::count());
        $this->assertDatabaseCount('export_stock_movements', 1);
        $this->assertEquals($dates, $this->boxes($next)->pluck('first_received_at')->all());
        $this->assertSame(['received', 'received'], $this->boxes($next)->pluck('state')->all());
    }

    public function test_overflow_can_fill_previous_pallet_and_split_the_remainder_with_identical_print_and_confirmation(): void
    {
        $pallet = $this->storedPallet([2, 1], 'RECOMMEND-MIXED', 4);
        // Expired stock still takes physical space, including stock of another part.
        $pallet->boxes()->where('part_setting_id', $this->b->id)->update(['first_received_at' => now()->subMonths(13)]);
        $batch = $this->batch([3, 0]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === ''
                && $groups[0]['spare_recommendation']['current'] === 3 && $groups[0]['spare_recommendation']['limit'] === 4);
        $next = ExportMasterLocation::create(['code' => 'RECOMMEND-REMAINDER']);
        $rows = $this->rows($this->boxes($batch), ['pallet:'.$pallet->id, 'location:'.$next->id, 'location:'.$next->id]);
        $rows[1]['box_limit'] = $rows[2]['box_limit'] = 2;
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode($rows)])
            ->assertOk()->assertViewHas('guideWarning', null)
            ->assertViewHas('printGroups', fn ($groups) => count($groups) === 2
                && count($groups[0]['rows']) === 1 && $groups[0]['load'] === 4 && count($groups[1]['rows']) === 2);
        $payload = ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)];
        $this->post(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->post(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->assertSame(4, $pallet->boxes()->count());
        $this->assertSame(2, $next->stockPallet->boxes()->count());
        $this->assertDatabaseCount('export_stock_movements', 6);
    }

    public function test_operator_can_use_a_new_pallet_and_next_receipt_follows_its_current_location(): void
    {
        $old = $this->storedPallet([1, 0], 'RECOMMEND-OLD', 1);
        $this->travel(1)->seconds();
        $new = $this->storedPallet([1, 0], 'RECOMMEND-NEW', 5);
        $move = app(ExportPalletMoveService::class);
        $moved = ExportMasterLocation::create(['code' => 'RECOMMEND-MOVED']);
        $move->move(['pallet_id' => $new->id, 'location_id' => $moved->id,
            'fingerprint' => $move->inspect($new)['fingerprint'], 'request_token' => (string) Str::uuid()], $this->operator);
        $batch = $this->batch([1, 0]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === 'pallet:'.$new->id
                && $groups[0]['spare_recommendation']['location'] === 'RECOMMEND-MOVED');
        $this->assertSame(1, $old->boxes()->count());
        $this->assertSame(1, $new->boxes()->count());
        $this->travelBack();
    }

    public function test_defaults_share_capacity_across_parts_and_a_full_pallet_is_explained_without_selecting_it(): void
    {
        $pallet = $this->storedPallet([1, 1], 'RECOMMEND-SHARED', 3);
        $batch = $this->batch([1, 1]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === 'pallet:'.$pallet->id
                && $groups[1]['spare_default_destination'] === '' && $groups[1]['spare_recommendation']['current'] === 2);
        $box = $this->boxes($batch)->first();
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'pallet_id' => $pallet->id], $this->operator);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertSee('3/3 box · Penuh')
            ->assertViewHas('groups', fn ($groups) => $groups[1]['spare_default_destination'] === ''
                && $groups[1]['spare_recommendation']['current'] === 3);
    }

    public function test_capacity_is_rechecked_if_another_receipt_fills_the_recommended_pallet(): void
    {
        $pallet = $this->storedPallet([1, 0], 'RECOMMEND-STALE', 2);
        $batch = $this->batch([1, 0]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === 'pallet:'.$pallet->id);
        $other = $this->boxes($this->batch([1, 0]))->sole();
        app(ExportStockReceivingService::class)->place($other->id, ['revision' => $other->revision,
            'request_token' => (string) Str::uuid(), 'pallet_id' => $pallet->id], $this->operator);
        $rows = $this->rows($this->boxes($batch), ['pallet:'.$pallet->id]);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])
            ->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->assertSame('received', $this->boxes($batch)->sole()->state);
        $this->assertDatabaseCount('export_stock_movements', 2);
    }

    public function test_soft_deleted_stock_does_not_create_a_previous_part_recommendation(): void
    {
        $pallet = $this->storedPallet([1, 1], 'RECOMMEND-DELETED', null);
        $pallet->boxes()->where('part_setting_id', $this->a->id)->firstOrFail()->delete();
        $batch = $this->batch([1, 1]);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_recommendation'] === null
                && $groups[1]['spare_default_destination'] === 'pallet:'.$pallet->id
                && $groups[1]['spare_recommendation']['limit'] === null);
    }

    public function test_all_boxes_that_fit_must_use_previous_pallet_through_bulk_single_modal_and_print(): void
    {
        $pallet = $this->storedPallet([2, 1], 'MANDATORY-A1', 5);
        $batch = $this->batch([2, 0]);
        $boxes = $this->boxes($batch);
        $other = ExportMasterLocation::create(['code' => 'MANDATORY-A2']);
        $rows = $this->rows($boxes, ['location:'.$other->id, 'location:'.$other->id]);
        $url = route('export.receiving.place-bulk');
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()->assertSee('Tujuan wajib, MANDATORY-A1')
            ->assertDontSee('data-spare-new-pallet', false)
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_recommendation']['required'] === true);
        $this->postJson($url, ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])
            ->assertUnprocessable()->assertJsonValidationErrors('destination');
        $split = $rows;
        $split[0]['destination'] = 'pallet:'.$pallet->id;
        $this->postJson($url, ['return_batch' => $batch->id, 'placements_json' => json_encode($split)])
            ->assertUnprocessable()->assertJsonValidationErrors('destination');
        // Omitting the receipt context or submitting only one box must not bypass the rule.
        $this->postJson($url, ['placements_json' => json_encode([$rows[0]])])->assertUnprocessable();
        $this->postJson(route('export.receiving.place', $boxes[0]), ['revision' => $boxes[0]->revision,
            'request_token' => (string) Str::uuid(), 'destination' => 'location:'.$other->id])->assertUnprocessable();
        $html = $this->getJson(route('export.stock.show', $boxes[0]))->assertOk()->json('html');
        $this->assertStringContainsString('Tujuan wajib', $html);
        $this->assertStringContainsString('Seluruh 2 box', $html);
        $this->post(route('export.receiving.transactions.print', $batch), ['spare_placements_json' => json_encode($rows)])
            ->assertOk()->assertSee('wajib ditempatkan')->assertDontSee('LOKASI: MANDATORY-A2');
        $this->get(route('export.receiving.transactions.print', $batch))->assertOk()
            ->assertSee('LOKASI: MANDATORY-A1')->assertViewHas('guideWarning', null);
        $this->assertFalse($other->fresh()->is_occupied);
        $this->assertDatabaseCount('export_stock_pallets', 1);
        $valid = $this->rows($boxes, ['pallet:'.$pallet->id, 'pallet:'.$pallet->id]);
        $payload = ['return_batch' => $batch->id, 'placements_json' => json_encode($valid)];
        $this->postJson($url, $payload)->assertRedirect();
        $this->postJson($url, $payload)->assertRedirect();
        $this->assertSame(5, $pallet->boxes()->count());
        $this->assertDatabaseCount('export_stock_movements', 5);
    }

    public function test_overflow_allows_every_box_to_use_a_new_pallet_in_one_atomic_confirmation(): void
    {
        $old = $this->storedPallet([2, 1], 'OVERFLOW-A1', 5);
        $batch = $this->batch([5, 0]);
        $target = ExportMasterLocation::create(['code' => 'OVERFLOW-A2']);
        $rows = $this->rows($this->boxes($batch), array_fill(0, 5, 'location:'.$target->id));
        $rows = array_map(fn ($row) => $row + ['box_limit' => 5], $rows);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertSee('data-spare-new-pallet', false)
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_recommendation']['required'] === false);
        $payload = ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)];
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->assertSame(3, $old->boxes()->count());
        $this->assertSame(5, $target->stockPallet->boxes()->count());
        $this->assertSame('PLT-EXP-002', $target->stockPallet->number);
        $this->assertDatabaseCount('export_stock_movements', 8);
    }

    public function test_subset_of_overflow_receipt_uses_the_entire_pending_part_quantity(): void
    {
        $old = $this->storedPallet([2, 1], 'SUBSET-A1', 5);
        $batch = $this->batch([5, 0]);
        $target = ExportMasterLocation::create(['code' => 'SUBSET-A2']);
        $boxes = $this->boxes($batch)->take(2);
        $rows = $this->rows($boxes, array_fill(0, 2, 'location:'.$target->id));
        $this->postJson(route('export.receiving.place-bulk'), ['placements_json' => json_encode($rows)])->assertRedirect();
        $this->assertSame(3, $old->boxes()->count());
        $this->assertSame(2, $target->stockPallet->boxes()->count());
        $this->assertSame(3, $this->boxes($batch)->where('state', 'received')->count());
    }

    public function test_new_capacity_is_rechecked_before_an_overflow_choice_is_accepted(): void
    {
        $old = $this->storedPallet([2, 1], 'STALE-RULE-A1', 4);
        $batch = $this->batch([2, 0]);
        $target = ExportMasterLocation::create(['code' => 'STALE-RULE-A2']);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_recommendation']['required'] === false);
        $old->update(['box_limit' => 5]);
        $rows = $this->rows($this->boxes($batch), array_fill(0, 2, 'location:'.$target->id));
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $batch->id, 'placements_json' => json_encode($rows)])
            ->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->assertFalse($target->fresh()->is_occupied);
        $this->assertSame(3, $old->boxes()->count());
    }

    public function test_legacy_receipt_with_unlimited_previous_pallet_is_also_mandatory(): void
    {
        $pallet = $this->storedPallet([1, 0], 'LEGACY-RULE-A1', null);
        $service = app(ExportStockReceivingService::class);
        $box = $service->receive(['box_number' => (string) ++$this->sequence, 'part_number' => $this->a->part_number,
            'receive_token' => (string) Str::uuid()], $this->operator);
        $target = ExportMasterLocation::create(['code' => 'LEGACY-RULE-A2']);
        $this->get(route('export.receiving.transactions.legacy', $box))->assertOk()->assertSee('Tujuan wajib');
        $this->postJson(route('export.receiving.place', $box), ['revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'destination' => 'location:'.$target->id])->assertUnprocessable();
        $this->postJson(route('export.receiving.place', $box), ['revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'destination' => 'pallet:'.$pallet->id])->assertRedirect();
        $this->assertSame(2, $pallet->boxes()->count());
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
        $this->get(route('export.receiving.transactions.show', $next))->assertSee('Tujuan wajib, SPARE-ONE')
            ->assertViewHas('groups', fn ($groups) => $groups[0]['spare_default_destination'] === 'pallet:'.$one->stockPallet->id)
            ->assertDontSee('Seluruh box pada transaksi ini sudah ditempatkan.');
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
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups[0]['planned'] !== null
                && ! array_key_exists('spare_recommendation', $groups[0]));
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
