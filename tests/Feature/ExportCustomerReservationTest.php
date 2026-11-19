<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportCustomer, ExportCustomerStockException, ExportDeliveryPlan, ExportFulfillmentBox, ExportMasterLocation, ExportStockBox, PartSetting, User};
use App\Services\{ExportCustomerReservationService, ExportCustomerService, ExportCustomerStockExceptionService, ExportCustomerStockPolicy, ExportDeliveryPlanService, ExportInventoryService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockAllocationService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExportCustomerReservationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\SeedsExistingExportStock;
    private User $admin;
    private PartSetting $part;
    private ExportCustomer $a;
    private ExportCustomer $b;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 16)->startOfDay());
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->part = PartSetting::create(['part_number' => 'CUSTOMER-PART', 'qty_box' => 10]);
        $this->a = app(ExportCustomerService::class)->save(['code' => 'C-A', 'name' => 'PT Alpha', 'aliases' => 'ALPHA', 'is_active' => true], $this->admin);
        $this->b = app(ExportCustomerService::class)->save(['code' => 'C-B', 'name' => 'PT Beta', 'aliases' => 'BETA', 'is_active' => true], $this->admin);
    }

    private function stock(?ExportCustomer $owner = null, string $date = '2026-10-01'): ExportStockBox
    {
        $number = str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => $number, 'part_number' => $this->part->part_number,
            'receive_token' => (string) Str::uuid(), 'customer_id' => $owner?->id], $this->admin, false, new \DateTimeImmutable($date));
        $location = ExportMasterLocation::create(['code' => 'CS-'.$number]);
        $this->storeExistingExportBoxes(collect([$box]), $this->admin, $location);
        return $box->fresh();
    }

    private function plan(ExportCustomer $customer, int $quantity = 3, string $deadline = '2026-11-20'): ExportDeliveryPlan
    {
        $code = 'CS-PLAN-'.++$this->sequence;
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => $customer->name,
            'customer_id' => $customer->id, 'delivery_deadline' => $deadline, 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 10 * $quantity]]], $this->admin);
        $location = ExportMasterLocation::create(['code' => $code]);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => $quantity]]]]], $this->admin);
        return $delivery->fresh(['palletPlan.pallets.items']);
    }

    private function exceptionPayload(ExportDeliveryPlan $delivery, ExportStockBox $box): array
    {
        $pallet = $delivery->palletPlan->pallets->first();
        return ['request_token' => (string) Str::uuid(), 'item_id' => $pallet->items->first()->id, 'source_customer_id' => $this->b->id,
            'delivery_revision' => $delivery->revision, 'plan_revision' => $delivery->palletPlan->revision, 'pallet_revision' => $pallet->fulfillment_revision,
            'boxes' => [$box->id => $box->fresh()->revision], 'reason' => 'Stok Alpha belum cukup untuk tenggat delivery.'];
    }

    public function test_receipt_ownership_is_set_before_automatic_allocation_and_never_changes_fifo(): void
    {
        $delivery = $this->plan($this->a, 1);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '99000001', 'part_number' => $this->part->part_number,
            'receive_token' => (string) Str::uuid(), 'customer_id' => $this->b->id], $this->admin);
        $this->assertNull($box->fulfillment);
        $this->assertSame($this->b->id, app(ExportCustomerStockPolicy::class)->ownerId($box));
        $this->assertSame(10, $box->pcs_quantity);
        $this->assertSame('2026-11-16', $box->first_received_at->toDateString());
        $this->assertDatabaseCount('export_fulfillment_boxes', 0);
    }

    public function test_own_stock_precedes_older_general_and_other_customers_are_never_automatically_taken(): void
    {
        $beta = $this->stock($this->b, '2026-06-01');
        $common = $this->stock(null, '2026-07-01');
        $alpha = $this->stock($this->a, '2026-10-01');
        $delivery = $this->plan($this->a);
        $this->assertSame([$alpha->id, $common->id], ExportFulfillmentBox::orderBy('id')->pluck('export_stock_box_id')->all());
        $this->assertNull($beta->fresh()->fulfillment);
        $this->assertSame('2026-10-01', $alpha->fresh()->first_received_at->toDateString());
        $this->expectException(ValidationException::class);
        ExportFulfillmentBox::create(['export_stock_box_id' => $beta->id, 'box_number' => $beta->box_number,
            'export_planned_pallet_item_id' => $delivery->palletPlan->pallets->first()->items->first()->id,
            'part_setting_id' => $this->part->id, 'pcs_quantity' => 10, 'first_received_at' => $beta->first_received_at, 'reserved_at' => now(), 'reserved_by' => $this->admin->id]);
    }

    public function test_exception_is_targeted_idempotent_and_cancellation_restores_original_owner(): void
    {
        $this->stock($this->a); $this->stock(); $beta = $this->stock($this->b);
        $delivery = $this->plan($this->a);
        $data = $this->exceptionPayload($delivery, $beta);
        $service = app(ExportCustomerStockExceptionService::class);
        $service->create($delivery->id, $data, $this->admin);
        $events = DB::table('export_customer_stock_events')->count();
        $service->create($delivery->id, $data, $this->admin);
        $this->assertSame($events, DB::table('export_customer_stock_events')->count());
        $exception = ExportCustomerStockException::sole();
        $this->assertSame($delivery->id, $exception->export_delivery_plan_id);
        $this->assertSame($this->b->id, app(ExportCustomerStockPolicy::class)->ownerId($beta));
        $this->assertNotNull($beta->fresh()->fulfillment);
        $this->getJson(route('export.stock.index', ['customer' => 'customer:'.$this->a->id]))->assertOk()
            ->assertJsonPath('count', 3)->assertJsonPath('stockCounts.with_delivery', 3);
        $this->getJson(route('export.stock.index', ['customer' => 'customer:'.$this->b->id]))->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('stockCounts.with_delivery', 1);
        $service->cancel($delivery->id, $exception->id, ['request_token' => (string) Str::uuid(), 'revision' => $exception->revision], $this->admin);
        $this->assertSame('cancelled', $exception->fresh()->status);
        $this->assertNull($beta->fresh()->fulfillment);
        $this->assertSame($this->b->id, app(ExportCustomerStockPolicy::class)->ownerId($beta));
        $this->assertSame(2, ExportFulfillmentBox::count());
        $this->getJson(route('export.stock.index', ['customer' => 'customer:'.$this->b->id, 'delivery_status' => 'without_delivery']))->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('stockCounts.with_delivery', 0);
    }

    public function test_exception_rechecks_fifo_and_rejects_a_box_committed_to_beta(): void
    {
        $old = $this->stock($this->b, '2026-07-01'); $new = $this->stock($this->b, '2026-08-01');
        $delivery = $this->plan($this->a, 1);
        try { app(ExportCustomerStockExceptionService::class)->create($delivery->id, $this->exceptionPayload($delivery, $new), $this->admin); $this->fail('FIFO bypass accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('FIFO', $e->getMessage()); }
        $this->assertDatabaseCount('export_customer_stock_exceptions', 0);
        $this->plan($this->b, 2, '2026-11-17');
        $this->expectException(ValidationException::class);
        app(ExportCustomerStockExceptionService::class)->create($delivery->id, $this->exceptionPayload($delivery, $old), $this->admin);
    }

    public function test_manage_requires_permission_revision_reason_and_preserves_business_values(): void
    {
        $box = $this->stock(); $before = $box->only(['id','pcs_quantity','current_pallet_id','first_received_at']);
        $payload = ['request_token' => (string) Str::uuid(), 'boxes' => [$box->id => $box->revision], 'customer_id' => $this->a->id, 'reason' => 'Pemetaan fisik telah diverifikasi.'];
        $operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($operator)->postJson(route('export.stock.customer-reservations'), $payload + ['reservation_action' => 'assign'])->assertForbidden();
        $this->actingAs($this->admin);
        app(ExportCustomerReservationService::class)->change($payload, $this->admin);
        $this->assertEquals($before, $box->fresh()->only(array_keys($before)));
        $this->assertSame($this->a->id, app(ExportCustomerStockPolicy::class)->ownerId($box));
        $this->get(route('export.stock.index', ['customer_view' => 'reservations']))->assertOk()->assertSee('PT Alpha');
        $this->expectException(ValidationException::class);
        app(ExportCustomerReservationService::class)->change(array_replace($payload, ['request_token'=>(string) Str::uuid(), 'customer_id'=>null]), $this->admin);
    }

    public function test_audit_failure_rolls_back_the_entire_batch(): void
    {
        $first = $this->stock(); $second = $this->stock();
        AuditLog::creating(function ($log) { if ($log->type === 'export_customer_stock') throw new \RuntimeException('Audit unavailable'); });
        try { app(ExportCustomerReservationService::class)->change(['request_token'=>(string)Str::uuid(), 'boxes'=>[$first->id=>$first->revision,$second->id=>$second->revision], 'customer_id'=>$this->a->id,'reason'=>'Batch mapping'],$this->admin); $this->fail('Audit failure ignored'); }
        catch (\RuntimeException $e) { $this->assertSame('Audit unavailable', $e->getMessage()); }
        $this->assertDatabaseCount('export_box_customer_reservations', 0);
        $this->assertDatabaseCount('export_customer_stock_events', 0);
        $this->assertDatabaseCount('export_customer_stock_requests', 0);
        $this->assertSame($first->revision, $first->fresh()->revision);
    }

    public function test_customer_matching_is_exact_with_aliases_and_reports_code_name_conflicts(): void
    {
        $service = app(ExportCustomerService::class);
        $this->assertSame($this->a->id, $service->resolve('  ALPHA  ')['customer_id']);
        $this->assertSame($this->a->id, $service->resolve('pt   alpha')['customer_id']);
        $this->assertSame('unmatched', $service->resolve('PT Alfa')['status']);
        $this->assertSame('conflict', $service->resolve('PT Beta', 'C-A')['status']);
        $this->get(route('export.customers.index'))->assertOk()->assertSee('ALPHA');
        $this->get(route('export.customers.edit', $this->a))->assertOk();
    }

    public function test_draft_boxes_keep_individual_customers_and_replay_cannot_change_them(): void
    {
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($this->admin);
        $scan = ['box_number'=>'88000001','part_number'=>$this->part->part_number,'revision'=>$batch->revision,'scan_token'=>(string)Str::uuid(),'customer_id'=>$this->a->id];
        $batch = $service->scan($batch->id,$scan,$this->admin);
        $batch = $service->scan($batch->id,['box_number'=>'88000002','part_number'=>$this->part->part_number,'revision'=>$batch->revision,'scan_token'=>(string)Str::uuid(),'customer_id'=>$this->b->id],$this->admin);
        $state = $service->state($batch);
        $this->assertSame([$this->a->id,$this->b->id],array_column($state['items'],'customer_id'));
        try { $service->scan($batch->id,array_replace($scan,['customer_id'=>$this->b->id]),$this->admin); $this->fail('Replay changed customer'); }
        catch (ValidationException) { $this->assertDatabaseCount('export_receiving_item_customers',2); }
        $service->process($batch->id,$batch->revision,$this->admin);
        $this->assertSame([$this->a->id,$this->b->id],ExportStockBox::orderBy('id')->get()->map(fn($box)=>app(ExportCustomerStockPolicy::class)->ownerId($box))->all());
    }

    public function test_new_usage_does_not_inherit_old_reservations_but_source_lineage_does(): void
    {
        $source = $this->stock($this->b);
        $replacement = ExportStockBox::create(['box_number'=>'77000001','part_setting_id'=>$this->part->id,'part_number'=>$this->part->part_number,'pcs_quantity'=>10,'source_box_id'=>$source->id,
            'received_by'=>$this->admin->id,'receive_token'=>(string)Str::uuid(),'first_received_at'=>$source->first_received_at,'state'=>'received','revision'=>1]);
        $this->assertSame($this->b->id,app(ExportCustomerStockPolicy::class)->ownerId($replacement));
        $source->delete();
        $reused = app(ExportStockReceivingService::class)->receive(['box_number'=>$source->box_number,'part_number'=>$this->part->part_number,'receive_token'=>(string)Str::uuid()],$this->admin,false);
        $this->assertNull(app(ExportCustomerStockPolicy::class)->ownerId($reused));
        $this->assertSame(2,(int)$reused->identity_cycle);
    }

    public function test_disabling_new_writes_does_not_disable_reservation_enforcement(): void
    {
        $beta = $this->stock($this->b); $delivery = $this->plan($this->a,1);
        config(['export_customer_stock.writes_enabled'=>false]);
        app(ExportInventoryService::class)->transaction(fn()=>app(ExportStockAllocationService::class)->allocate($this->admin));
        $this->assertNull($beta->fresh()->fulfillment);
        $this->assertSame($this->b->id,app(ExportCustomerStockPolicy::class)->ownerId($beta));
    }

    public function test_exception_pages_and_source_filter_render_without_mutating_stock(): void
    {
        $beta = $this->stock($this->b); $delivery = $this->plan($this->a,1);
        $item = $delivery->palletPlan->pallets->first()->items->first();
        $this->get(route('export.customer-exceptions.preview',[$delivery,'item_id'=>$item->id,'source_customer_id'=>$this->b->id]))->assertOk()->assertSee($beta->box_number)->assertSee('Pemilik box tetap customer sumber');
        $this->assertDatabaseCount('export_customer_stock_exceptions',0);
        $this->assertDatabaseCount('export_fulfillment_boxes',0);
        $this->get(route('export.stock.index',['customer_view'=>'reservations','process'=>'free','owner'=>$this->b->id]))->assertOk()->assertSee($beta->box_number);
        $operator = User::factory()->create(['role'=>'export_operator']);
        $this->actingAs($operator)->get(route('export.customer-exceptions.preview',[$delivery,'item_id'=>$item->id]))->assertForbidden();
    }

    public function test_import_auto_links_alias_and_a_customer_change_invalidates_request_token(): void
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([['Customer','ALPHA'],['Tanggal','2026-11-20'],['Kode Rencana','CS-IMPORT'],['Nomor Part','Jumlah PCS'],[$this->part->part_number,10]]);
        $file = tempnam(sys_get_temp_dir(),'customer-import-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
            $this->postJson(route('export.delivery-plans.import-excel'),['file'=>new \Illuminate\Http\UploadedFile($file,'customer.xlsx',null,null,true)])
                ->assertOk()->assertJsonPath('customer_match.customer_id',$this->a->id)->assertJsonPath('customer_match.status','matched');
        } finally { @unlink($file); $book->disconnectWorksheets(); }
        $data=['request_token'=>(string)Str::uuid(),'plan_code'=>'CUSTOMER-HASH','customer_id'=>$this->a->id,'customer_name'=>'ALPHA',
            'customer_reference_version'=>1,'delivery_deadline'=>'2026-11-20','source_type'=>'manual','items'=>[['part_number'=>$this->part->part_number,'pcs_quantity'=>10]]];
        $saved=app(ExportDeliveryPlanService::class)->save($data,$this->admin);
        $this->assertSame('PT Alpha',$saved->customer_name);
        $this->assertSame('ALPHA',$saved->customerLink->source_name);
        $this->expectException(ValidationException::class);
        app(ExportDeliveryPlanService::class)->save(array_replace($data,['customer_id'=>$this->b->id]),$this->admin);
    }

    public function test_customer_revision_and_alias_conflicts_are_atomic_and_inactive_customer_keeps_existing_stock(): void
    {
        $box=$this->stock($this->a);
        try { app(ExportCustomerService::class)->save(['code'=>'C-B','name'=>'Changed Beta','aliases'=>'ALPHA','revision'=>1,'is_active'=>true],$this->admin,$this->b->id); $this->fail('Alias collision accepted'); }
        catch (ValidationException) { $this->assertSame('PT Beta',$this->b->fresh()->name); }
        app(ExportCustomerService::class)->save(['code'=>'C-A','name'=>'PT Alpha','aliases'=>'ALPHA','revision'=>1,'is_active'=>false],$this->admin,$this->a->id);
        $this->assertSame($this->a->id,app(ExportCustomerStockPolicy::class)->ownerId($box));
        $this->assertSame('unmatched',app(ExportCustomerService::class)->resolve('ALPHA')['status']);
    }

    public function test_existing_commitment_cannot_be_reassigned_by_ownership_management(): void
    {
        $box=$this->stock($this->a); $this->plan($this->a,1);
        $this->expectException(ValidationException::class);
        app(ExportCustomerReservationService::class)->change(['request_token'=>(string)Str::uuid(),'boxes'=>[$box->id=>$box->fresh()->revision],
            'customer_id'=>null,'reason'=>'Attempt to release committed stock'],$this->admin);
    }

    public function test_a_stale_cancel_form_cannot_cancel_a_new_exception_on_the_same_box(): void
    {
        $beta=$this->stock($this->b); $delivery=$this->plan($this->a,1);
        $service=app(ExportCustomerStockExceptionService::class);
        $service->create($delivery->id,$this->exceptionPayload($delivery,$beta),$this->admin);
        $exception=ExportCustomerStockException::sole();
        $stale=['request_token'=>(string)Str::uuid(),'revision'=>$exception->revision];
        $service->cancel($delivery->id,$exception->id,['request_token'=>(string)Str::uuid(),'revision'=>$exception->revision],$this->admin);
        $delivery=$delivery->fresh(['palletPlan.pallets.items']);
        $service->create($delivery->id,$this->exceptionPayload($delivery,$beta),$this->admin);
        try {$service->cancel($delivery->id,$exception->id,$stale,$this->admin);$this->fail('Stale cancellation accepted');}
        catch(ValidationException){$this->assertSame('active',$exception->fresh()->status);$this->assertNotNull($beta->fresh()->fulfillment);}
    }

    public function test_shipping_an_exception_keeps_owner_and_recipient_in_immutable_audit(): void
    {
        $this->stock($this->a);$beta=$this->stock($this->b);$delivery=$this->plan($this->a,2);
        app(ExportCustomerStockExceptionService::class)->create($delivery->id,$this->exceptionPayload($delivery,$beta),$this->admin);
        $delivery=$delivery->fresh(['palletPlan.pallets.items']);$plan=$delivery->palletPlan;$pallet=$plan->pallets->first();
        $notice=\App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->where('plan_revision',$plan->revision)->firstOrFail();
        app(\App\Services\ExportStockAllocationNoticeService::class)->acknowledge($delivery->id,$notice->id,$plan->revision,$this->admin);
        $picking=app(\App\Services\ExportPickingService::class);
        $pickingRows=$picking->rows($pallet->id,$notice->id,recommendationsOnly:true);
        $picking->start($pallet->id,$notice->id,$picking->startFingerprint($pickingRows),$this->admin);
        foreach(ExportFulfillmentBox::with('stockBox')->get() as $f)app(ExportStockReceivingService::class)->place($f->export_stock_box_id,
            ['revision'=>$f->stockBox->revision,'request_token'=>(string)Str::uuid()],$this->admin);
        $this->post(route('export.fulfillment.ready',[$delivery,$pallet]),['revision'=>$plan->revision,'fulfillment_revision'=>$pallet->fulfillment_revision,
            'request_token'=>(string)Str::uuid(),'location_id'=>$pallet->master_location_id,'placement_confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('export.fulfillment.complete',$delivery),['revision'=>$plan->revision,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('consumed',ExportCustomerStockException::sole()->status);
        $snapshot=AuditLog::where('type','export_fulfillment')->where('action','completed')->sole()->getOldValuesArray();
        $shipped=collect($snapshot['boxes'])->firstWhere('box_id',$beta->id);
        $this->assertSame($this->b->id,$shipped['owner_customer_id']);
        $this->assertSame($this->a->id,$shipped['recipient_customer_id']);
        $this->assertSame('PT Beta',$shipped['owner_customer']);
    }

    public function test_master_rename_does_not_rewrite_an_existing_delivery_customer_snapshot(): void
    {
        $data=['plan_code'=>'SNAPSHOT-CUSTOMER','customer_id'=>$this->a->id,'customer_name'=>$this->a->name,'delivery_deadline'=>'2026-11-20','source_type'=>'manual',
            'items'=>[['part_number'=>$this->part->part_number,'pcs_quantity'=>10]]];
        $delivery=app(ExportDeliveryPlanService::class)->save($data,$this->admin);
        app(ExportCustomerService::class)->save(['code'=>'C-A','name'=>'Alpha New Name','aliases'=>'ALPHA','is_active'=>true,'revision'=>1],$this->admin,$this->a->id);
        $data['revision']=$delivery->revision;$data['customer_name']='Alpha New Name';$data['items'][0]['pcs_quantity']=20;
        $saved=app(ExportDeliveryPlanService::class)->save($data,$this->admin,$delivery->id);
        $this->assertSame('PT Alpha',$saved->customer_name);
        $this->assertSame($this->a->id,app(ExportCustomerStockPolicy::class)->customerId($saved));
    }

    public function test_operator_permission_can_be_granted_then_revoked_without_supervisor_approval(): void
    {
        $first=$this->stock($this->b);$second=$this->stock($this->b);$delivery=$this->plan($this->a,2);
        $operator=User::factory()->create(['role'=>'export_operator']);
        $permissions=app(\App\Services\FeatureAccessService::class)->defaults('export_operator');
        $record=\App\Models\RoleFeatureAccess::updateOrCreate(['role'=>'export_operator'],['permissions'=>[...$permissions,'export_stock_customer_exception'],'permissions_version'=>2]);
        request()->attributes->remove('feature_access.export_operator');
        $this->actingAs($operator)->postJson(route('export.customer-exceptions.store',$delivery),$this->exceptionPayload($delivery,$first))->assertRedirect();
        $this->assertSame($operator->id,ExportCustomerStockException::sole()->authorized_by);
        $record->update(['permissions'=>$permissions]);request()->attributes->remove('feature_access.export_operator');
        $delivery=$delivery->fresh(['palletPlan.pallets.items']);
        $this->postJson(route('export.customer-exceptions.store',$delivery),$this->exceptionPayload($delivery,$second))->assertForbidden();
        $this->assertDatabaseCount('export_customer_stock_exceptions',1);
    }

    public function test_receipt_retry_uses_original_customer_snapshot_after_later_reservation_changes(): void
    {
        $data=['box_number'=>'66000001','part_number'=>$this->part->part_number,'customer_id'=>$this->b->id,'receive_token'=>(string)Str::uuid()];
        $service=app(ExportStockReceivingService::class);$box=$service->receive($data,$this->admin,false);
        $location=ExportMasterLocation::create(['code'=>'RECEIPT-REPLAY']);
        $box=$service->place($box->id,['revision'=>$box->revision,'request_token'=>(string)Str::uuid(),'location_id'=>$location->id],$this->admin);
        app(ExportCustomerReservationService::class)->change(['request_token'=>(string)Str::uuid(),'boxes'=>[$box->id=>$box->revision],'customer_id'=>null,'reason'=>'Reservasi dilepas setelah pemeriksaan.'],$this->admin);
        $count=AuditLog::count();$replayed=$service->receive($data,$this->admin,false);
        $this->assertSame($box->id,$replayed->id);$this->assertNull(app(ExportCustomerStockPolicy::class)->ownerId($replayed));$this->assertSame($count,AuditLog::count());
        $this->expectException(ValidationException::class);
        $service->receive(array_replace($data,['customer_id'=>$this->a->id]),$this->admin,false);
    }
}
