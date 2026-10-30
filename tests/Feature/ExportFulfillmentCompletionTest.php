<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportDeliveryPlan, ExportMasterLocation, ExportPartSetting, ExportStockAllocationNotice, ExportStockBox, ExportStockPallet, MasterLocation, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportFulfillmentCompletionService, ExportPalletPlanningService, ExportStockAllocationNoticeService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportFulfillmentCompletionTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $part;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'READY-A', 'qty_box' => 10]);
    }

    private function receive(?ExportPartSetting $part = null): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(['box_number' => str_pad((string) ++$this->sequence,8,'0',STR_PAD_LEFT),
            'part_number' => ($part ?? $this->part)->part_number, 'receive_token' => (string) Str::uuid()], $this->operator,
            receivedAt: now()->subDays(20 - $this->sequence));
    }

    private function place(ExportStockBox $box, array $destination = []): void
    {
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()] + $destination, $this->operator);
    }

    private function plan(array $counts = [3], bool $fill = true): ExportDeliveryPlan
    {
        $code = 'READY-'.ExportDeliveryPlan::count();
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => 'Customer Ready',
            'delivery_deadline' => '2026-11-20', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => array_sum($counts)*10]]], $this->operator);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => array_map(fn ($count,$index) => ['master_location_id' => ExportMasterLocation::create(['code'=>$code.'-'.$index])->id,
                'items' => [['part_setting_id'=>$this->part->id,'box_quantity'=>$count]]], $counts,array_keys($counts))], $this->operator);
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id,$notice->id,$plan->revision,$this->operator);
        if ($fill) for ($i=0; $i<array_sum($counts); $i++) $this->place($this->receive());
        return $delivery->fresh(['palletPlan.pallets.items']);
    }

    private function versions($pallet): array
    {
        $pallet->refresh()->load('palletPlan');
        return ['revision' => $pallet->palletPlan->revision, 'fulfillment_revision' => $pallet->fulfillment_revision, 'request_token' => (string) Str::uuid()];
    }

    public function test_partially_filled_pallet_can_reduce_below_current_stock_and_returns_newest_only(): void
    {
        $delivery=$this->plan([10],false);$pallet=$delivery->palletPlan->pallets->sole();
        for($i=0;$i<4;$i++)$this->place($this->receive());
        $this->get(route('export.fulfillment.show',$delivery))->assertSee('Revisi pallet 1');
        $this->get(route('export.fulfillment.revise',[$delivery,$pallet]))->assertOk();
        $data=$this->reduction($pallet,2);
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data)->assertSessionHasNoErrors();
        $this->assertSame(2,$delivery->fresh()->total_boxes);$this->assertSame(4,ExportStockBox::count());
        $this->assertSame(['00000001','00000002'],$pallet->fulfillmentBoxes()->orderBy('box_number')->pluck('box_number')->all());
        $audit=AuditLog::where('type','export_fulfillment')->where('action','reduced')->sole()->getNewValuesArray();
        $this->assertSame(2,$audit['returned_box_quantity']);$this->assertSame(20,$audit['returned_pcs_quantity']);
        $filters=app(\App\Services\WarehouseReportService::class)->filters(\Illuminate\Http\Request::create('/','GET',['scope'=>'export','kind'=>'returns']));
        $report=app(\App\Services\WarehouseReportService::class)->query($filters)->first();
        $this->assertSame(2,(int)$report->box_quantity);$this->assertSame(20,(int)$report->pcs_quantity);
    }

    public function test_unfilled_quantity_can_be_reduced_without_destination_or_fictitious_return(): void
    {
        $delivery=$this->plan([10],false);$pallet=$delivery->palletPlan->pallets->sole();
        $this->place($this->receive());$data=$this->reduction($pallet,6);unset($data['destination'],$data['return_confirmed']);
        $this->post(route('export.fulfillment.revise',[$delivery,$pallet]),$data)->assertOk()->assertSee('Tidak ada pengembalian fisik');
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data)->assertSessionHasNoErrors();
        $this->assertSame(6,$delivery->fresh()->total_boxes);$this->assertSame(1,$pallet->fulfillmentBoxes()->count());
        $this->assertSame(1,\App\Models\ExportStockMovement::count());
        $reports=app(\App\Services\WarehouseReportService::class);
        $row=$reports->query($reports->filters(\Illuminate\Http\Request::create('/','GET',['scope'=>'export','kind'=>'returns'])))->sole();
        $this->assertSame(0,(int)$row->box_quantity);$this->assertSame('revised',$row->status);
    }

    public function test_untouched_reservations_release_in_place_preserve_fifo_and_invalidate_old_placement(): void
    {
        $box=$this->receive();$location=ExportMasterLocation::create(['code'=>'SPARE-RELEASE']);$this->place($box,['location_id'=>$location->id]);
        $date=$box->first_received_at;$source=$box->fresh()->current_pallet_id;
        $delivery=$this->plan([2],false);$pallet=$delivery->palletPlan->pallets->sole();$stale=$box->fresh()->revision;
        $data=$this->reduction($pallet,0);unset($data['destination'],$data['return_confirmed']);$url=route('export.fulfillment.reduce',[$delivery,$pallet]);
        $this->post($url,$data)->assertSessionHasNoErrors();$this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertSame('cancelled',ExportDeliveryPlan::withTrashed()->find($delivery->id)->status);
        $this->assertNull($box->fresh()->fulfillment);$this->assertSame($source,$box->fresh()->current_pallet_id);
        $this->assertTrue($date->equalTo($box->fresh()->first_received_at));$this->assertTrue($location->fresh()->is_occupied);
        $this->postJson(route('export.receiving.place',$box),['revision'=>$stale,'request_token'=>(string)Str::uuid(),'pallet_id'=>$source])->assertUnprocessable();
        $this->assertDatabaseCount('export_pallet_location_reservations',0);
        $log=AuditLog::where('type','export_fulfillment')->where('action','reduced')->sole();
        $presented=\App\Support\AuditLogPresenter::present($log);
        $released=collect($presented['tables'])->first(fn($table)=>str_starts_with($table['title'],'Reservasi dilepas'));
        $this->assertNotNull($released);$this->assertSame($box->box_number,$released['rows'][0][0]);
    }

    public function test_removing_one_whole_pallet_preserves_other_ready_pallet_and_its_number(): void
    {
        $delivery=$this->plan([1,2]);$a=$delivery->palletPlan->pallets[0];$b=$delivery->palletPlan->pallets[1];$this->ready($delivery,$b)->assertSessionHasNoErrors();
        $this->post(route('export.fulfillment.reduce',[$delivery,$a]),$this->reduction($a,0))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('export_planned_pallets',['id'=>$a->id]);$this->assertSame(2,$b->fresh()->position);
        $this->assertNotNull($b->fresh()->ready_at);$this->assertSame(2,$delivery->fresh()->total_boxes);
        $this->assertFalse(ExportMasterLocation::find($a->master_location_id)->is_occupied);
    }

    private function cancellationData($delivery): array
    {
        $preview=app(ExportFulfillmentCompletionService::class)->cancellation($delivery->fresh());
        return ['revision'=>$delivery->palletPlan->revision,'request_token'=>(string)Str::uuid(),'fingerprint'=>$preview['fingerprint'],
            'reason'=>'Customer membatalkan permintaan.','return_confirmed'=>1,
            'destination'=>'location:'.ExportMasterLocation::create(['code'=>'CANCEL-RETURN-'.$this->sequence])->id];
    }

    public function test_cancellation_returns_multiple_pallets_to_one_spare_releases_all_and_is_idempotent(): void
    {
        $delivery=$this->plan([2,2]);$dates=ExportStockBox::pluck('first_received_at','id')->map->toDateTimeString()->all();
        $this->get(route('export.fulfillment.cancel-form',$delivery))->assertOk()->assertSee('Konfirmasi pembatalan permintaan');
        $data=$this->cancellationData($delivery);$url=route('export.fulfillment.cancel',$delivery->id);
        $this->post($url,array_replace($data,['return_confirmed'=>0]))->assertSessionHasErrors('return_confirmed');
        $this->post($url,$data)->assertSessionHasNoErrors();$this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertSame(4,ExportStockBox::count());$this->assertSame(0,\App\Models\ExportFulfillmentBox::count());
        $this->assertDatabaseCount('export_pallet_location_reservations',0);$this->assertDatabaseCount('export_planned_pallets',0);
        $this->assertSame(1,ExportStockPallet::whereNull('retired_at')->count());
        $this->assertSame($dates,ExportStockBox::pluck('first_received_at','id')->map->toDateTimeString()->all());
        $this->assertSame('cancelled',ExportDeliveryPlan::withTrashed()->find($delivery->id)->status);
        $this->assertNull($delivery->palletPlan->fresh()->completed_at);
        $this->assertSame(1,AuditLog::where('type','export_fulfillment')->where('action','cancelled')->count());
    }

    public function test_cancellation_without_stock_needs_no_return_and_new_receipt_invalidates_old_preview(): void
    {
        $delivery=$this->plan([3],false);$data=$this->cancellationData($delivery);unset($data['destination'],$data['return_confirmed']);
        $this->receive();$url=route('export.fulfillment.cancel',$delivery->id);
        $this->post($url,$data)->assertSessionHasErrors('fulfillment');
        $this->assertNotNull($delivery->fresh());$this->assertSame(1,\App\Models\ExportFulfillmentBox::count());
        $data=$this->cancellationData($delivery);unset($data['destination'],$data['return_confirmed']);
        $this->post($url,$data)->assertSessionHasNoErrors();$this->assertNull(ExportDeliveryPlan::find($delivery->id));
        $this->assertSame(1,ExportStockBox::count());$this->assertSame(0,\App\Models\ExportStockMovement::count());
    }

    public function test_cancellation_cannot_return_to_its_own_pallet_or_bypass_permissions(): void
    {
        $delivery=$this->plan();$data=$this->cancellationData($delivery);$physical=ExportStockPallet::whereNull('retired_at')->sole();
        $url=route('export.fulfillment.cancel',$delivery->id);
        $this->post($url,array_replace($data,['destination'=>'pallet:'.$physical->id]))->assertSessionHasErrors('fulfillment');
        $this->assertSame(3,\App\Models\ExportFulfillmentBox::count());
        $this->actingAs(User::factory()->create(['role'=>'warehouse_operator']))->post($url,$data)->assertForbidden();
        $this->assertNotNull($delivery->fresh());
    }

    public function test_cancellation_audit_failure_rolls_back_every_return_and_reservation_release(): void
    {
        $delivery=$this->plan([1,1]);$data=$this->cancellationData($delivery);
        AuditLog::creating(function($log){if($log->type==='export_fulfillment'&&$log->action==='cancelled')throw new \RuntimeException('Cancel audit failed');});
        try {app(ExportFulfillmentCompletionService::class)->cancel($delivery->id,$data,$this->operator);$this->fail('Audit must persist.');}
        catch(\RuntimeException $e){$this->assertSame('Cancel audit failed',$e->getMessage());}
        $this->assertNotNull($delivery->fresh());$this->assertSame(2,\App\Models\ExportFulfillmentBox::count());
        $this->assertDatabaseCount('export_pallet_location_reservations',2);$this->assertDatabaseCount('export_planned_pallets',2);
        $this->assertDatabaseCount('export_fulfillment_actions',0);
    }

    private function ready($delivery,$pallet,array $extra=[])
    {
        return $this->post(route('export.fulfillment.ready',[$delivery,$pallet]), $extra + $this->versions($pallet) + ['location_id'=>$pallet->master_location_id,'placement_confirmed'=>1]);
    }

    private function reduction($pallet,int $remaining=2,array $extra=[]): array
    {
        $quantities = [$pallet->items()->sole()->id => $remaining];
        $preview = app(ExportFulfillmentCompletionService::class)->reduction($pallet->fresh(),$quantities);
        return $extra + $this->versions($pallet) + ['quantities'=>$quantities,'fingerprint'=>$preview['fingerprint'],
            'return_confirmed'=>1,'destination'=>'location:'.ExportMasterLocation::create(['code'=>'RETURN-'.$this->sequence])->id];
    }

    public function test_ready_popup_requires_complete_physical_contents_and_explicit_location_confirmation(): void
    {
        $delivery=$this->plan(); $pallet=$delivery->palletPlan->pallets->first();
        $this->get(route('export.fulfillment.show',$delivery))->assertOk()->assertSee('Konfirmasi Pallet 1 siap');
        $this->get(route('export.fulfillment.ready-form',[$delivery,$pallet]))->assertOk()->assertSee('READY-A')->assertSee('lokasi semula');
        $this->ready($delivery,$pallet,['placement_confirmed'=>0])->assertSessionHasErrors('placement_confirmed');
        $data=$this->versions($pallet)+['location_id'=>$pallet->master_location_id,'placement_confirmed'=>1];
        $this->post(route('export.fulfillment.ready',[$delivery,$pallet]),$data)->assertSessionHasNoErrors();
        $this->post(route('export.fulfillment.ready',[$delivery,$pallet]),$data)->assertSessionHasNoErrors();
        $this->assertNotNull($pallet->fresh()->ready_at);
        $this->assertSame(3,ExportStockBox::count());
        $this->assertSame(1,AuditLog::where('type','export_fulfillment')->where('action','ready')->count());
        $this->get(route('export.fulfillment.show',$delivery))->assertSee('1 / 1 pallet siap');
    }

    public function test_ready_can_move_whole_pallet_to_empty_export_location_and_preserve_fifo(): void
    {
        $delivery=$this->plan(); $pallet=$delivery->palletPlan->pallets->first();
        $original=$pallet->master_location_id; $times=ExportStockBox::pluck('first_received_at','id')->map->toDateTimeString()->all();
        $location=ExportMasterLocation::create(['code'=>'READY-NEW']);
        $this->ready($delivery,$pallet,['location_id'=>$location->id])->assertSessionHasNoErrors();
        $this->assertFalse(ExportMasterLocation::find($original)->is_occupied);
        $this->assertTrue($location->fresh()->is_occupied);
        $this->assertSame($location->id,$pallet->fresh()->master_location_id);
        $this->assertSame($times,ExportStockBox::pluck('first_received_at','id')->map->toDateTimeString()->all());
        $this->get(route('export.fulfillment.show',$delivery))->assertViewHas('metrics',fn($m)=>$m['fulfilled']===3 && $m['invalid']===0);
        $this->assertDatabaseHas('export_pallet_location_reservations',['export_planned_pallet_id'=>$pallet->id,'master_location_id'=>$location->id]);
    }

    public function test_ready_rejects_incomplete_surplus_occupied_reserved_and_domestic_locations(): void
    {
        $delivery=$this->plan(fill:false);$pallet=$delivery->palletPlan->pallets->first();
        $this->ready($delivery,$pallet)->assertSessionHasErrors('fulfillment');
        for($i=0;$i<3;$i++)$this->place($this->receive());
        $domestic=MasterLocation::create(['code'=>'DOMESTIC-ONLY']);
        $this->ready($delivery,$pallet,['location_id'=>$domestic->id])->assertSessionHasErrors('fulfillment');
        $other=$this->plan();$occupied=$other->palletPlan->pallets->first()->master_location_id;
        $this->ready($delivery,$pallet,['location_id'=>$occupied])->assertSessionHasErrors('fulfillment');
        $spare=$this->receive();$physical=ExportStockPallet::where('export_planned_pallet_id',$pallet->id)->sole();
        $this->place($spare,['pallet_id'=>$physical->id]);
        $this->ready($delivery,$pallet)->assertSessionHasErrors('fulfillment');
        $this->assertNull($pallet->fresh()->ready_at);
    }

    public function test_ready_pallet_rejects_incoming_spare_and_outgoing_box_through_existing_placement_endpoint(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$this->ready($delivery,$pallet)->assertSessionHasNoErrors();
        $spare=$this->receive();$physical=ExportStockPallet::where('export_planned_pallet_id',$pallet->id)->sole();
        $this->postJson(route('export.receiving.place',$spare),['revision'=>$spare->revision,'request_token'=>(string)Str::uuid(),'pallet_id'=>$physical->id])->assertUnprocessable();
        $existing=ExportStockBox::where('current_pallet_id',$physical->id)->first();
        $this->postJson(route('export.receiving.place',$existing),['revision'=>$existing->revision,'request_token'=>(string)Str::uuid()])->assertUnprocessable();
        $this->assertSame(3,$physical->boxes()->count());
    }

    public function test_revision_returns_newest_box_to_spare_and_keeps_oldest_for_fifo_shipment(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$this->ready($delivery,$pallet)->assertSessionHasNoErrors();
        $newest=ExportStockBox::orderByDesc('first_received_at')->first();$date=$newest->first_received_at;
        $oldest=ExportStockBox::orderBy('first_received_at')->first();
        $data=$this->reduction($pallet);
        $this->post(route('export.fulfillment.revise',[$delivery,$pallet]),$data)->assertOk()->assertSee($newest->box_number)->assertSee('Pilih pallet existing atau lokasi kosong');
        $this->assertSame(3,$delivery->fresh()->total_boxes);
        $url=route('export.fulfillment.reduce',[$delivery,$pallet]);
        $this->post($url,$data)->assertSessionHasNoErrors();$this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertNull($pallet->fresh()->ready_at);
        $this->assertNull($newest->fresh()->fulfillment);
        $this->assertNotNull($oldest->fresh()->fulfillment);
        $this->assertTrue($date->equalTo($newest->fresh()->first_received_at));
        $this->assertStringStartsWith('RETURN-',$newest->fresh()->currentPallet->location_code);
        $this->assertSame(2,$delivery->fresh()->total_boxes);$this->assertSame(20,$delivery->fresh()->total_pcs);
        $this->assertSame(2,$pallet->items()->sole()->box_quantity);$this->assertSame(3,ExportStockBox::count());
        $this->assertSame(1,AuditLog::where('type','export_fulfillment')->where('action','reduced')->count());
        $this->ready($delivery->fresh(),$pallet)->assertSessionHasNoErrors();
    }

    public function test_revision_requires_return_confirmation_destination_latest_fingerprint_and_revisions(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$data=$this->reduction($pallet);$url=route('export.fulfillment.reduce',[$delivery,$pallet]);
        $this->post($url,array_replace($data,['destination'=>'']))->assertSessionHasErrors('destination');
        $this->post($url,array_replace($data,['return_confirmed'=>0]))->assertSessionHasErrors('return_confirmed');
        $this->post($url,array_replace($data,['fingerprint'=>str_repeat('a',64)]))->assertSessionHasErrors('fulfillment');
        $this->post($url,array_replace($data,['fulfillment_revision'=>99]))->assertSessionHasErrors('fulfillment');
        $this->assertSame(3,$delivery->fresh()->total_boxes);$this->assertDatabaseCount('export_fulfillment_actions',0);
    }

    public function test_returns_can_choose_existing_pallet_but_not_source_or_ready_pallet(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$physical=ExportStockPallet::where('export_planned_pallet_id',$pallet->id)->sole();
        $data=$this->reduction($pallet,extra:['destination'=>'pallet:'.$physical->id]);
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data)->assertSessionHasErrors('fulfillment');
        $spare=$this->receive();$loc=ExportMasterLocation::create(['code'=>'SPARE-TARGET']);$this->place($spare,['location_id'=>$loc->id]);
        $target=$spare->fresh()->current_pallet_id;
        $data=array_replace($data,['destination'=>'pallet:'.$target,'request_token'=>(string)Str::uuid()]);
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data)->assertSessionHasNoErrors();
        $this->assertSame(2,ExportStockBox::where('current_pallet_id',$target)->count());
    }

    public function test_completion_requires_all_pallets_ready_and_explicit_shipped_alert_confirmation(): void
    {
        $delivery=$this->plan([2,2]);$pallets=$delivery->palletPlan->pallets;
        $data=['revision'=>1,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1];
        $this->post(route('export.fulfillment.complete',$delivery->id),$data)->assertSessionHasErrors('fulfillment');
        foreach($pallets as $p)$this->ready($delivery,$p)->assertSessionHasNoErrors();
        $this->get(route('export.fulfillment.show',$delivery))->assertSee('Pastikan seluruh box permintaan sudah keluar gudang.');
        $this->post(route('export.fulfillment.complete',$delivery->id),array_replace($data,['shipped_confirmed'=>0]))->assertSessionHasErrors('shipped_confirmed');
        $this->assertSame(4,ExportStockBox::count());
    }

    public function test_completion_removes_only_request_stock_from_active_lists_frees_locations_and_keeps_complete_audit(): void
    {
        $delivery=$this->plan([2,2]);foreach($delivery->palletPlan->pallets as $p)$this->ready($delivery,$p)->assertSessionHasNoErrors();
        $spare=$this->receive();$location=ExportMasterLocation::create(['code'=>'STAY-SPARE']);$this->place($spare,['location_id'=>$location->id]);
        $data=['revision'=>1,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1];$url=route('export.fulfillment.complete',$delivery->id);
        $this->post($url,$data)->assertSessionHasNoErrors()->assertRedirect(route('export.fulfillment.index'));
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertSame(1,ExportStockBox::count());$this->assertSame(5,ExportStockBox::withTrashed()->count());
        $this->assertTrue($spare->fresh()->exists);$this->assertTrue($location->fresh()->is_occupied);
        foreach($delivery->palletPlan->pallets as $p)$this->assertFalse(ExportMasterLocation::find($p->master_location_id)->is_occupied);
        $this->assertDatabaseCount('export_pallet_location_reservations',0);
        $this->get(route('export.fulfillment.index'))->assertDontSee($delivery->plan_code);
        $this->get(route('export.pallet-plans.index'))->assertDontSee($delivery->plan_code);
        $this->get(route('export.stock.index'))->assertOk()->assertSee($spare->box_number)->assertDontSee('00000001');
        $log=AuditLog::where('type','export_fulfillment')->where('action','completed')->sole();
        $snapshot=json_decode($log->old_values,true);$this->assertCount(4,$snapshot['boxes']);$this->assertCount(2,$snapshot['pallets']);
        $this->assertSame('READY-A',$snapshot['boxes'][0]['part_number']);
        $this->assertSame('completed',ExportDeliveryPlan::withTrashed()->find($delivery->id)->status);
        $this->assertDatabaseCount('part_settings',1);
    }

    public function test_foreign_pallet_and_revoked_permissions_cannot_mutate_fulfillment(): void
    {
        $first=$this->plan();$second=$this->plan();$p=$second->palletPlan->pallets->first();
        $this->ready($first,$p)->assertNotFound();
        RoleFeatureAccess::create(['role'=>'export_operator','permissions'=>['export_stock_input']]);
        $this->get(route('export.fulfillment.ready-form',[$second,$p]))->assertForbidden();
        $this->ready($second,$p)->assertForbidden();
        $this->post(route('export.fulfillment.complete',$second->id),['revision'=>1,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1])->assertForbidden();
    }

    public function test_return_to_ready_pallet_rolls_back_reduction_and_does_not_unlock_either_pallet(): void
    {
        $first=$this->plan();$second=$this->plan();$a=$first->palletPlan->pallets->first();$b=$second->palletPlan->pallets->first();
        $this->ready($first,$a)->assertSessionHasNoErrors();$this->ready($second,$b)->assertSessionHasNoErrors();
        $target=ExportStockPallet::where('export_planned_pallet_id',$b->id)->sole();
        $data=$this->reduction($a,extra:['destination'=>'pallet:'.$target->id]);
        $this->post(route('export.fulfillment.reduce',[$first,$a]),$data)->assertSessionHasErrors('fulfillment');
        $this->assertNotNull($a->fresh()->ready_at);$this->assertNotNull($b->fresh()->ready_at);
        $this->assertSame(3,$a->fulfillmentBoxes()->count());$this->assertSame(3,$first->fresh()->total_boxes);
    }

    public function test_reduction_updates_only_selected_part_and_keeps_other_pallet_ready(): void
    {
        $delivery=$this->plan([3,2]);$a=$delivery->palletPlan->pallets[0];$b=$delivery->palletPlan->pallets[1];
        $this->ready($delivery,$a)->assertSessionHasNoErrors();$this->ready($delivery,$b)->assertSessionHasNoErrors();
        $data=$this->reduction($a);
        $this->post(route('export.fulfillment.reduce',[$delivery,$a]),$data)->assertSessionHasNoErrors();
        $this->assertSame(4,$delivery->fresh()->total_boxes);$this->assertSame(40,$delivery->fresh()->total_pcs);
        $this->assertNotNull($b->fresh()->ready_at);$this->assertSame(2,$b->fulfillmentBoxes()->count());
        $this->get(route('export.fulfillment.show',$delivery))->assertOk()->assertSee('1 / 2 pallet siap')->assertViewHas('metrics',fn($m)=>$m['required']===4&&$m['fulfilled']===4);
    }

    public function test_failing_required_completion_audit_rolls_back_stock_archive_and_location_release(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$this->ready($delivery,$pallet)->assertSessionHasNoErrors();
        AuditLog::creating(function ($log) { if($log->type==='export_fulfillment'&&$log->action==='completed')throw new \RuntimeException('Simulated audit failure'); });
        try {
            app(ExportFulfillmentCompletionService::class)->complete($delivery->id,['revision'=>1,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1],$this->operator);
            $this->fail('Completion must require audit persistence.');
        } catch (\RuntimeException $error) { $this->assertSame('Simulated audit failure',$error->getMessage()); }
        $this->assertSame(3,ExportStockBox::count());$this->assertNotNull($delivery->fresh());
        $this->assertTrue(ExportMasterLocation::find($pallet->master_location_id)->is_occupied);
        $this->assertDatabaseCount('export_pallet_location_reservations',1);
        $this->assertDatabaseMissing('export_fulfillment_actions',['action'=>'completed']);
    }

    public function test_reopening_ready_status_preserves_contents_but_requires_location_confirmation_again(): void
    {
        $delivery=$this->plan();$pallet=$delivery->palletPlan->pallets->first();$this->ready($delivery,$pallet)->assertSessionHasNoErrors();
        $this->get(route('export.fulfillment.revise',[$delivery,$pallet]))->assertOk()->assertSee('Buka ulang kesiapan pallet');
        $this->post(route('export.fulfillment.reopen',[$delivery,$pallet]),$this->versions($pallet))->assertSessionHasNoErrors();
        $this->assertNull($pallet->fresh()->ready_at);$this->assertSame(3,$delivery->fresh()->total_boxes);$this->assertSame(3,ExportStockBox::count());
        $this->post(route('export.fulfillment.complete',$delivery->id),['revision'=>1,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1])->assertSessionHasErrors('fulfillment');
        $this->ready($delivery,$pallet)->assertSessionHasNoErrors();
    }

    public function test_mixed_pallet_reduction_can_remove_one_part_completely_and_return_multiple_boxes_to_one_empty_location(): void
    {
        $b=ExportPartSetting::create(['part_number'=>'READY-B','qty_box'=>20]);
        $delivery=app(ExportDeliveryPlanService::class)->save(['plan_code'=>'MIXED-REDUCE','customer_name'=>'Customer Mixed','delivery_deadline'=>'2026-11-20','source_type'=>'manual',
            'items'=>[['part_number'=>$this->part->part_number,'pcs_quantity'=>30],['part_number'=>$b->part_number,'pcs_quantity'=>40]]],$this->operator);
        $location=ExportMasterLocation::create(['code'=>'MIXED-FINAL']);
        $plan=app(ExportPalletPlanningService::class)->save($delivery->id,['revision'=>0,'delivery_revision'=>1,'action'=>'finalize','pallets'=>[
            ['master_location_id'=>$location->id,'items'=>[['part_setting_id'=>$this->part->id,'box_quantity'=>3],['part_setting_id'=>$b->id,'box_quantity'=>2]]]]],$this->operator);
        $notice=ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id,$notice->id,$plan->revision,$this->operator);
        foreach([$this->part,$this->part,$this->part,$b,$b] as $part)$this->place($this->receive($part));
        $pallet=$plan->pallets()->sole();$quantities=$pallet->items()->get()->mapWithKeys(fn($i)=>[$i->id=>$i->part_setting_id===$b->id?0:2])->all();
        $preview=app(ExportFulfillmentCompletionService::class)->reduction($pallet,$quantities);
        $this->assertSame(['00000003','00000005','00000004'],$preview['selected']->pluck('box_number')->all());
        $target=ExportMasterLocation::create(['code'=>'MIXED-RETURN']);
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$this->versions($pallet)+['quantities'=>$quantities,'fingerprint'=>$preview['fingerprint'],
            'destination'=>'location:'.$target->id,'return_confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(2,$delivery->fresh()->total_boxes);$this->assertSame(20,$delivery->fresh()->total_pcs);
        $this->assertSame(0,$delivery->items()->where('part_setting_id',$b->id)->sole()->pcs_quantity);
        $this->assertSame(1,$pallet->items()->count());
        $physical=ExportStockPallet::where('master_location_id',$target->id)->sole();
        $this->assertSame(3,$physical->boxes()->whereDoesntHave('fulfillment')->count());
        $this->assertSame(5,ExportStockBox::count());
        $this->ready($delivery->fresh(),$pallet)->assertSessionHasNoErrors();
    }
}
