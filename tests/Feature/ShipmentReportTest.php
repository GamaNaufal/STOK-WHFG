<?php

namespace Tests\Feature;

use App\Exports\ShipmentReportExport;
use App\Models\{AuditLog, Box, DeliveryOrder, DeliveryPickSession, Pallet, PartSetting, RoleFeatureAccess, StockWithdrawal, User};
use App\Services\ShipmentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ShipmentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $this->operator = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function local(string $status = 'completed', ?DeliveryOrder $order = null, bool $withdrawals = true): DeliveryPickSession
    {
        $order ??= DeliveryOrder::create(['sales_user_id' => $this->operator->id, 'customer_name' => '=Customer 001',
            'delivery_date' => '2026-11-01', 'status' => 'completed', 'notes' => 'Catatan delivery lengkap']);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $this->operator->id,
            'status' => 'completed', 'completion_status' => $status === 'reversed' ? 'redone' : 'completed',
            'started_at' => now()->subHour(), 'completed_at' => now(), 'redo_until' => now()->subDay(), 'approval_notes' => 'Catatan approval lengkap']);
        foreach (['001-A' => 10, '001-a' => 20] as $part => $pcs) {
            $number = str_pad((string) (Box::withTrashed()->count() + 1), 8, '0', STR_PAD_LEFT);
            $box = Box::create(['box_number' => $number, 'part_number' => $part, 'pcs_quantity' => $pcs,
                'qr_code' => $number.'|'.$part.'|'.$pcs, 'user_id' => $this->operator->id, 'is_withdrawn' => true]);
            $box->delete();
            $pallet = Pallet::create(['pallet_number' => 'PALLET-'.$number]);
            $item = $pallet->items()->create(['part_number' => $part, 'pcs_quantity' => $pcs, 'box_quantity' => 1]);
            $session->items()->create(['box_id' => $box->id, 'part_number' => $part, 'pcs_quantity' => $pcs,
                'status' => 'scanned', 'scanned_at' => now()->subMinutes(5), 'scanned_by' => $this->operator->id]);
            if ($withdrawals) StockWithdrawal::create(['pick_session_id' => $session->id, 'withdrawal_batch_id' => (string) Str::uuid(),
                'user_id' => $this->operator->id, 'box_id' => $box->id, 'pallet_item_id' => $item->id, 'part_number' => $part,
                'pcs_quantity' => $pcs, 'box_quantity' => 1, 'warehouse_location' => 'LOCATION-'.$number, 'status' => $status,
                'withdrawn_at' => now(), 'notes' => '=Catatan box lengkap '.str_repeat('keterangan ', 12)]);
        }
        return $session;
    }

    private function filters(array $extra = []): array
    {
        return app(ShipmentReportService::class)->filters(\Illuminate\Http\Request::create('/reports/withdrawal', 'GET', $extra));
    }

    public function test_local_report_groups_whole_shipments_and_redo_separately_without_double_counting(): void
    {
        $old = $this->local('reversed');
        $current = $this->local(order: $old->order);
        $service = app(ShipmentReportService::class);
        $rows = $service->query($this->filters())->get();
        $this->assertCount(1, $rows);
        $this->assertSame('local-s'.$current->id, $rows->sole()->event_key);
        $this->assertSame(30, (int) $rows->sole()->pcs_quantity);
        $this->assertSame(2, (int) $rows->sole()->part_count);
        $summary = $service->summary('domestic');
        $this->assertSame(1, (int) $summary->completed_deliveries);
        $this->assertSame(30, (int) $summary->shipped_pcs);
        $this->assertSame(2, (int) $summary->shipped_boxes);
        $this->assertSame(1, (int) $summary->redo_events);
        $this->get(route('reports.withdrawal'))->assertOk()->assertSee('Laporan Pengiriman')->assertSee('Delivery selesai')
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get(route('reports.withdrawal', ['status' => 'reversed']))->assertOk()->assertViewHas('rows', fn ($r) => $r->first()->event_key === 'local-s'.$old->id);
        $this->get(route('reports.withdrawal', ['status' => '']))->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 2);
    }

    public function test_part_box_operator_and_date_filters_select_whole_event_and_same_excel_rows(): void
    {
        $session = $this->local();
        $service = app(ShipmentReportService::class);
        foreach (['part_number' => '001-A', 'q' => '00000001', 'warehouse_location' => 'LOCATION-00000001', 'user_id' => $this->operator->id] as $key => $value) {
            $filters = $this->filters([$key => $value]);
            $this->assertSame(1, $service->query($filters)->count());
            $this->assertSame(2, $service->selectedLines($filters)->count());
            $this->assertSame(30, (int) $service->query($filters)->sole()->pcs_quantity);
        }
        $this->assertSame(1, $service->query($this->filters(['start_date' => '2026-11-13', 'end_date' => '2026-11-13']))->count());
        $this->assertSame(0, $service->query($this->filters(['end_date' => '2026-11-01']))->count());
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id, 'part_number' => '001-A']))
            ->assertOk()->assertSee('001-A')->assertSee('001-a')->assertSee('00000001')->assertSee('00000002')
            ->assertSee('Catatan delivery lengkap')->assertSee('Catatan approval lengkap')->assertSee('Catatan box lengkap');
    }

    public function test_legacy_batch_and_partial_box_quantities_remain_visible_without_inventing_delivery_links(): void
    {
        $batch = (string) Str::uuid();
        foreach ([10, 20] as $pcs) StockWithdrawal::create(['withdrawal_batch_id' => $batch, 'user_id' => $this->operator->id,
            'part_number' => 'LEGACY', 'pcs_quantity' => $pcs, 'box_quantity' => .25, 'warehouse_location' => 'OLD',
            'status' => 'completed', 'withdrawn_at' => now()]);
        $service = app(ShipmentReportService::class);
        $row = $service->query($this->filters())->sole();
        $this->assertSame(.5, (float) $row->box_quantity);
        $this->assertSame(30, (int) $row->pcs_quantity);
        $this->assertNull($row->order_id);
        $this->assertSame(0, (int) $service->summary('domestic')->completed_deliveries);
        $this->get(route('reports.shipments.show', ['shipment' => $row->event_key]))->assertOk()->assertSee('belum memiliki hubungan delivery');
    }

    public function test_completed_session_without_withdrawals_remains_visible_and_purge_keeps_history(): void
    {
        $session = $this->local(withdrawals: false);
        $this->assertSame(30, (int) app(ShipmentReportService::class)->query($this->filters())->sole()->pcs_quantity);
        Artisan::call('delivery:purge-completions');
        $this->assertDatabaseHas('delivery_pick_sessions', ['id' => $session->id]);
        $this->assertSame(2, $session->items()->count());
        $session->order->delete();
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id]))->assertOk()->assertSee('00000001');
    }

    public function test_reports_detail_and_exports_have_independent_permissions_and_do_not_mutate_stock(): void
    {
        $session = $this->local();
        $before = [AuditLog::count(), StockWithdrawal::count(), Box::withTrashed()->count()];
        $reader = User::factory()->create(['role' => 'sales']);
        RoleFeatureAccess::create(['role' => 'sales', 'permissions' => ['reports'], 'permissions_version' => 2]);
        $this->actingAs($reader)->get(route('reports.withdrawal'))->assertOk();
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id]))->assertOk();
        $this->get(route('reports.shipments.export', ['shipment' => 'local-s'.$session->id]))->assertForbidden();
        $this->get(route('reports.withdrawal.export'))->assertForbidden();
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id, 'scope' => 'export']))->assertNotFound();
        $this->assertSame($before, [AuditLog::count(), StockWithdrawal::count(), Box::withTrashed()->count()]);
    }

    public function test_three_sheet_workbook_reconciles_counts_preserves_case_zeroes_formulas_and_actual_dates(): void
    {
        $session = $this->local();
        $filters = $this->filters(['q' => '00000001']);
        $bytes = Excel::raw(new ShipmentReportExport($filters), \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'shipment-');
        file_put_contents($file, $bytes);
        try {
            $book = IOFactory::load($file);
            $this->assertSame(['Ringkasan Delivery', 'Rincian Part', 'Rincian Box'], $book->getSheetNames());
            $summary = $book->getSheet(0); $parts = $book->getSheet(1); $boxes = $book->getSheet(2);
            $this->assertSame(2, $summary->getHighestRow());
            $this->assertSame(3, $parts->getHighestRow());
            $this->assertSame(3, $boxes->getHighestRow());
            $this->assertSame(30, (int) $summary->getCell('M2')->getValue());
            $this->assertSame(30, (int) $parts->getCell('J2')->getValue() + (int) $parts->getCell('J3')->getValue());
            $this->assertSame('00000001', $boxes->getCell('G2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $boxes->getCell('G2')->getDataType());
            $this->assertSame('=Customer 001', $summary->getCell('D2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $summary->getCell('D2')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $summary->getCell('E2')->getDataType());
            $this->assertSame(DataType::TYPE_STRING, $boxes->getCell('U2')->getDataType());
            $this->assertStringContainsString('Catatan box lengkap', $boxes->getCell('U2')->getValue());
            $this->assertSame('A2', $boxes->getFreezePane());
            $this->assertSame('Penggunaan ID Box', $boxes->getCell('X1')->getValue());
            $this->assertSame(1, (int) $boxes->getCell('X2')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $boxes->getCell('X2')->getDataType());
        } finally { unlink($file); }
        $this->get(route('reports.shipments.export', ['shipment' => 'local-s'.$session->id]))->assertOk();
        $nextWorkbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $nextWorkbook->getActiveSheet()->setCellValue('B1', 100);
        $this->assertSame(DataType::TYPE_NUMERIC, $nextWorkbook->getActiveSheet()->getCell('B1')->getDataType());
    }

    public function test_export_shipments_include_archived_delivery_and_snapshot_box_and_document_details(): void
    {
        $part = PartSetting::create(['part_number' => 'LABEL', 'qty_box' => 10]);
        $delivery = \App\Models\ExportDeliveryPlan::create(['plan_code' => 'EXPORT-DONE', 'customer_name' => 'Export Customer',
            'delivery_deadline' => '2026-11-01', 'status' => 'completed', 'total_pcs' => 10, 'total_boxes' => 1,
            'created_by' => $this->operator->id, 'updated_by' => $this->operator->id]);
        $delivery->items()->create(['part_setting_id' => $part->id, 'position' => 1, 'part_number' => 'LABEL', 'document_part_number' => '001-DOC',
            'pcs_quantity' => 10, 'pcs_per_box' => 10, 'box_quantity' => 1]);
        $plan = $delivery->palletPlan()->create(['status' => 'final', 'delivery_revision' => 1, 'completed_at' => now(),
            'completed_by' => $this->operator->id, 'created_by' => $this->operator->id, 'updated_by' => $this->operator->id]);
        $pallet = $plan->pallets()->create(['position' => 1, 'location_code' => 'EX-LOC']);
        $item = $pallet->items()->create(['part_setting_id' => $part->id, 'part_number' => 'LABEL', 'pcs_per_box' => 10, 'box_quantity' => 1]);
        $item->fulfillmentBoxes()->create(['part_setting_id' => $part->id, 'box_number' => '00123456', 'pcs_quantity' => 10,
            'first_received_at' => now()->subMonth(), 'reserved_at' => now()->subDay(), 'reserved_by' => $this->operator->id]);
        $delivery->delete();
        $this->get(route('reports.withdrawal', ['scope' => 'export']))->assertOk()->assertSee('EXPORT-DONE')
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get(route('reports.shipments.show', ['shipment' => 'export-p'.$plan->id, 'scope' => 'export']))
            ->assertOk()->assertSee('00123456')->assertSee('001-DOC')->assertSee('EX-LOC');
        $service = app(ShipmentReportService::class);
        $this->assertSame(1, (int) $service->summary('export')->completed_deliveries);
        $plan->update(['completed_at' => null]);
        $this->assertSame(0, $service->query($this->filters(['scope' => 'export']))->count());
    }

    public function test_excel_includes_all_filtered_pages_and_delivery_export_contains_only_one_event(): void
    {
        $first = $this->local();
        foreach (range(2, 16) as $index) $this->local();
        $this->get(route('reports.withdrawal'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $export = new ShipmentReportExport($this->filters());
        $this->assertSame(16, $export->sheets()[0]->query()->count());
        $this->assertSame(32, $export->sheets()[2]->query()->count());
        $single = new ShipmentReportExport($this->filters(), 'local-s'.$first->id);
        $this->assertSame(1, $single->sheets()[0]->query()->count());
        $this->assertSame(2, $single->sheets()[2]->query()->count());
    }

    public function test_missing_details_zero_data_invalid_filters_and_ajax_keep_explicit_history_selection(): void
    {
        // Scope/type switches replace only the report body, all report styles must
        // already be available when entering from another report.
        $this->get(route('reports.stock-input'))->assertOk()->assertSee('css/pages/report-shipments.css', false);
        $this->getJson(route('reports.withdrawal'))->assertOk()->assertJsonPath('title', 'Laporan Pengiriman - Warehouse FG Yamato');
        $this->getJson(route('reports.withdrawal', ['scope' => 'all']))->assertUnprocessable();
        $this->getJson(route('reports.withdrawal', ['status' => 'invented']))->assertUnprocessable();
        $this->getJson(route('reports.withdrawal', ['start_date' => '2026-11-13', 'end_date' => '2026-11-01']))->assertUnprocessable();
        $session = $this->local('reversed');
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id]))->assertOk()->assertSee('telah dikembalikan');
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s999999']))->assertNotFound();
        $response = $this->getJson(route('reports.records', ['kind' => 'shipments', 'status' => '', 'q' => 'no-result']));
        $response->assertOk();
        $this->assertStringContainsString('status=', $response->json('html'));
        $this->get(route('reports.withdrawal', ['status' => '', 'q' => 'no-result']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => (int) $summary->redo_events === 1)
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        $session->items()->delete();
        StockWithdrawal::where('pick_session_id', $session->id)->forceDelete();
        $this->get(route('reports.shipments.show', ['shipment' => 'local-s'.$session->id]))->assertOk();
    }
}
