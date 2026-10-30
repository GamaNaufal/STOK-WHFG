<?php

namespace Tests\Feature;

use App\Models\{ExportMasterLocation, ExportPartSetting, ExportReceivingBatch, ExportStockBox, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportReceivingTransactionsTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $part;
    private int $number = 1000;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']); $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'TX-PART', 'qty_box' => 100]);
    }

    private function number(): string { return str_pad((string) ++$this->number, 8, '0', STR_PAD_LEFT); }

    private function batch(int $count): ExportReceivingBatch
    {
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($this->operator);
        for ($i = 0; $i < $count; $i++) $batch = $service->scan($batch->id, [
            'box_number' => $this->number(), 'part_number' => $this->part->part_number,
            'revision' => $batch->revision, 'scan_token' => (string) Str::uuid(),
        ], $this->operator);
        return $service->process($batch->id, $batch->revision, $this->operator);
    }

    private function boxes(ExportReceivingBatch $batch) { return ExportStockBox::whereIn('id', $batch->items()->select('export_stock_box_id'))->orderBy('id')->get(); }

    private function rows($boxes): array
    {
        return $boxes->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision, 'request_token' => (string) Str::uuid()])->all();
    }

    private function place(ExportStockBox $box, int $location): void
    {
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'location_id' => $location], $this->operator);
    }

    public function test_ten_boxes_form_one_transaction_and_same_day_batches_remain_separate(): void
    {
        $first = $this->batch(10); $second = $this->batch(2);
        app(ExportReceivingBatchService::class)->start($this->operator);
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('transactions', fn ($p) =>
            $p->total() === 2 && (int) $p[0]->transaction_id === $first->id && (int) $p[0]->total_boxes === 10 && (int) $p[0]->pending_boxes === 10);
        $this->get(route('export.receiving.transactions.show', $first))->assertOk()
            ->assertViewHas('receipt', fn ($r) => $r['total'] === 10 && $r['pending'] === 10)
            ->assertViewHas('groups', fn ($g) => $g->sum(fn ($group) => $group['boxes']->count()) === 10)
            ->assertDontSee('export-stock-box-modal')->assertSee('Kembali ke antrean');
        $this->get(route('export.receiving.index', ['batch' => $first->id]))->assertRedirect(route('export.receiving.transactions.show', $first));
    }

    public function test_partial_placement_updates_counts_and_finished_transaction_leaves_queue(): void
    {
        $batch = $this->batch(3); $location = ExportMasterLocation::create(['code' => 'TX-SPARE']);
        $boxes = $this->boxes($batch); $this->place($boxes[0], $location->id);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) =>
            $p->total() === 1 && (int) $p[0]->total_boxes === 3 && (int) $p[0]->pending_boxes === 2 && $p[0]->placed_boxes === 1);
        $this->get(route('export.receiving.transactions.show', $batch))->assertViewHas('receipt', fn ($r) => $r['total'] === 3 && $r['pending'] === 2 && $r['placed'] === 1);
        $remaining = $this->boxes($batch)->slice(1)->values();
        $data = ['return_batch' => $batch->id, 'placements_json' => json_encode($this->rows($remaining)), 'destination' => 'pallet:'.$boxes[0]->fresh()->current_pallet_id];
        $this->postJson(route('export.receiving.place-bulk'), $data)->assertRedirect(route('export.receiving.transactions.show', $batch));
        $this->postJson(route('export.receiving.place-bulk'), $data)->assertRedirect();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 0);
        $this->get(route('export.receiving.transactions.show', $batch))->assertSee('Seluruh box pada transaksi ini sudah ditempatkan.');
        $this->assertDatabaseCount('export_stock_movements', 3);
    }

    public function test_legacy_receipts_stay_visible_and_keep_global_fifo_with_batch_receipts(): void
    {
        $this->travelTo(now()->subDay());
        $old = app(ExportStockReceivingService::class)->receive(['box_number' => $this->number(), 'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $this->travelBack(); $batch = $this->batch(2);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) =>
            $p->total() === 2 && $p[0]->kind === 'legacy' && (int) $p[0]->transaction_id === $old->id && (int) $p[0]->total_boxes === 1);
        $this->get(route('export.receiving.transactions.legacy', $old))->assertOk()->assertViewHas('receipt', fn ($r) => $r['total'] === 1);
        $location = ExportMasterLocation::create(['code' => 'TX-OLD']);
        $this->postJson(route('export.receiving.place-bulk'), ['return_box' => $old->id,
            'placements_json' => json_encode($this->rows(collect([$old]))), 'destination' => 'location:'.$location->id])
            ->assertRedirect(route('export.receiving.transactions.legacy', $old));
        $this->get(route('export.receiving.transactions.legacy', $this->boxes($batch)->first()))->assertNotFound();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->total() === 1 && $p[0]->kind === 'batch');
    }

    public function test_new_reservation_reopens_the_original_receipt_for_stored_spare_stock(): void
    {
        $batch = $this->batch(1); $box = $this->boxes($batch)->first(); $firstReceived = $box->first_received_at->toDateTimeString();
        $source = ExportMasterLocation::create(['code' => 'TX-SOURCE']); $this->place($box, $source->id);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->isEmpty());
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'TX-PLAN', 'customer_name' => 'Customer TX',
            'delivery_deadline' => '2026-11-01', 'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 100]]], $this->operator);
        $target = ExportMasterLocation::create(['code' => 'TX-TARGET']);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $target->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]]], $this->operator);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) => $p->isEmpty());
        $this->post(route('export.pallet-plans.acknowledge-stock', ['deliveryPlan' => $delivery->id,
            'notice' => $box->fresh()->fulfillment->allocation_notice_id]), ['revision' => $delivery->palletPlan->revision])->assertRedirect();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions', fn ($p) =>
            $p->total() === 1 && (int) $p[0]->transaction_id === $batch->id && (int) $p[0]->pending_boxes === 1);
        $this->get(route('export.receiving.transactions.show', $batch))->assertSee('Pallet cadangan · TX-SOURCE')->assertSee('Pallet 1 · TX-TARGET');
        $this->assertSame($firstReceived, $box->fresh()->first_received_at->toDateTimeString());
    }

    public function test_processed_receipts_are_shared_but_drafts_and_cross_transaction_confirmations_are_protected(): void
    {
        $first = $this->batch(1); $second = $this->batch(1); $draft = app(ExportReceivingBatchService::class)->start($this->operator);
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->get(route('export.receiving.transactions.show', $first))->assertOk();
        $this->get(route('export.receiving.transactions.show', $draft))->assertNotFound();
        $this->getJson(route('export.receiving.batches.state', $draft))->assertNotFound();
        $location = ExportMasterLocation::create(['code' => 'TX-SHARED']);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $first->id,
            'placements_json' => json_encode($this->rows($this->boxes($second))), 'destination' => 'location:'.$location->id])
            ->assertUnprocessable()->assertJsonValidationErrors('placements');
        $this->assertDatabaseCount('export_stock_movements', 0);
        $this->postJson(route('export.receiving.place-bulk'), ['return_batch' => $first->id,
            'placements_json' => json_encode($this->rows($this->boxes($first))), 'destination' => 'location:'.$location->id])->assertRedirect();
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get(route('export.receiving.transactions.show', $first))->assertForbidden();
    }

    public function test_queue_pagination_counts_transactions_instead_of_boxes_and_preserves_fifo(): void
    {
        for ($i = 0; $i < 21; $i++) $last = $this->batch(1);
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('transactions', fn ($p) =>
            $p->total() === 21 && $p->count() === 20 && str_contains($p->url(2), '#receiving-pending-queue'));
        $this->get(route('export.receiving.index', ['pending_page' => 2]))->assertOk()
            ->assertViewHas('transactions', fn ($p) => $p->count() === 1 && (int) $p[0]->transaction_id === $last->id);
    }
}
