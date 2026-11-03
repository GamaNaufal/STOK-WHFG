<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportMasterLocation;
use App\Models\ExportPartSetting;
use App\Models\ExportPickingIssue;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Models\ExportStockMovement;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportInventoryService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportReceivingBatchService;
use App\Services\ExportReceivingTransactionService;
use App\Services\ExportStockAllocationNoticeService;
use App\Services\ExportStockAllocationService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportRecommendationTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private ExportPartSetting $a;

    private ExportPartSetting $b;

    private int $sequence = 91000000;

    private int $locations = 0;

    private int $deliveries = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->a = ExportPartSetting::create(['part_number' => 'TX-FIFO-A', 'qty_box' => 10]);
        $this->b = ExportPartSetting::create(['part_number' => 'TX-FIFO-B', 'qty_box' => 20]);
    }

    private function receive(?ExportPartSetting $part = null): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(['box_number' => (string) ++$this->sequence,
            'part_number' => ($part ?? $this->a)->part_number, 'receive_token' => (string) Str::uuid()], $this->operator, false);
    }

    private function location(): ExportMasterLocation
    {
        return ExportMasterLocation::create(['code' => 'TX-LOC-'.++$this->locations]);
    }

    private function store($boxes, ?int $sourceId = null): int
    {
        $location = $sourceId ? null : $this->location();
        foreach ($boxes as $box) {
            app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->fresh()->revision,
                'request_token' => (string) Str::uuid()] + ($sourceId ? ['pallet_id' => $sourceId] : ['location_id' => $location->id]), $this->operator);
            $sourceId ??= $box->fresh()->current_pallet_id;
        }

        return $sourceId;
    }

    private function batch(int $count)
    {
        $service = app(ExportReceivingBatchService::class);
        $batch = $service->start($this->operator);
        for ($i = 0; $i < $count; $i++) {
            $batch = $service->scan($batch->id, ['box_number' => (string) ++$this->sequence,
                'part_number' => $this->a->part_number, 'revision' => $batch->revision, 'scan_token' => (string) Str::uuid()], $this->operator);
        }

        return $service->process($batch->id, $batch->revision, $this->operator);
    }

    /** Each element is a destination pallet containing part ID => box count. */
    private function demand(array $pallets): array
    {
        $totals = [];
        foreach ($pallets as $parts) {
            foreach ($parts as $id => $count) {
                $totals[$id] = ($totals[$id] ?? 0) + $count;
            }
        }
        $items = [];
        foreach ($totals as $id => $count) {
            $part = ExportPartSetting::findOrFail($id);
            $items[] = ['part_number' => $part->part_number, 'pcs_quantity' => $count * $part->qty_box];
        }
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'TX-REC-'.++$this->deliveries,
            'customer_name' => 'Customer FIFO', 'delivery_deadline' => '2026-11-20', 'source_type' => 'manual', 'items' => $items], $this->operator);
        $rows = [];
        foreach ($pallets as $parts) {
            $rows[] = ['master_location_id' => $this->location()->id,
                'items' => collect($parts)->map(fn ($count, $id) => ['part_setting_id' => $id, 'box_quantity' => $count])->values()->all()];
        }
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
            'action' => 'finalize', 'pallets' => $rows], $this->operator);

        return [$delivery, $plan, ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole(), $plan->pallets()->get()];
    }

    private function acknowledge($delivery, $plan, $notice): void
    {
        $this->post(route('export.pallet-plans.acknowledge-stock', [$delivery, $notice]), ['revision' => $plan->revision])->assertSessionHasNoErrors();
    }

    private function url($notice, $pallet): string
    {
        return route('export.receiving.transactions.recommendation', ['notice' => $notice->id, 'pallet' => $pallet->id]);
    }

    private function placements($boxes, $notice, $pallet): array
    {
        return ['return_notice' => $notice->id, 'return_pallet' => $pallet->id,
            'placements_json' => json_encode(collect($boxes)->map(fn ($box) => ['id' => $box->id,
                'revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()])->values()->all())];
    }

    public function test_one_transaction_per_destination_combines_sources_parts_and_receipt_origins(): void
    {
        $batch = $this->batch(1);
        $first = $batch->items()->sole()->stockBox;
        $otherPart = $this->receive($this->b);
        $this->store([$first, $otherPart]);
        $second = $this->receive();
        $this->store([$second]);
        $third = $this->receive();
        $this->store([$third]);
        $first->update(['first_received_at' => now()->subDays(3)]);
        $second->update(['first_received_at' => now()->subDays(2)]);
        $third->update(['first_received_at' => now()->subDay()]);
        $dates = ExportStockBox::orderBy('id')->get()->mapWithKeys(fn ($box) => [$box->id => $box->first_received_at->toDateTimeString()])->all();
        $movements = ExportStockMovement::count();
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 2, $this->b->id => 1], [$this->a->id => 1]]);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->isEmpty());
        $this->acknowledge($delivery, $plan, $notice);
        $this->acknowledge($delivery, $plan, $notice);
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('transactions', fn ($p) => $p->total() === 2
            && $p->every(fn ($row) => $row->kind === 'recommendation') && (int) $p->sum('pending_boxes') === 4);
        $this->get($this->url($notice, $pallets[0]))->assertOk()->assertViewHas('receipt', fn ($r) => $r['total'] === 3 && $r['pending'] === 3)
            ->assertSee($first->box_number)->assertSee($second->box_number)->assertSee($otherPart->box_number)->assertDontSee($third->box_number);
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()->assertViewHas('receipt', fn ($r) => $r['pending'] === 0 && $r['redirected'] === 1)
            ->assertSee($this->url($notice, $pallets[0]), false);
        $this->assertSame($dates, ExportStockBox::orderBy('id')->get()->mapWithKeys(fn ($box) => [$box->id => $box->first_received_at->toDateTimeString()])->all());
        $this->assertSame($movements, ExportStockMovement::count());
        $audit = AuditLog::where('type', 'export_allocation_notice')->where('action', 'acknowledged')->sole();
        $this->assertCount(2, $audit->getNewValuesArray()['transactions']);
        $this->get(route('export.picking.index'))->assertOk()->assertViewHas('tasks', fn ($p) => $p->total() === 2);
    }

    public function test_new_receipt_tasks_remain_in_batch_and_recommended_boxes_cannot_use_old_receipt_confirmation(): void
    {
        $batch = $this->batch(2);
        $boxes = $batch->items()->with('stockBox')->get()->map->stockBox;
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 1]]);
        $this->acknowledge($delivery, $plan, $notice);
        $recommended = $boxes->first(fn ($box) => $box->fresh()->fulfillment !== null);
        $spare = $boxes->first(fn ($box) => $box->id !== $recommended->id);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 2
            && (int) $p->firstWhere('kind', 'batch')->pending_boxes === 1 && (int) $p->firstWhere('kind', 'recommendation')->pending_boxes === 1);
        $original = route('export.receiving.transactions.show', $batch);
        $this->get($original)->assertViewHas('receipt', fn ($r) => $r['pending'] === 1 && $r['redirected'] === 1 && $r['placed'] === 0);
        $payload = $this->placements([$recommended], $notice, $pallets[0]);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $batch->id, 'placements_json' => $payload['placements_json']])
            ->assertUnprocessable()->assertJsonValidationErrors('placements');
        $this->get(route('export.receiving.transactions.print', $batch))->assertOk()->assertDontSee($recommended->box_number)->assertSee($spare->box_number);
        $this->get(route('export.receiving.transactions.recommendation-print', ['notice' => $notice->id, 'pallet' => $pallets[0]->id]))
            ->assertOk()->assertSee($recommended->box_number)->assertDontSee($spare->box_number)->assertDontSee('@if')->assertDontSee('@endif');
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect($this->url($notice, $pallets[0]));
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect($this->url($notice, $pallets[0]));
        $this->assertDatabaseCount('export_stock_movements', 1);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 1 && $p->first()->kind === 'batch');
        // New input after finalization keeps its original receipt, with no mandatory picking.
        $newBatch = $this->batch(1);
        $this->assertNull($newBatch->items()->sole()->stockBox->fulfillment);
    }

    public function test_partial_placement_and_replay_update_only_the_selected_recommendation_transaction(): void
    {
        $boxes = collect([$this->receive(), $this->receive(), $this->receive()]);
        $this->store($boxes);
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 3]]);
        $this->acknowledge($delivery, $plan, $notice);
        $payload = $this->placements([$boxes[0]], $notice, $pallets[0]);
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertRedirect();
        $this->get($this->url($notice, $pallets[0]))->assertViewHas('receipt', fn ($r) => $r['total'] === 3 && $r['pending'] === 2 && $r['placed'] === 1);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 1 && (int) $p[0]->pending_boxes === 2 && $p[0]->placed_boxes === 1);
        $this->postJson(route('export.receiving.place-bulk'), $this->placements($boxes->slice(1), $notice, $pallets[0]))->assertRedirect();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->isEmpty());
        $this->assertDatabaseCount('export_stock_movements', 6);
    }

    public function test_recommendation_membership_is_scoped_to_both_wave_and_destination(): void
    {
        $first = $this->receive();
        $second = $this->receive();
        $other = $this->receive($this->b);
        $source = $this->store([$first, $second, $other]);
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 4]]);
        $pallet = $pallets[0];
        $this->acknowledge($delivery, $plan, $notice);
        $third = $this->receive();
        $this->store([$third], $source);
        $this->travel(1)->minutes();
        // Independently acknowledged waves retain their own membership, even for the same target.
        $next = app(ExportInventoryService::class)->transaction(function () use ($plan) {
            $plan->increment('revision');
            $notice = app(ExportStockAllocationNoticeService::class)->create($plan->fresh(), $this->operator);
            app(ExportStockAllocationService::class)->allocate($this->operator, notice: $notice);

            return $notice;
        });
        $this->acknowledge($delivery, $plan->fresh(), $next);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 2 && (int) $p[0]->transaction_id === $next->id);
        $this->get($this->url($notice, $pallet))->assertViewHas('receipt', fn ($r) => $r['total'] === 2)->assertDontSee($third->box_number);
        $this->get($this->url($next, $pallet))->assertViewHas('receipt', fn ($r) => $r['total'] === 1)->assertDontSee($first->box_number);
        $this->get(route('export.picking.index'))->assertViewHas('tasks', fn ($p) => $p->total() === 2 && $p->sum('required_scan_count') === 3);
        $scope = ['pallet' => $pallet->id, 'notice' => $next->id];
        $this->postJson(route('export.picking.seek', $scope), ['box_number' => $first->box_number])->assertOk()->assertJsonPath('success', false)->assertJsonPath('blocked', false);
        $this->assertDatabaseCount('export_picking_issues', 0);
        $this->postJson(route('export.receiving.place-bulk'), $this->placements([$first], $next, $pallet))->assertUnprocessable()->assertJsonValidationErrors('placements');
        $token = (string) Str::uuid();
        $this->postJson(route('export.receiving.picking.scan', ['pallet' => $pallet->id, 'notice' => $notice->id]), ['box_number' => $first->box_number, 'request_token' => $token])->assertOk();
        $this->postJson(route('export.receiving.picking.scan', $scope), ['box_number' => $first->box_number, 'request_token' => $token])->assertUnprocessable();
        $this->assertDatabaseCount('export_picking_issues', 0);
        $this->get(route('export.receiving.picking.show', $scope))->assertOk()->assertSee($third->box_number)->assertDontSee($first->box_number)
            ->assertSee(ExportReceivingTransactionService::code('recommendation', $next->id, $pallet->id));
        $this->postJson(route('export.receiving.picking.scan', $scope), ['box_number' => $first->box_number, 'request_token' => (string) Str::uuid()])->assertStatus(423);
        $issue = ExportPickingIssue::sole();
        $this->assertSame($next->id, $issue->context['allocation_notice_id']);
        $this->actingAs(User::factory()->create(['role' => 'admin_warehouse']))
            ->post(route('delivery.pick.export-issue.approve', $issue), ['revision' => $issue->revision, 'notes' => 'Kembalikan ke asal.'])->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->post(route('export.receiving.picking.return', $issue), ['revision' => $issue->fresh()->revision,
            'request_token' => (string) Str::uuid(), 'placement_confirmed' => 1])->assertRedirect(route('export.receiving.picking.show', $scope));
        $this->assertTrue($issue->fresh()->isClosed());
    }

    public function test_cross_destination_and_foreign_plan_links_are_rejected_and_access_can_be_revoked(): void
    {
        $first = $this->receive();
        $second = $this->receive();
        $this->store([$first, $second]);
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 1], [$this->a->id => 1]]);
        $this->acknowledge($delivery, $plan, $notice);
        $this->postJson(route('export.receiving.place-bulk'), $this->placements([$first], $notice, $pallets[1]))->assertUnprocessable()->assertJsonValidationErrors('placements');
        [, , , $foreign] = $this->demand([[$this->a->id => 1]]);
        $this->get($this->url($notice, $foreign[0]))->assertNotFound();
        $this->get(route('export.picking.show', ['pallet' => $foreign[0]->id, 'notice' => $notice->id]))->assertNotFound();
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get($this->url($notice, $pallets[0]))->assertForbidden();
        $this->postJson(route('export.receiving.place-bulk'), $this->placements([$first], $notice, $pallets[0]))->assertForbidden();
    }

    public function test_mixed_sources_require_scan_but_mono_sources_and_new_input_do_not(): void
    {
        $first = $this->receive();
        $other = $this->receive($this->b);
        $this->store([$first, $other]);
        [$delivery, $plan, $notice, $pallets] = $this->demand([[$this->a->id => 2]]);
        $this->acknowledge($delivery, $plan, $notice);
        $payload = $this->placements([$first], $notice, $pallets[0]);
        $this->postJson(route('export.receiving.place-bulk'), $payload)->assertUnprocessable();
        $scope = ['pallet' => $pallets[0]->id, 'notice' => $notice->id];
        $this->postJson(route('export.receiving.picking.scan', $scope), ['box_number' => $first->box_number, 'request_token' => (string) Str::uuid()])->assertOk()->assertJsonPath('success', true);
        $this->postJson(route('export.receiving.place-bulk'), $this->placements([$first], $notice, $pallets[0]))->assertRedirect();
        $newBatch = $this->batch(1);
        $newBox = $newBatch->items()->sole()->stockBox;
        $this->assertNull($newBox->fulfillment->allocation_notice_id);
        $this->get(route('export.receiving.transactions.show', $newBatch))->assertOk()->assertDontSee('wajib diverifikasi');
        $newData = $this->placements([$newBox], $notice, $pallets[0]);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $newBatch->id, 'placements_json' => $newData['placements_json']])->assertRedirect();
        $this->assertNotNull($newBox->fresh()->fulfillment->placed_at);
    }

    public function test_pagination_counts_destination_transactions_not_individual_boxes(): void
    {
        $batch = $this->batch(42);
        $this->store($batch->items()->with('stockBox')->get()->map->stockBox);
        [$delivery, $plan, $notice] = $this->demand(array_fill(0, 21, [$this->a->id => 2]));
        $this->acknowledge($delivery, $plan, $notice);
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('transactions', fn ($p) => $p->total() === 21 && $p->count() === 20
            && $p->every(fn ($row) => $row->kind === 'recommendation' && (int) $row->total_boxes === 2));
        $this->get(route('export.receiving.index', ['pending_page' => 2]))->assertOk()->assertViewHas('transactions', fn ($p) => $p->count() === 1);
        $this->get(route('export.picking.index'))->assertOk()->assertViewHas('tasks', fn ($p) => $p->total() === 21);
    }
}
