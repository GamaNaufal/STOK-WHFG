<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportMasterLocation, ExportPartSetting, ExportReceivingBatch, ExportStockBox, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportReceivingBatchTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $part;
    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role'=>'export_operator']); $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number'=>'BULK-A','qty_box'=>10]);
    }
    private function batch(): ExportReceivingBatch { return app(ExportReceivingBatchService::class)->start($this->operator); }
    private function scan(ExportReceivingBatch $batch, string $number, array $replace=[])
    {
        return $this->postJson(route('export.receiving.batches.scan',$batch), array_replace([
            'box_number'=>$number,'part_number'=>'BULK-A','revision'=>$batch->fresh()->revision,'scan_token'=>(string)Str::uuid(),
        ],$replace));
    }
    private function process(ExportReceivingBatch $batch)
    {
        return $this->postJson(route('export.receiving.batches.process',$batch),['revision'=>$batch->fresh()->revision]);
    }
    private function plan(int $quantity=2)
    {
        $delivery=app(ExportDeliveryPlanService::class)->save(['plan_code'=>'BULK-PLAN','customer_name'=>'Customer Bulk',
            'delivery_deadline'=>'2026-11-01','source_type'=>'manual','items'=>[['part_number'=>'BULK-A','pcs_quantity'=>$quantity*10]]],$this->operator);
        $loc=ExportMasterLocation::create(['code'=>'BULK-1']);
        app(ExportPalletPlanningService::class)->save($delivery->id,['revision'=>0,'delivery_revision'=>1,'action'=>'finalize',
            'pallets'=>[['master_location_id'=>$loc->id,'items'=>[['part_setting_id'=>$this->part->id,'box_quantity'=>$quantity]]]]],$this->operator);
        return $delivery;
    }
    private function confirmations(): array
    {
        return ExportStockBox::orderBy('id')->get()->map(fn($b)=>['id'=>$b->id,'revision'=>$b->revision,'request_token'=>(string)Str::uuid()])->all();
    }

    public function test_scans_are_persistent_drafts_without_stock_or_location_instructions(): void
    {
        $started=$this->postJson(route('export.receiving.batches.start'))->assertOk()
            ->assertJsonPath('status','draft')->assertJsonPath('revision',1)->json();
        $batch=ExportReceivingBatch::findOrFail($started['id']);
        $this->scan($batch,'00000001',['revision'=>$started['revision']])->assertOk()->assertJsonPath('items.0.pcs_quantity',10);
        $this->scan($batch,'00000002')->assertOk()->assertJsonCount(2,'items');
        $this->get(route('export.receiving.index'))->assertOk()->assertViewHas('state',fn($s)=>count($s['items'])===2)
            ->assertDontSee('Letakkan box ke')->assertSee('Daftar scan');
        foreach(['export_stock_boxes','export_stock_pallets','export_fulfillment_boxes','audit_logs'] as $table)$this->assertDatabaseCount($table,0);
        $this->get(route('export.stock.index'))->assertOk()->assertSee('Belum ada stok ekspor');
    }
    public function test_bad_scans_duplicates_and_stale_tab_are_rejected_without_appending(): void
    {
        $batch=$this->batch();
        $this->scan($batch,'00000001')->assertOk();
        $this->postJson(route('export.receiving.check-box'),['box_number'=>'00000001','batch_id'=>$batch->id])->assertJsonPath('available',false);
        $this->scan($batch,'00000001')->assertUnprocessable()->assertJsonValidationErrors('box_number');
        $this->scan($batch,'ABCDE123')->assertUnprocessable();
        $this->scan($batch,'00000002',['part_number'=>'UNKNOWN'])->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $this->scan($batch,'00000002',['revision'=>1])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame(1,$batch->items()->count()); $this->assertSame(2,$batch->fresh()->revision);
    }
    public function test_scan_retries_are_idempotent_and_deleted_scan_cannot_reappear_from_stale_request(): void
    {
        $batch=$this->batch(); $token=(string)Str::uuid();
        $this->scan($batch,'00000001',['scan_token'=>$token])->assertOk();
        $this->scan($batch,'00000001',['scan_token'=>$token,'revision'=>1])->assertOk()->assertJsonCount(1,'items');
        $this->scan($batch,'00000002',['scan_token'=>$token])->assertUnprocessable()->assertJsonValidationErrors('scan_token');
        $item=$batch->items()->sole();
        $this->deleteJson(route('export.receiving.batches.remove',[$batch,'item'=>$item->id]),['revision'=>2])->assertOk()->assertJsonCount(0,'items');
        $this->scan($batch,'00000001',['scan_token'=>$token,'revision'=>1])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->scan($batch,'00000001')->assertOk();
        $this->deleteJson(route('export.receiving.batches.discard',$batch),['revision'=>4])->assertOk()->assertJsonPath('id',null);
        $this->process($batch)->assertUnprocessable()->assertJsonValidationErrors('batch');
        $this->assertDatabaseCount('export_stock_boxes',0);
    }
    public function test_process_receives_all_then_allocates_and_preserves_server_scan_dates(): void
    {
        $delivery=$this->plan(); $batch=$this->batch();
        $this->travelTo(now()->subDay()); $scannedAt=now()->toDateTimeString();
        foreach(['00000001','00000002','00000003'] as $number)$this->scan($batch,$number,['first_received_at'=>'1990-01-01','pcs_quantity'=>999])->assertOk();
        $this->travelBack();
        $this->process($batch)->assertRedirect(route('export.receiving.transactions.show',$batch));
        $this->assertDatabaseCount('export_stock_boxes',3); $this->assertDatabaseCount('export_fulfillment_boxes',2);
        $this->assertSame($scannedAt,ExportStockBox::first()->first_received_at->toDateTimeString());
        $this->assertSame(10,ExportStockBox::first()->pcs_quantity);
        $this->get(route('export.receiving.transactions.show',$batch))->assertOk()->assertSee('Transaksi penerimaan ekspor')
            ->assertViewHas('groups',fn($g)=>$g->count()===2);
        $audits=AuditLog::count(); $this->process($batch)->assertRedirect();
        $this->assertSame($audits,AuditLog::count()); $this->assertDatabaseCount('export_stock_boxes',3);
        $this->get(route('export.fulfillment.show',$delivery))->assertViewHas('metrics',fn($m)=>$m['fulfilled']===0&&$m['reserved']===2);
    }
    public function test_conflicting_receipt_rolls_back_the_entire_batch(): void
    {
        $batch=$this->batch(); $this->scan($batch,'00000001')->assertOk(); $this->scan($batch,'00000002')->assertOk();
        app(ExportStockReceivingService::class)->receive(['box_number'=>'00000002','part_number'=>'BULK-A','receive_token'=>(string)Str::uuid()],$this->operator);
        $before=AuditLog::count(); $this->process($batch)->assertUnprocessable()->assertJsonValidationErrors('box_number');
        $this->assertDatabaseCount('export_stock_boxes',1); $this->assertDatabaseMissing('export_stock_boxes',['box_number'=>'00000001']);
        $this->assertSame('draft',$batch->fresh()->status); $this->assertSame($before,AuditLog::count());
        $this->assertNull($batch->items()->first()->export_stock_box_id);
    }
    public function test_changed_capacity_requires_rescanning_without_partial_receipt(): void
    {
        $batch=$this->batch(); $this->scan($batch,'00000001')->assertOk(); $this->part->update(['qty_box'=>20]);
        $this->process($batch)->assertUnprocessable()->assertJsonValidationErrors('batch'); $this->assertDatabaseCount('export_stock_boxes',0);
        $item=$batch->items()->sole(); $this->deleteJson(route('export.receiving.batches.remove',[$batch,'item'=>$item->id]),['revision'=>2])->assertOk();
        $this->scan($batch,'00000001')->assertOk()->assertJsonPath('items.0.pcs_quantity',20); $this->process($batch)->assertRedirect();
        $this->assertSame(20,ExportStockBox::sole()->pcs_quantity);
    }
    public function test_bulk_spare_placement_creates_one_pallet_and_retry_does_not_duplicate_movements(): void
    {
        $batch=$this->batch(); foreach(['00000001','00000002'] as $n)$this->scan($batch,$n)->assertOk(); $this->process($batch)->assertRedirect();
        $location=ExportMasterLocation::create(['code'=>'SPARE']);
        $data=['return_batch'=>$batch->id,'destination'=>'location:'.$location->id,'placements_json'=>json_encode($this->confirmations())];
        $this->postJson(route('export.receiving.place-bulk'),$data)->assertRedirect();
        $this->assertDatabaseCount('export_stock_pallets',1); $this->assertDatabaseCount('export_stock_movements',2);
        $this->assertSame(2,ExportStockBox::where('state','stored')->count());
        $this->postJson(route('export.receiving.place-bulk'),$data)->assertRedirect(); $this->assertDatabaseCount('export_stock_movements',2);
    }
    public function test_group_confirmation_updates_fulfillment_atomically_and_rejects_stale_member(): void
    {
        $delivery=$this->plan(); $batch=$this->batch(); foreach(['00000001','00000002'] as $n)$this->scan($batch,$n)->assertOk(); $this->process($batch)->assertRedirect();
        $rows=$this->confirmations(); $bad=$rows; $bad[1]['revision']=1;
        $this->postJson(route('export.receiving.place-bulk'),['placements_json'=>json_encode($bad)])->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_movements',0); $this->assertDatabaseCount('export_stock_pallets',0);
        $this->postJson(route('export.receiving.place-bulk'),['return_batch'=>$batch->id,'placements_json'=>json_encode($rows)])->assertRedirect();
        $this->get(route('export.fulfillment.show',$delivery))->assertViewHas('metrics',fn($m)=>$m['fulfilled']===2&&$m['remaining']===0);
    }
    public function test_other_operators_cannot_change_or_process_each_others_drafts(): void
    {
        $batch=$this->batch(); $this->scan($batch,'00000001')->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'export_operator']));
        $this->getJson(route('export.receiving.batches.state',$batch))->assertNotFound();
        $this->process($batch)->assertNotFound();
        $this->deleteJson(route('export.receiving.batches.discard',$batch),['revision'=>2])->assertNotFound();
        $this->get(route('export.receiving.index'))->assertViewHas('state',fn($s)=>$s['items']===[]);
    }
    public function test_stock_page_is_separate_filters_real_stock_and_uses_existing_permission(): void
    {
        $batch=$this->batch(); $this->scan($batch,'00000001')->assertOk();
        $this->get(route('export.stock.index'))->assertOk()->assertViewHas('boxes',fn($b)=>$b->total()===0);
        $this->process($batch)->assertRedirect();
        $this->get(route('export.stock.index',['status'=>'pending','q'=>'00000001']))->assertOk()->assertViewHas('boxes',fn($b)=>$b->total()===1)
            ->assertDontSee('Antrean penempatan / pemindahan');
        $this->get(route('export.receiving.index'))->assertDontSee('Daftar stok ekspor')
            ->assertSeeInOrder(['Scan box dan part','Daftar scan','Antrean penempatan / pemindahan'])
            ->assertViewHas('transactions',fn($p)=>$p->total()===1);
        RoleFeatureAccess::create(['role'=>'export_operator','permissions'=>[]]);
        $this->get(route('export.stock.index'))->assertForbidden();
        $this->postJson(route('export.receiving.batches.start'))->assertForbidden();
    }
    public function test_batch_results_include_older_fifo_tasks_and_new_surplus_remains_spare(): void
    {
        $old=app(ExportStockReceivingService::class)->receive(['box_number'=>'00000999','part_number'=>'BULK-A','receive_token'=>(string)Str::uuid()],$this->operator);
        $this->plan(); $batch=$this->batch();
        $this->scan($batch,'00000001')->assertOk(); $this->scan($batch,'00000002')->assertOk();
        $this->get(route('export.receiving.index'))->assertDontSee('BULK-PLAN');
        $this->process($batch)->assertRedirect();
        $this->assertNotNull($old->fresh()->fulfillment);
        $this->assertNull(ExportStockBox::where('box_number','00000002')->sole()->fulfillment);
        $this->get(route('export.receiving.transactions.show',$batch))->assertOk()
            ->assertViewHas('groups',fn($groups)=>$groups->sum(fn($group)=>$group['boxes']->count())===2
                && !$groups->contains(fn($group)=>$group['boxes']->contains('id',$old->id)));
        $this->get(route('export.receiving.index'))->assertViewHas('transactions',fn($p)=>$p->first()->kind==='legacy' && (int)$p->first()->transaction_id===$old->id);
    }
    public function test_draft_limit_accepts_large_delivery_batches_and_rejects_more_than_one_thousand(): void
    {
        $batch=$this->batch(); $rows=[];
        for($i=1;$i<=1000;$i++)$rows[]=['export_receiving_batch_id'=>$batch->id,'position'=>$i,
            'box_number'=>str_pad((string)$i,8,'0',STR_PAD_LEFT),'part_setting_id'=>$this->part->id,
            'part_number'=>'BULK-A','pcs_quantity'=>10,'receive_token'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()];
        \Illuminate\Support\Facades\DB::table('export_receiving_batch_items')->insert($rows);
        $this->getJson(route('export.receiving.batches.state',$batch))->assertOk()->assertJsonCount(1000,'items');
        $this->scan($batch,'00010001')->assertUnprocessable()->assertJsonValidationErrors('batch');
        $this->assertDatabaseCount('export_stock_boxes',0);
    }
    public function test_invalid_confirmation_payloads_are_rejected_without_changes(): void
    {
        $this->postJson(route('export.receiving.place-bulk'),['placements_json'=>'{bad'])->assertUnprocessable();
        $row=['id'=>1,'revision'=>1,'request_token'=>(string)Str::uuid()];
        $this->postJson(route('export.receiving.place-bulk'),['placements_json'=>json_encode([$row,$row])])->assertUnprocessable();
        $this->assertDatabaseCount('export_stock_movements',0);
    }
}
