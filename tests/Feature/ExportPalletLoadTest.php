<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportMasterLocation, ExportPartSetting, ExportStockBox, ExportStockPallet, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockReceivingService, ExportStockViewService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportPalletLoadTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ExportPartSetting $part;
    private int $number = 600000;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']); $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'LOAD-PART', 'qty_box' => 10]);
    }

    private function pallet(int $count, ?int $plannedId = null, ?ExportMasterLocation $location = null): ExportStockPallet
    {
        $location ??= ExportMasterLocation::create(['code' => 'LOAD-'.($this->number + 1)]);
        $location->update(['is_occupied' => true]);
        $pallet = ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id,
            'location_code' => $location->code, 'export_planned_pallet_id' => $plannedId, 'created_by' => $this->operator->id]);
        for ($i = 0; $i < $count; $i++) ExportStockBox::create([
            'box_number' => str_pad((string) ++$this->number,8,'0',STR_PAD_LEFT), 'part_setting_id' => $this->part->id,
            'part_number' => $this->part->part_number, 'pcs_quantity' => 10, 'state' => 'stored', 'revision' => 1,
            'receive_token' => (string) Str::uuid(), 'first_received_at' => now()->subDay(), 'received_by' => $this->operator->id,
            'current_pallet_id' => $pallet->id, 'stored_at' => now()->subDay(), 'stored_by' => $this->operator->id,
        ]);
        return $pallet;
    }

    private function batch(int $count)
    {
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($this->operator);
        for ($i = 0; $i < $count; $i++) $batch = $service->scan($batch->id,[
            'box_number' => str_pad((string) ++$this->number,8,'0',STR_PAD_LEFT), 'part_number' => $this->part->part_number,
            'revision' => $batch->revision, 'scan_token' => (string) Str::uuid(),
        ],$this->operator);
        return $service->process($batch->id,$batch->revision,$this->operator);
    }

    public function test_mixed_part_planning_labels_36_and_72_without_blocking_finalization(): void
    {
        $b = ExportPartSetting::create(['part_number' => 'LOAD-B', 'qty_box' => 20]);
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'LOAD-MIX', 'customer_name' => 'Load Customer',
            'delivery_deadline' => '2026-11-15', 'source_type' => 'manual', 'items' => [
                ['part_number' => $this->part->part_number, 'pcs_quantity' => 360], ['part_number' => $b->part_number, 'pcs_quantity' => 1440],
            ]],$this->operator);
        $rows = [];
        foreach ([[10,26],[26,46]] as $i => [$aCount,$bCount]) $rows[] = [
            'master_location_id' => ExportMasterLocation::create(['code' => 'LOAD-MIX-'.$i])->id,
            'items' => [['part_setting_id' => $this->part->id,'box_quantity' => $aCount],['part_setting_id' => $b->id,'box_quantity' => $bCount]],
        ];
        $this->put(route('export.pallet-plans.update',$delivery),['revision' => 0,'delivery_revision' => 1,'action' => 'finalize','pallets' => $rows])->assertRedirect();
        $this->assertSame('final',$delivery->fresh()->palletPlan->status);
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertSee('data-level="warning"',false)
            ->assertSee('data-level="danger"',false)->assertSee('36 box')->assertSee('72 box');
        $this->get(route('export.receiving.index'))->assertOk()->assertSee('Penerimaan Transfer Box Ekspor');
    }

    public function test_spare_bulk_print_and_modal_use_existing_count_plus_incoming_and_remain_read_only(): void
    {
        $yellow = $this->pallet(34); $red = $this->pallet(70); $batch = $this->batch(2); $audits = AuditLog::count();
        $this->get(route('export.receiving.transactions.show',$batch))->assertOk()->assertSee('data-incoming-count="2"',false)
            ->assertSee('data-box-count="34"',false)->assertSee('data-box-count="70"',false);
        foreach ([[$yellow,36],[$red,72]] as [$target,$total]) $this->get(route('export.receiving.transactions.print',[
            'batch' => $batch->id,'spare_destination' => 'pallet:'.$target->id,
        ]))->assertOk()->assertViewHas('printGroups',fn ($groups) => $groups[0]['load'] === $total)->assertSee($total.' box');
        $box = ExportStockBox::whereNull('current_pallet_id')->firstOrFail();
        $response = $this->getJson(route('export.stock.show',$box))->assertOk();
        $this->assertStringContainsString('data-incoming-count="1"',$response->json('html'));
        $this->assertStringContainsString('data-box-count="70"',$response->json('html'));
        $this->assertSame($audits,AuditLog::count());
        $this->assertDatabaseCount('export_stock_movements',0);
        // A red label is a warning, so the real placement to 72 boxes still succeeds.
        $items = $batch->items()->with('stockBox')->get();
        $this->post(route('export.receiving.place-bulk'),['return_batch' => $batch->id,'destination' => 'pallet:'.$red->id,
            'placements_json' => json_encode($items->map(fn ($item) => ['id' => $item->stockBox->id,
                'revision' => $item->stockBox->revision,'request_token' => (string) Str::uuid()])->all()),
        ])->assertRedirect(route('export.receiving.transactions.show',$batch));
        $this->assertSame(72,$red->boxes()->count());
    }

    public function test_planned_destination_includes_stock_from_other_receipts_and_never_counts_a_placed_box_twice(): void
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'LOAD-EXISTING','customer_name' => 'Load Existing',
            'delivery_deadline' => '2026-11-15','source_type' => 'manual','items' => [['part_number' => $this->part->part_number,'pcs_quantity' => 360]]],$this->operator);
        $location = ExportMasterLocation::create(['code' => 'LOAD-PLAN']);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id,['revision' => 0,'delivery_revision' => 1,'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id,'items' => [['part_setting_id' => $this->part->id,'box_quantity' => 36]]]]],$this->operator);
        $pallet = $this->pallet(35,$plan->pallets()->firstOrFail()->id,$location); $batch = $this->batch(1);
        $this->get(route('export.receiving.transactions.show',$batch))->assertOk()->assertSee('data-level="warning"',false)
            ->assertViewHas('groups',fn ($groups) => $groups[0]['load'] === ['current' => 35,'incoming' => 1,'total' => 36]);
        $box = ExportStockBox::whereNull('current_pallet_id')->firstOrFail();
        app(ExportStockReceivingService::class)->place($box->id,['revision' => $box->revision,'request_token' => (string) Str::uuid()],$this->operator);
        $box->refresh()->load(ExportStockViewService::RELATIONS);
        $this->assertSame(['current' => 36,'incoming' => 0,'total' => 36],app(ExportStockViewService::class)->groups(collect([$box]))->first()['load']);
    }
}
