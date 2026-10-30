<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportMasterLocation, ExportPartSetting, ExportStockAllocationNotice, ExportStockBox, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockAllocationNoticeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportStockAllocationNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ExportPartSetting $part;
    private int $number = 400000;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']); $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'FIFO-NOTICE','qty_box' => 100]);
    }

    private function batch(int $count)
    {
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($this->operator);
        for ($i=0;$i<$count;$i++) $batch = $service->scan($batch->id,['box_number' => str_pad((string) ++$this->number,8,'0',STR_PAD_LEFT),
            'part_number' => $this->part->part_number,'revision' => $batch->revision,'scan_token' => (string) Str::uuid()],$this->operator);
        return $service->process($batch->id,$batch->revision,$this->operator);
    }

    private function demand(int $count = 2, bool $final = true)
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'FIFO-'.(++$this->number),'customer_name' => 'Customer FIFO',
            'delivery_deadline' => '2026-11-20','source_type' => 'manual','items' => [['part_number' => $this->part->part_number,'pcs_quantity' => $count*100]]],$this->operator);
        $location = ExportMasterLocation::create(['code' => 'FIFO-TARGET-'.$delivery->id]);
        $planning = app(ExportPalletPlanningService::class)->save($delivery->id,['revision' => 0,'delivery_revision' => 1,'action' => $final?'finalize':'draft',
            'pallets' => [['master_location_id' => $location->id,'items' => [['part_setting_id' => $this->part->id,'box_quantity' => $count]]]]],$this->operator);
        return [$delivery,$planning];
    }

    private function ackUrl($delivery,$notice): string
    {
        return route('export.pallet-plans.acknowledge-stock',['deliveryPlan' => $delivery->id,'notice' => $notice->id]);
    }

    public function test_oldest_stock_is_reserved_but_only_enters_queue_after_ok_without_physical_movement(): void
    {
        $batch = $this->batch(3); $boxes = $batch->items()->with('stockBox')->get()->map->stockBox;
        foreach ([1,3,2] as $i=>$days) $boxes[$i]->update(['first_received_at' => now()->subDays($days)]);
        $source = ExportMasterLocation::create(['code' => 'FIFO-SOURCE']);
        $rows = fn ($set) => $set->map(fn ($box) => ['id' => $box->id,'revision' => $box->fresh()->revision,'request_token' => (string) Str::uuid()])->all();
        $this->post(route('export.receiving.place-bulk'),['return_batch' => $batch->id,'destination' => 'location:'.$source->id,
            'placements_json' => json_encode($rows($boxes))])->assertRedirect();
        $sourceId = $boxes[0]->fresh()->current_pallet_id;
        [$delivery,$plan] = $this->demand(); $notice = ExportStockAllocationNotice::sole();
        $this->assertSame([$boxes[1]->id,$boxes[2]->id],$notice->boxes()->orderBy('first_received_at')->pluck('export_stock_box_id')->all());
        $this->assertNull($boxes[0]->fresh()->fulfillment);
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertSee('Stok FIFO untuk rencana pallet')
            ->assertSee('FIFO-SOURCE')->assertSee('Oke, masukkan ke antrean')->assertSeeInOrder([$boxes[1]->box_number,$boxes[2]->box_number])
            ->assertDontSee('Tinjau nanti')->assertDontSee('Tutup tinjauan')->assertSee('data-bs-backdrop="static"',false)
            ->assertSee('data-bs-keyboard="false"',false)->assertViewHas('stockReviewRequired',true);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->isEmpty());
        $this->get(route('export.receiving.transactions.show',$batch))->assertViewHas('groups',fn ($g) =>
            $g->sum(fn ($group) => $group['waiting']->count()) === 2 && $g->sum(fn ($group) => count($group['placements'])) === 0);
        $this->postJson(route('export.receiving.place-bulk'),['return_batch' => $batch->id,
            'placements_json' => json_encode($rows(collect([$boxes[1]])))])->assertUnprocessable()->assertJsonValidationErrors('allocation');
        $this->assertDatabaseCount('export_stock_movements',3);
        $audits = AuditLog::count();
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect(route('export.receiving.index').'#receiving-pending-queue');
        $this->assertNotNull($notice->fresh()->acknowledged_at);
        $this->assertSame($sourceId,$boxes[1]->fresh()->current_pallet_id);
        $this->assertDatabaseCount('export_stock_movements',3); $this->assertSame($audits+1,AuditLog::count());
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect();
        $this->assertSame($audits+1,AuditLog::count());
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 1 && (int) $p[0]->transaction_id === $batch->id
            && (int) $p[0]->pending_boxes === 2 && (int) $p[0]->placed_boxes === 1);
        $this->post(route('export.receiving.place-bulk'),['return_batch' => $batch->id,
            'placements_json' => json_encode($rows(collect([$boxes[1],$boxes[2]])))])->assertRedirect();
        $this->assertDatabaseCount('export_stock_movements',5);
        $this->assertNotNull($boxes[1]->fresh()->fulfillment->placed_at);
        $audits = AuditLog::count();
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertSee('Tinjau alokasi FIFO')
            ->assertViewHas('stockNotice',fn ($n) => $n->id === $notice->id && $n->acknowledged_at !== null)
            ->assertViewHas('stockReviewOpen',false)->assertViewHas('stockAssignments',null);
        $this->get(route('export.pallet-plans.show',['deliveryPlan' => $delivery->id,'review_stock' => 1]))->assertOk()
            ->assertSee('Sudah ditempatkan')->assertSee('Tutup')->assertDontSee('action="'.$this->ackUrl($delivery,$notice).'"',false)
            ->assertViewHas('stockReviewRequired',false)->assertViewHas('stockAssignments',fn ($p) => $p->total() === 2);
        $this->assertSame($audits,AuditLog::count()); $this->assertDatabaseCount('export_stock_movements',5);
    }

    public function test_drafts_do_not_allocate_and_empty_review_does_not_hold_future_receipts(): void
    {
        [$delivery,$plan] = $this->demand(1,false);
        $this->assertDatabaseCount('export_stock_allocation_notices',0);
        $service = app(ExportPalletPlanningService::class);
        $plan = $service->save($delivery->id,['revision' => $plan->revision,'delivery_revision' => 1,'action' => 'finalize','pallets' => $service->rows($plan)],$this->operator);
        $notice = ExportStockAllocationNotice::sole();
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertSee('Belum ada stok yang dapat dialokasikan')
            ->assertViewHas('stockAssignments',fn ($p) => $p->total() === 0);
        $batch = $this->batch(1); $box = $batch->items()->first()->stockBox;
        $this->assertNull($box->fulfillment->allocation_notice_id);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 1);
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect();
        $this->assertDatabaseCount('export_stock_movements',0);
    }

    public function test_review_survives_refresh_and_pagination_without_acknowledging_or_losing_rows(): void
    {
        for ($i=0;$i<101;$i++) ExportStockBox::create(['box_number' => str_pad((string) ++$this->number,8,'0',STR_PAD_LEFT),
            'part_setting_id' => $this->part->id,'part_number' => $this->part->part_number,'pcs_quantity' => 100,'state' => 'received','revision' => 1,
            'receive_token' => (string) Str::uuid(),'first_received_at' => now()->subDay(),'received_by' => $this->operator->id]);
        [$delivery,$plan] = $this->demand(101); $notice = ExportStockAllocationNotice::sole(); $audits = AuditLog::count();
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertViewHas('stockAssignments',fn ($p) => $p->total() === 101 && $p->count() === 100);
        $this->get(route('export.pallet-plans.show',['deliveryPlan' => $delivery->id,'stock_page' => 2]))->assertOk()
            ->assertViewHas('stockAssignments',fn ($p) => $p->total() === 101 && $p->count() === 1 && $p->currentPage() === 2);
        $this->assertNull($notice->fresh()->acknowledged_at); $this->assertSame($audits,AuditLog::count());
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 0);
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 101);
        $audits = AuditLog::count();
        $this->get(route('export.pallet-plans.show',['deliveryPlan' => $delivery->id,'review_stock' => 1,'stock_page' => 2]))->assertOk()
            ->assertViewHas('stockReviewRequired',false)->assertViewHas('stockAssignments',fn ($p) => $p->total() === 101 && $p->count() === 1)
            ->assertDontSee('action="'.$this->ackUrl($delivery,$notice).'"',false);
        $this->assertSame($audits,AuditLog::count());
    }

    public function test_foreign_stale_and_revoked_acknowledgments_are_rejected_and_replay_keeps_first_actor(): void
    {
        [$delivery,$plan] = $this->demand(1); $notice = ExportStockAllocationNotice::sole();
        [$other] = $this->demand(1);
        $this->postJson($this->ackUrl($other,$notice),['revision' => $plan->revision])->assertNotFound();
        $this->postJson($this->ackUrl($delivery,$notice),['revision' => $plan->revision+1])->assertUnprocessable();
        $access = RoleFeatureAccess::create(['role' => 'export_operator','permissions' => ['export_stock_input']]);
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertForbidden();
        $this->assertNull($notice->fresh()->acknowledged_at);
        $access->update(['permissions' => ['export_pallet_plans']]);
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect(route('export.pallet-plans.show',$delivery));
        $audits = AuditLog::count(); $next = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($next)->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect();
        $this->assertSame($this->operator->id,(int) $notice->fresh()->acknowledged_by); $this->assertSame($audits,AuditLog::count());
    }

    public function test_existing_reservations_without_a_notice_keep_their_original_queue_behavior(): void
    {
        $batch = $this->batch(1); [$delivery] = $this->demand(1);
        $box = $batch->items()->first()->stockBox; $box->fulfillment->update(['allocation_notice_id' => null]);
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 1);
        $this->get(route('export.receiving.transactions.show',$batch))->assertViewHas('groups',fn ($g) => $g[0]['actionable']->count() === 1 && $g[0]['waiting']->isEmpty());
        $this->assertFalse($box->fresh()->fulfillment->awaitsQueueAcknowledgment());
    }

    public function test_mixed_receipts_separate_ready_waiting_and_placed_counts_and_print_marks_held_boxes(): void
    {
        $batch = $this->batch(2); [$delivery,$plan] = $this->demand(1); $notice = ExportStockAllocationNotice::sole();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => $p->total() === 1 && (int) $p[0]->total_boxes === 2
            && (int) $p[0]->pending_boxes === 1 && (int) $p[0]->waiting_boxes === 1 && (int) $p[0]->placed_boxes === 0);
        $this->get(route('export.receiving.transactions.print',$batch))->assertOk()->assertSee('Menunggu Oke FIFO');
        $this->get(route('export.receiving.transactions.show',$batch))->assertOk()->assertSee('Buka tinjauan perencanaan')
            ->assertViewHas('groups',fn ($groups) => $groups->sum(fn ($g) => $g['actionable']->count()) === 1 && $groups->sum(fn ($g) => $g['waiting']->count()) === 1);
        $this->post($this->ackUrl($delivery,$notice),['revision' => $plan->revision])->assertRedirect();
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn ($p) => (int) $p[0]->pending_boxes === 2
            && (int) $p[0]->waiting_boxes === 0 && (int) $p[0]->placed_boxes === 0);
    }
}
