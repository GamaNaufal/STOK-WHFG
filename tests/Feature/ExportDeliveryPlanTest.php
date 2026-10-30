<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportPartSetting;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExportDeliveryPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function operator(): User
    {
        return User::factory()->create(['role' => 'export_operator']);
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'request_token' => (string) \Illuminate\Support\Str::uuid(),
            'customer_name' => 'Customer Ekspor', 'delivery_deadline' => '2026-11-15',
            'source_type' => 'manual', 'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => 100]],
        ], $changes);
    }

    private function workbook(array $grid): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($grid, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'plan-test-');
        (new Xlsx($book))->save($path);
        $bytes = file_get_contents($path);
        unlink($path);
        $book->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('rencana.xlsx', $bytes);
    }

    public function test_repeated_save_with_same_token_creates_one_plan_and_one_audit(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());
        $data=$this->payload(['plan_code'=>'RETRY-RD']);$url=route('export.delivery-plans.store');
        $first=$this->post($url,$data)->assertSessionHasNoErrors();$plan=ExportDeliveryPlan::sole();
        $second=$this->post($url,$data)->assertSessionHasNoErrors();
        $first->assertRedirect(route('export.delivery-plans.show',$plan));$second->assertRedirect(route('export.delivery-plans.show',$plan));
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertDatabaseCount('export_delivery_plan_requests',1);
        $this->assertSame(1,AuditLog::where('type','export_delivery_plan')->count());
    }

    public function test_token_cannot_be_reused_for_changed_content_another_actor_or_an_update(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$actor=$this->operator();$this->actingAs($actor);
        $data=$this->payload();$this->post(route('export.delivery-plans.store'),$data)->assertSessionHasNoErrors();$plan=ExportDeliveryPlan::sole();
        $this->postJson(route('export.delivery-plans.store'),array_replace($data,['customer_name'=>'Another customer']))->assertUnprocessable()->assertJsonValidationErrors('request_token');
        $this->putJson(route('export.delivery-plans.update',$plan),$data+['revision'=>1])->assertUnprocessable()->assertJsonValidationErrors('request_token');
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('request_token');
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertSame(1,$plan->fresh()->revision);
    }

    public function test_failed_validation_does_not_consume_save_token(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());
        $data=$this->payload(['items'=>[['part_number'=>'EXP-001','pcs_quantity'=>101]]]);
        $this->postJson(route('export.delivery-plans.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('items.0.pcs_quantity');
        $this->assertDatabaseCount('export_delivery_plan_requests',0);
        $data['items'][0]['pcs_quantity']=100;
        $this->post(route('export.delivery-plans.store'),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertDatabaseCount('export_delivery_plan_requests',1);
    }

    public function test_restored_form_with_changed_content_preserves_edits_and_gets_fresh_token_without_saving(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());$data=$this->payload();
        $this->post(route('export.delivery-plans.store'),$data)->assertSessionHasNoErrors();
        $changed=array_replace($data,['customer_name'=>'New intended customer']);$create=route('export.delivery-plans.create');
        $this->from($create)->post(route('export.delivery-plans.store'),$changed)->assertRedirect($create)->assertSessionHasErrors('request_token')
            ->assertSessionHasInput('customer_name','New intended customer');
        $fresh=session()->getOldInput('request_token');$this->assertNotSame($data['request_token'],$fresh);$this->assertTrue(\Illuminate\Support\Str::isUuid($fresh));
        $this->assertDatabaseCount('export_delivery_plans',1);
        $this->post(route('export.delivery-plans.store'),array_replace($changed,['request_token'=>$fresh]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans',2);
    }

    public function test_successful_update_retry_does_not_increment_revision_twice_even_after_archive(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());
        $this->post(route('export.delivery-plans.store'),$this->payload())->assertSessionHasNoErrors();$plan=ExportDeliveryPlan::sole();
        $data=$this->payload(['revision'=>1,'items'=>[['part_number'=>'EXP-001','pcs_quantity'=>120]]]);$url=route('export.delivery-plans.update',$plan);
        $this->put($url,$data)->assertSessionHasNoErrors();$this->put($url,$data)->assertSessionHasNoErrors();
        $this->assertSame(2,$plan->fresh()->revision);$this->assertSame(120,$plan->fresh()->total_pcs);
        $this->delete(route('export.delivery-plans.destroy',$plan),['revision'=>2])->assertSessionHasNoErrors();
        $this->put($url,$data)->assertSessionHasNoErrors()->assertRedirect(route('export.delivery-plans.index'));
        $this->assertDatabaseCount('export_delivery_plan_requests',2);
        $this->assertSame(1,AuditLog::where('type','export_delivery_plan')->where('action','updated')->count());
    }

    public function test_create_retry_resolves_archived_plan_before_rechecking_changed_master_capacity(): void
    {
        $part=ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());$data=$this->payload();
        $this->post(route('export.delivery-plans.store'),$data)->assertSessionHasNoErrors();$plan=ExportDeliveryPlan::sole();
        $this->delete(route('export.delivery-plans.destroy',$plan),['revision'=>1])->assertSessionHasNoErrors();
        $this->put(route('export.part-settings.update',$part),['part_number'=>$part->part_number,'qty_box'=>30])->assertSessionHasNoErrors();
        $this->post(route('export.delivery-plans.store'),$data)->assertSessionHasNoErrors()->assertRedirect(route('export.delivery-plans.index'));
        $this->assertSame(1,ExportDeliveryPlan::withTrashed()->count());$this->assertSame(0,ExportDeliveryPlan::count());
    }

    public function test_web_save_requires_a_valid_request_token(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$this->actingAs($this->operator());$data=$this->payload();
        unset($data['request_token']);$this->postJson(route('export.delivery-plans.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('request_token');
        $this->postJson(route('export.delivery-plans.store'),$data+['request_token'=>'bad-token'])->assertUnprocessable()->assertJsonValidationErrors('request_token');
        $this->assertDatabaseCount('export_delivery_plans',0);
    }

    public function test_audit_failure_rolls_back_plan_and_token_and_allows_retry(): void
    {
        ExportPartSetting::create(['part_number'=>'EXP-001','qty_box'=>20]);$actor=$this->operator();$data=$this->payload();$fail=true;
        AuditLog::creating(function($log)use(&$fail){if($fail&&$log->type==='export_delivery_plan')throw new \RuntimeException('Audit unavailable');});
        try{app(\App\Services\ExportDeliveryPlanService::class)->save($data,$actor);$this->fail('Audit must persist.');}
        catch(\RuntimeException $e){$this->assertSame('Audit unavailable',$e->getMessage());}
        $this->assertDatabaseCount('export_delivery_plans',0);$this->assertDatabaseCount('export_delivery_plan_requests',0);
        $fail=false;app(\App\Services\ExportDeliveryPlanService::class)->save($data,$actor);
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertDatabaseCount('export_delivery_plan_requests',1);
    }

    public function test_default_access_and_revocation_cover_pages_import_and_template(): void
    {
        foreach (['warehouse_operator', 'admin_warehouse', 'sales', 'ppc', 'supervisi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('export.delivery-plans.index'))->assertForbidden();
            $this->postJson(route('export.delivery-plans.store'), [])->assertForbidden();
            $this->postJson(route('export.delivery-plans.import-excel'), [])->assertForbidden();
            $this->get(route('export.delivery-plans.template'))->assertForbidden();
        }
        $operator = $this->operator();
        $this->actingAs($operator)->get(route('export.delivery-plans.create'))->assertOk()
            ->assertSee('Rencana delivery baru')->assertSee('Isi manual')->assertSee('Dari Excel')
            ->assertDontSee('Dari foto')->assertDontSee('plan-photo-file')->assertDontSee('data-ocr-base');
        $this->get(route('export.delivery-plans.template'))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get(route('export.delivery-plans.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('export.delivery-plans.index'))->assertOk();
    }

    public function test_creation_uses_export_capacity_and_ignores_client_totals_without_mutating_inventory(): void
    {
        PartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 5]);
        $part = ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $operator = $this->operator();
        $this->actingAs($operator)->post(route('export.delivery-plans.store'), $this->payload([
            'total_boxes' => 999, 'status' => 'approved',
            'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => 100, 'pcs_per_box' => 1, 'box_quantity' => 999]],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $plan = ExportDeliveryPlan::with('items')->sole();
        $this->assertSame(5, $plan->total_boxes);
        $this->assertSame(100, $plan->total_pcs);
        $this->assertSame('draft', $plan->status);
        $this->assertSame(20, $plan->items[0]->pcs_per_box);
        $this->assertSame($part->id, $plan->items[0]->part_setting_id);
        $this->assertSame($operator->id, $plan->created_by);
        foreach (['boxes', 'pallets', 'stock_inputs', 'stock_withdrawals', 'delivery_orders'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $audit = AuditLog::where('type', 'export_delivery_plan')->sole();
        $this->assertSame(5, $audit->getNewValuesArray()['total_boxes']);
        $this->get(route('export.delivery-plans.show', $plan))->assertOk()->assertSee('Customer Ekspor');
    }

    public function test_partial_boxes_unknown_domestic_only_parts_and_duplicates_cannot_be_saved(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 30]);
        PartSetting::create(['part_number' => 'LOCAL-ONLY', 'qty_box' => 10]);
        $this->actingAs($this->operator());
        $this->postJson(route('export.delivery-plans.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.pcs_quantity');
        $this->postJson(route('export.delivery-plans.store'), $this->payload([
            'items' => [['part_number' => 'LOCAL-ONLY', 'pcs_quantity' => 100]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.part_number');
        $this->postJson(route('export.delivery-plans.store'), $this->payload([
            'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => 90], ['part_number' => 'EXP-001', 'pcs_quantity' => 90]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.part_number');
        $this->assertDatabaseCount('export_delivery_plans', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_non_positive_fractional_and_overflowing_quantities_are_rejected(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 1]);
        $this->actingAs($this->operator());
        foreach ([0, -1, 1.5, '1e3', '4294967296'] as $quantity) {
            $this->postJson(route('export.delivery-plans.store'), $this->payload([
                'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => $quantity]],
            ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.pcs_quantity');
        }
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_excel_results_require_explicit_review_before_save(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator());
        $this->postJson(route('export.delivery-plans.store'), $this->payload(['source_type' => 'excel']))
            ->assertUnprocessable()->assertJsonValidationErrors('reviewed');
        $this->post(route('export.delivery-plans.store'), $this->payload([
            'source_type' => 'excel', 'source_name' => 'rencana.xlsx', 'reviewed' => '1',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('export_delivery_plans', ['source_type' => 'excel', 'source_name' => 'rencana.xlsx']);
    }

    public function test_photo_source_is_rejected_for_new_plans_and_existing_non_photo_plans(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator());
        $this->postJson(route('export.delivery-plans.store'), $this->payload([
            'source_type' => 'photo', 'reviewed' => '1',
        ]))->assertUnprocessable()->assertJsonValidationErrors('source_type');
        $this->assertDatabaseCount('export_delivery_plans', 0);
        $this->post(route('export.delivery-plans.store'), $this->payload())->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::sole();
        $this->putJson(route('export.delivery-plans.update', $plan), $this->payload([
            'source_type' => 'photo', 'reviewed' => '1', 'revision' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('source_type');
        $this->assertSame('manual', $plan->fresh()->source_type);
        $this->assertSame(1, $plan->fresh()->revision);
    }

    public function test_historical_photo_plan_can_be_viewed_and_edited_without_losing_provenance(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator())->post(route('export.delivery-plans.store'), $this->payload());
        $plan = ExportDeliveryPlan::sole();
        $plan->update(['source_type' => 'photo', 'source_name' => 'historis.jpg']);
        $this->get(route('export.delivery-plans.show', $plan))->assertOk()->assertSee('foto (riwayat)');
        $this->get(route('export.delivery-plans.edit', $plan))->assertOk()
            ->assertDontSee('Dari foto')->assertDontSee('plan-photo-file');
        $this->put(route('export.delivery-plans.update', $plan), $this->payload([
            'source_type' => 'photo', 'source_name' => 'historis.jpg', 'reviewed' => '1', 'revision' => 1,
            'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => 120]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('photo', $plan->fresh()->source_type);
        $this->assertSame('historis.jpg', $plan->fresh()->source_name);
        $this->assertSame(120, $plan->fresh()->total_pcs);
    }

    public function test_update_keeps_capacity_snapshot_and_rejects_stale_revisions_atomically(): void
    {
        $part = ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator())->post(route('export.delivery-plans.store'), $this->payload())->assertRedirect();
        $plan = ExportDeliveryPlan::sole();
        $part->update(['qty_box' => 30]);
        $this->put(route('export.delivery-plans.update', $plan), $this->payload([
            'revision' => 1, 'items' => [['part_number' => 'EXP-001', 'pcs_quantity' => 140]],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(7, $plan->fresh()->total_boxes);
        $this->assertSame(2, $plan->fresh()->revision);
        $this->assertSame(20, $plan->items()->first()->pcs_per_box);
        $this->putJson(route('export.delivery-plans.update', $plan), $this->payload(['revision' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame(140, $plan->fresh()->total_pcs);
        $this->assertSame(2, AuditLog::where('type', 'export_delivery_plan')->count());
    }

    public function test_current_master_is_checked_again_at_save_after_preview(): void
    {
        $part = ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator())->get(route('export.delivery-plans.create'))->assertOk();
        $part->update(['qty_box' => 30]);
        $this->postJson(route('export.delivery-plans.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.pcs_quantity');
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_archive_keeps_history_and_prevents_master_identity_change_or_delete(): void
    {
        $part = ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator())->post(route('export.delivery-plans.store'), $this->payload())->assertRedirect();
        $plan = ExportDeliveryPlan::sole();
        $this->put(route('export.part-settings.update', $part), ['part_number' => 'RENAMED', 'qty_box' => 20])
            ->assertSessionHas('error');
        $this->delete(route('export.part-settings.destroy', $part))->assertSessionHas('error');
        $this->delete(route('export.delivery-plans.destroy', $plan), ['revision' => 1])->assertRedirect();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $plan->id]);
        $this->assertDatabaseCount('export_delivery_plan_items', 1);
        $this->get(route('export.delivery-plans.show', $plan))->assertNotFound();
        $this->delete(route('export.part-settings.destroy', $part))->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('type', 'export_delivery_plan')->where('action', 'deleted')->count());
    }

    public function test_excel_upload_only_previews_rows_and_metadata_without_creating_records(): void
    {
        $file = $this->workbook([
            ['Customer', 'Customer A'], ['Batas Tanggal Pengiriman', '2026-11-15'], [],
            ['Nomor Part', 'Jumlah PCS', 'Qty/Box', 'Box'], ['EXP-001', 100, 999, 999],
        ]);
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.import-excel'), ['file' => $file])
            ->assertOk()->assertJsonPath('customer_name', 'Customer A')
            ->assertJsonPath('delivery_deadline', '2026-11-15')
            ->assertJsonPath('source_name', $file->getClientOriginalName())
            ->assertJsonPath('items.0.part_number', 'EXP-001')->assertJsonPath('items.0.pcs_quantity', 100);
        $this->assertDatabaseCount('export_delivery_plans', 0);
        $this->assertDatabaseCount('part_settings', 0);
    }

    public function test_excel_parser_handles_fixed_layout_with_two_header_rows_and_ignores_totals(): void
    {
        $data = app(ExportDeliveryPlanImportService::class)->parseRows([
            ['RENCANA DELIVERY'], ['SENIN, 28 SEPTEMBER 2026'], ['CUSTOMER : CUSTOMER A'],
            ['No', 'Part No', 'RENCANA DELIVERY'], ['', '', 'Qty(Pcs)', 'Qty/Bx', 'Bx'],
            [1, '001-ABC', 7500, 1500, 5], ['TOTAL BOX', '', '', '', 5],
        ]);
        $this->assertSame('2026-09-28', $data['delivery_deadline']);
        $this->assertSame('CUSTOMER A', $data['customer_name']);
        $this->assertSame([['part_number' => '001-ABC', 'pcs_quantity' => 7500]], $data['items']);
    }

    public function test_excel_upload_rejects_other_file_types_and_multiple_customers(): void
    {
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.import-excel'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $file = $this->workbook([
            ['Customer', 'A'], ['Nomor Part', 'Jumlah PCS'], ['P-1', 100], ['Customer', 'B'], ['P-2', 100],
        ]);
        $this->postJson(route('export.delivery-plans.import-excel'), ['file' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_excel_import_uses_original_pcs_column_reads_code_and_warns_about_skipped_rows(): void
    {
        $file = $this->workbook([
            ['RENCANA DELIVERY', null, null, null, null, null, null, null, null, null, null, null, 'RDYR0925'],
            ['JUMAT,25 SEPTEMBER 2026'], ['Customer : Customer A'],
            ['No', 'Part No', 'QTY(PCS)', 'RENCANA DELIVERY'],
            [null, null, null, 'Qty2(Pcs)', 'RABU', 'Box', 'Qty/Bx', 'Bx'],
            [1, '001-ABC', 3000, 1500, 1500, null, 1500, 1],
            [2, 'EMPTY-PART', null, 999], [3, 'ZERO-PART', 0, 999],
            [4, 'SPACE-PART', '  ', 999], [5, 'TEXT-ZERO-PART', '0', 999],
            ['TOTAL BOX', null, null, null, null, null, 999],
            [6, 'AFTER-TOTAL', 999],
        ]);
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.import-excel'), ['file' => $file])
            ->assertOk()->assertJsonPath('plan_code', 'RDYR0925')
            ->assertJsonPath('customer_name', 'Customer A')->assertJsonPath('delivery_deadline', '2026-09-25')
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.part_number', '001-ABC')
            ->assertJsonPath('items.0.pcs_quantity', 3000)
            ->assertJsonPath('skipped_rows.empty', 2)->assertJsonPath('skipped_rows.zero', 2)
            ->assertJsonPath('warnings.0', '4 baris diabaikan: 2 jumlah kosong dan 2 bernilai 0. Periksa kembali daftar part yang dimasukkan.');
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_import_keeps_invalid_quantities_for_correction_instead_of_silently_skipping_them(): void
    {
        $parsed = app(ExportDeliveryPlanImportService::class)->parseRows([
            ['Kode Rencana Delivery', 'CUSTOM-001'], ['Nomor Part', 'Jumlah PCS'],
            ['NEGATIVE', -1], ['FORMULA', '=1+1'], ['FRACTION', 1.5],
        ]);
        $this->assertSame('CUSTOM-001', $parsed['plan_code']);
        $this->assertCount(3, $parsed['items']);
        $this->assertSame(['empty' => 0, 'zero' => 0], $parsed['skipped_rows']);
        $this->assertSame([], $parsed['warnings']);
    }

    public function test_import_with_only_empty_and_zero_quantities_reports_skipped_counts(): void
    {
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.import-excel'), [
            'file' => $this->workbook([['Nomor Part', 'Jumlah PCS'], ['EMPTY', null], ['ZERO', 0]]),
        ])->assertUnprocessable()->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Tidak ada baris dengan jumlah PCS yang dapat diimpor. 1 baris jumlah kosong dan 1 baris bernilai 0 diabaikan.');
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_plan_code_is_saved_audited_searchable_and_editable_while_legacy_plans_remain_valid(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator());
        $this->post(route('export.delivery-plans.store'), $this->payload())->assertSessionHasNoErrors();
        $legacy = ExportDeliveryPlan::sole();
        $this->assertNull($legacy->plan_code);
        $this->post(route('export.delivery-plans.store'), $this->payload(['plan_code' => ' RDYR0925 ']))
            ->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::latest('id')->first();
        $this->assertSame('RDYR0925', $plan->plan_code);
        $audit = AuditLog::where('type', 'export_delivery_plan')->where('model_id', $plan->id)->sole();
        $this->assertSame('RDYR0925', $audit->getNewValuesArray()['plan_code']);
        $this->get(route('export.delivery-plans.index', ['q' => 'RDYR0925']))->assertOk()
            ->assertViewHas('plans', fn ($plans) => $plans->total() === 1 && $plans->first()->id === $plan->id);
        $this->get(route('export.delivery-plans.show', $plan))->assertOk()->assertSee('RDYR0925');
        $this->get(route('export.delivery-plans.edit', $plan))->assertOk()->assertSee('RDYR0925');
        $this->put(route('export.delivery-plans.update', $plan), $this->payload(['revision' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame('RDYR0925', $plan->fresh()->plan_code);
        $this->put(route('export.delivery-plans.update', $plan), $this->payload(['revision' => 2, 'plan_code' => 'RDYR0926']))
            ->assertSessionHasNoErrors();
        $this->assertSame('RDYR0926', $plan->fresh()->plan_code);
        $this->postJson(route('export.delivery-plans.store'), $this->payload(['plan_code' => str_repeat('R', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('plan_code');
    }

    public function test_import_rejects_multiple_plan_codes_in_one_document(): void
    {
        $this->actingAs($this->operator())->postJson(route('export.delivery-plans.import-excel'), [
            'file' => $this->workbook([['RDYR0925', 'RDYR0926'], ['Nomor Part', 'Jumlah PCS'], ['P-1', 100]]),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
