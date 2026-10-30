<?php
namespace Tests\Feature;

use App\Exports\WarehouseRecordsExport;
use App\Models\{AuditLog, Box, ExportDeliveryPlan, ExportMasterLocation, ExportPartSetting, ExportStockAllocationNotice, ExportStockBox, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportFulfillmentCompletionService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockAllocationNoticeService, ExportStockReceivingService, WarehouseReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class WarehouseScopedReportsTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private User $reporter;
    protected function setUp(): void
    {
        parent::setUp();$this->withoutVite();$this->travelTo(now()->setDate(2026,10,30)->setTime(10,0));
        $this->operator=User::factory()->create(['role'=>'export_operator']);
        $this->reporter=User::factory()->create(['role'=>'supervisi']);$this->actingAs($this->reporter);
    }
    private function fixture(bool $finish=true): ExportDeliveryPlan
    {
        $part=ExportPartSetting::create(['part_number'=>'RPT-A','qty_box'=>10]);
        $location=ExportMasterLocation::create(['code'=>'RPT-LOC']);$back=ExportMasterLocation::create(['code'=>'RPT-BACK']);
        $delivery=app(ExportDeliveryPlanService::class)->save(['plan_code'=>'RPT-DELIVERY','customer_name'=>'Customer Report','delivery_deadline'=>'2026-11-20','source_type'=>'manual','items'=>[['part_number'=>'RPT-A','pcs_quantity'=>30]]],$this->operator);
        $plan=app(ExportPalletPlanningService::class)->save($delivery->id,['revision'=>0,'delivery_revision'=>1,'action'=>'finalize','pallets'=>[['master_location_id'=>$location->id,'items'=>[['part_setting_id'=>$part->id,'box_quantity'=>3]]]]],$this->operator);
        $notice=ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->sole();app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id,$notice->id,1,$this->operator);
        $bulk=app(ExportReceivingBatchService::class);$batch=$bulk->start($this->operator)->fresh();
        foreach(['00000001','00000002','00000003']as$number)$batch=$bulk->scan($batch->id,['box_number'=>$number,'part_number'=>'RPT-A','revision'=>$batch->revision,'scan_token'=>(string)Str::uuid()],$this->operator);
        $bulk->process($batch->id,$batch->revision,$this->operator);
        foreach(ExportStockBox::get()as$box)app(ExportStockReceivingService::class)->place($box->id,['revision'=>$box->revision,'request_token'=>(string)Str::uuid()],$this->operator);
        if($finish){
            $pallet=$plan->pallets()->sole();$item=$pallet->items()->sole();$service=app(ExportFulfillmentCompletionService::class);
            $preview=$service->reduction($pallet,[$item->id=>2]);
            $service->reduce($delivery->id,$pallet->id,['revision'=>1,'fulfillment_revision'=>1,'quantities'=>[$item->id=>2],
                'fingerprint'=>$preview['fingerprint'],'destination'=>'location:'.$back->id,'request_token'=>(string)Str::uuid(),'return_confirmed'=>1],$this->operator);
            $service->ready($delivery->id,$pallet->id,['revision'=>2,'fulfillment_revision'=>2,'location_id'=>$location->id,'request_token'=>(string)Str::uuid(),'placement_confirmed'=>1],$this->operator);
            $service->complete($delivery->id,['revision'=>2,'request_token'=>(string)Str::uuid(),'shipped_confirmed'=>1],$this->operator);
        }
        return $delivery;
    }
    private function filters(string $kind,array $extra=[]): array
    {
        return app(WarehouseReportService::class)->filters(\Illuminate\Http\Request::create('/reports/records','GET',$extra+['scope'=>'export','kind'=>$kind]));
    }

    public function test_all_export_reports_and_extra_domestic_reports_render_without_charts_on_empty_data(): void
    {
        foreach(array_keys(WarehouseReportService::KINDS)as$kind)$this->get(route('reports.records',['scope'=>'export','kind'=>$kind]))->assertOk()->assertDontSee('highcharts.js',false);
        foreach(['stock','fulfillment','returns']as$kind)$this->get(route('reports.records',['scope'=>'domestic','kind'=>$kind]))->assertOk();
        $this->get(route('reports.stock-input'))->assertOk()->assertSee('Lingkup laporan')->assertSee('Domestik / Lokal');
        $this->get(route('reports.withdrawal'))->assertOk()->assertSee('Lingkup laporan');
    }

    public function test_reports_are_available_to_requested_roles_and_denied_to_operators_sales_ppc_and_revoked_roles(): void
    {
        foreach(['admin_warehouse','supervisi','admin']as$role){$this->actingAs(User::factory()->create(['role'=>$role]));
            $this->get(route('reports.stock-input',['scope'=>'export']))->assertOk();$this->get(route('reports.records',['scope'=>'export','kind'=>'fulfillment']))->assertOk();
            $this->get(route('reports.withdrawal',['scope'=>'domestic']))->assertOk();}
        foreach(['export_operator','warehouse_operator','sales','ppc']as$role){$this->actingAs(User::factory()->create(['role'=>$role]));
            $this->get(route('reports.records',['scope'=>'export','kind'=>'stock']))->assertForbidden();
            $this->get(route('reports.records.export',['scope'=>'export','kind'=>'receipts']))->assertForbidden();
            $this->get(route('reports.stock-input',['scope'=>'export']))->assertForbidden();}
        RoleFeatureAccess::create(['role'=>'admin_warehouse','permissions'=>[]]);$this->actingAs(User::factory()->create(['role'=>'admin_warehouse']));
        $this->get(route('reports.records',['scope'=>'export','kind'=>'returns']))->assertForbidden();
    }

    public function test_archived_shipments_receipts_spare_stock_returns_and_fulfillment_reconcile_without_double_counting(): void
    {
        $this->fixture();$service=app(WarehouseReportService::class);
        Box::create(['box_number'=>'00000001','part_number'=>'DOMESTIC-ONLY','pcs_quantity'=>999,'qr_code'=>'DOM-REPORT','user_id'=>$this->operator->id]);
        foreach(['receipts'=>[3,30],'shipments'=>[2,20],'stock'=>[1,10],'returns'=>[1,10]]as$kind=>[$count,$pcs]){
            $rows=$service->query($this->filters($kind))->get();$this->assertCount($count,$rows);$this->assertSame($pcs,(int)$rows->sum('pcs_quantity'));
            $this->get(route('reports.records',['scope'=>'export','kind'=>$kind]))->assertOk()->assertDontSee('DOMESTIC-ONLY');
        }
        $row=$service->query($this->filters('fulfillment'))->sole();$this->assertSame('shipped',$row->status);$this->assertSame(2,(int)$row->required_boxes);$this->assertSame(2,(int)$row->fulfilled_boxes);
        $this->assertSame('00000003',$service->query($this->filters('stock'))->sole()->box_number);
        $this->assertSame('RPT-BACK',$service->query($this->filters('returns'))->sole()->location);
        $this->assertSame('RPT-LOC',$service->query($this->filters('receipts',['q'=>'00000001']))->sole()->location);
    }

    public function test_ready_but_not_completed_pallet_is_not_outbound_and_existing_report_urls_support_export_scope(): void
    {
        $delivery=$this->fixture(false);$service=app(WarehouseReportService::class);
        $this->assertSame(0,$service->query($this->filters('shipments'))->count());
        $this->get(route('reports.stock-input',['scope'=>'export']))->assertOk()->assertViewHas('rows',fn($r)=>$r->total()===3);
        $this->get(route('reports.withdrawal',['scope'=>'export']))->assertOk()->assertViewHas('rows',fn($r)=>$r->total()===0);
        $pallet=$delivery->palletPlan()->sole()->pallets()->sole();
        app(ExportFulfillmentCompletionService::class)->ready($delivery->id,$pallet->id,['revision'=>1,'fulfillment_revision'=>1,'location_id'=>$pallet->master_location_id,'placement_confirmed'=>1,'request_token'=>(string)Str::uuid()],$this->operator);
        $this->assertSame(0,$service->query($this->filters('shipments'))->count());
        $this->assertSame(1,(int)$service->query($this->filters('fulfillment'))->sole()->ready_pallets);
    }

    public function test_filters_are_inclusive_and_snapshot_part_filters_only_match_returned_boxes(): void
    {
        $this->fixture();$service=app(WarehouseReportService::class);
        foreach(['receipts','shipments','stock','returns']as$kind){
            $this->assertGreaterThan(0,$service->query($this->filters($kind,['start_date'=>'2026-10-30','end_date'=>'2026-10-30']))->count());
            $this->assertSame(0,$service->query($this->filters($kind,['start_date'=>'2026-10-31']))->count());
            $this->assertSame(0,$service->query($this->filters($kind,['part_number'=>'NOT-A-PART']))->count());
        }
        $this->assertSame(1,$service->query($this->filters('returns',['part_number'=>'RPT-A','warehouse_location'=>'RPT-BACK','user_id'=>$this->operator->id]))->count());
        $this->assertSame(1,$service->query($this->filters('fulfillment',['part_number'=>'RPT-A','customer_name'=>'Customer Report','start_date'=>'2026-11-20','end_date'=>'2026-11-20']))->count());
        $this->assertSame(0,$service->query($this->filters('fulfillment',['end_date'=>'2026-11-19']))->count());
    }

    public function test_details_remain_readable_after_archive_and_do_not_require_operator_export_feature_access(): void
    {
        $delivery=$this->fixture();$plan=$delivery->palletPlan()->sole();
        $this->get(route('reports.records.detail',['record'=>$plan->id,'scope'=>'export','kind'=>'fulfillment']))->assertOk()->assertSee('00000001')->assertSee('00000002')->assertDontSee('00000003');
        $audit=AuditLog::where('type','export_fulfillment')->where('action','reduced')->sole();
        $this->get(route('reports.records.detail',['record'=>$audit->id,'scope'=>'export','kind'=>'returns']))->assertOk()->assertSee('00000003')->assertSee('RPT-BACK');
        $this->get(route('reports.records.detail',['record'=>$audit->id,'scope'=>'domestic','kind'=>'returns']))->assertNotFound();
    }

    public function test_invalid_scope_kind_and_dates_are_rejected_and_domestic_navigation_keeps_existing_reports(): void
    {
        $this->getJson(route('reports.records',['scope'=>'all']))->assertUnprocessable();
        $this->getJson(route('reports.records',['scope'=>'export','kind'=>'unknown']))->assertUnprocessable();
        $this->getJson(route('reports.records',['start_date'=>'2026-11-20','end_date'=>'2026-10-01']))->assertUnprocessable();
        $this->get(route('reports.records',['scope'=>'domestic','kind'=>'receipts']))->assertRedirect();
        $this->getJson(route('reports.stock-input',['scope'=>'all']))->assertUnprocessable();
    }

    public function test_excel_uses_same_filters_and_text_box_identity_including_archived_rows(): void
    {
        $this->fixture();$service=app(WarehouseReportService::class);$filters=$this->filters('shipments',['part_number'=>'RPT-A','q'=>'00000002']);
        Excel::fake();
        $this->get(route('reports.withdrawal.export',$filters))->assertOk();
        Excel::assertDownloaded('laporan_export_shipments_20261030_100000.xlsx',fn(WarehouseRecordsExport $export)=>$export->query()->count()===1&&$export->query()->sole()->box_number==='00000002');
        $export=new WarehouseRecordsExport($service->query($filters),$filters);$mapped=$export->map($service->query($filters)->sole());
        $this->assertContains('00000002',$mapped);$this->assertSame('Ekspor',$mapped[0]);
    }

    public function test_excel_includes_every_page_and_preserves_leading_zero_ids_as_text(): void
    {
        $this->fixture();
        for($i=4;$i<=30;$i++)app(ExportStockReceivingService::class)->receive(['box_number'=>str_pad((string)$i,8,'0',STR_PAD_LEFT),'part_number'=>'RPT-A','receive_token'=>(string)Str::uuid()],$this->operator);
        $service=app(WarehouseReportService::class);$filters=$this->filters('receipts');
        $this->get(route('reports.records',$filters))->assertOk()->assertViewHas('rows',fn($r)=>$r->count()===25&&$r->total()===30);
        $bytes=Excel::raw(new WarehouseRecordsExport($service->query($filters),$filters),\Maatwebsite\Excel\Excel::XLSX);
        $file=tempnam(sys_get_temp_dir(),'report-');file_put_contents($file,$bytes);
        try{
            $sheet=\PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
            $this->assertSame(31,$sheet->getHighestRow());
            $this->assertSame('00000030',$sheet->getCell('D2')->getValue());
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$sheet->getCell('D2')->getDataType());
            $this->assertTrue(is_numeric($sheet->getCell('C2')->getValue()));
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC,$sheet->getCell('C2')->getDataType());
        }finally{unlink($file);}
    }

    public function test_receipt_transaction_search_and_report_reads_do_not_change_inventory_or_audits(): void
    {
        $this->fixture();$service=app(WarehouseReportService::class);$before=[ExportStockBox::withTrashed()->count(),AuditLog::count()];
        $this->assertSame(3,$service->query($this->filters('receipts',['q'=>'TRX-000001']))->count());
        $this->assertSame(1,$service->query($this->filters('stock',['q'=>'Pallet cadangan']))->count());
        foreach(array_keys(WarehouseReportService::KINDS)as$kind)$this->get(route('reports.records',['scope'=>'export','kind'=>$kind]))->assertOk();
        $this->assertSame($before,[ExportStockBox::withTrashed()->count(),AuditLog::count()]);
    }

    public function test_excel_treats_customer_formula_text_as_literal_text(): void
    {
        $delivery=$this->fixture();\Illuminate\Support\Facades\DB::table('export_delivery_plans')->where('id',$delivery->id)->update(['customer_name'=>'=1+1']);
        $service=app(WarehouseReportService::class);$filters=$this->filters('shipments');
        $bytes=Excel::raw(new WarehouseRecordsExport($service->query($filters),$filters),\Maatwebsite\Excel\Excel::XLSX);
        $file=tempnam(sys_get_temp_dir(),'report-');file_put_contents($file,$bytes);
        try{$sheet=\PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
            $this->assertSame('=1+1',$sheet->getCell('D2')->getValue());
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$sheet->getCell('D2')->getDataType());
        }finally{unlink($file);}
    }

    public function test_ajax_returns_only_report_content_and_keeps_scope_filters_and_export_links(): void
    {
        $this->fixture();
        foreach(array_keys(WarehouseReportService::KINDS) as $kind){
            $response=$this->getJson(route('reports.records',['scope'=>'export','kind'=>$kind,'part_number'=>'RPT-A']));
            $response->assertOk()->assertJsonStructure(['html','title','url'])->assertHeader('Cache-Control','no-store, private')->assertHeader('Vary','Accept');
            $html=$response->json('html');$this->assertStringNotContainsString('<html',$html);$this->assertStringNotContainsString('globalSearchInput',$html);
            $this->assertStringContainsString('data-report-export',$html);$this->assertStringContainsString('part_number=RPT-A',$html);
        }
        $this->getJson(route('reports.records',['scope'=>'export','kind'=>'stock','q'=>'NO-MATCH','status'=>'spare','user_id'=>$this->operator->id]))
            ->assertOk()->assertJsonPath('title','Laporan Operasional');
        $html=$this->getJson(route('reports.records',['scope'=>'export','kind'=>'stock','q'=>'NO-MATCH','status'=>'spare','user_id'=>$this->operator->id]))->json('html');
        $this->assertMatchesRegularExpression('/value="spare"\s+selected/',$html);
        $this->assertMatchesRegularExpression('/value="'.$this->operator->id.'"\s+selected/',$html);
    }

    public function test_ajax_supports_existing_domestic_reports_and_rejects_invalid_dates_without_inventory_mutations(): void
    {
        $before=AuditLog::count();
        foreach(['reports.stock-input','reports.withdrawal'] as $route){
            $this->getJson(route($route))->assertOk()->assertJsonStructure(['html','title','url'])->assertJsonFragment(['url'=>route($route)]);
            $this->getJson(route($route,['start_date'=>'2026-11-20','end_date'=>'2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('end_date');
        }
        $this->assertSame($before,AuditLog::count());
    }

    public function test_ajax_pagination_and_detail_keep_archived_data_and_filter_query(): void
    {
        $delivery=$this->fixture();
        for($i=4;$i<=30;$i++)app(ExportStockReceivingService::class)->receive(['box_number'=>str_pad((string)$i,8,'0',STR_PAD_LEFT),'part_number'=>'RPT-A','receive_token'=>(string)Str::uuid()],$this->operator);
        $response=$this->getJson(route('reports.records',['scope'=>'export','kind'=>'receipts','part_number'=>'RPT-A','page'=>2]));
        $response->assertOk();$this->assertStringContainsString('00000001',$response->json('html'));$this->assertStringContainsString('part_number=RPT-A',$response->json('html'));
        $plan=$delivery->palletPlan()->sole();
        $this->getJson(route('reports.records.detail',['record'=>$plan->id,'scope'=>'export','kind'=>'fulfillment']))
            ->assertOk()->assertJsonStructure(['html','title','url']);
    }

    public function test_domestic_withdrawal_excel_now_matches_part_and_operator_filters(): void
    {
        $other=User::factory()->create(['role'=>'warehouse_operator']);
        foreach([[$this->operator->id,'DOM-A'],[$other->id,'DOM-A'],[$this->operator->id,'DOM-B']] as [$user,$part]){
            \App\Models\StockWithdrawal::create(['user_id'=>$user,'part_number'=>$part,'pcs_quantity'=>10,'box_quantity'=>1,'warehouse_location'=>'DOM-LOC','status'=>'completed','withdrawn_at'=>now()]);
        }
        Excel::fake();
        $this->get(route('reports.withdrawal.export',['part_number'=>'DOM-A','user_id'=>$this->operator->id]))->assertOk();
        Excel::assertDownloaded('stock_withdrawal_report_20261030_100000.xlsx',fn($export)=>$export->collection()->count()===1);
    }
}
