<?php

namespace Tests\Feature;

use App\Exports\OperationalDashboardExport;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportPartSetting;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportFulfillmentCompletionService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationNoticeService;
use App\Services\ExportStockReceivingService;
use App\Services\OperationalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class OperationalDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $monitor;

    private User $operator;

    private ExportPartSetting $part;

    private int $sequence = 0;

    private int $plans = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'Asia/Jakarta'));
        $this->monitor = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->part = ExportPartSetting::create(['part_number' => 'SAME-PART', 'qty_box' => 10]);
        $this->actingAs($this->monitor);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function data(array $filters = [], bool $all = false): array
    {
        return app(OperationalDashboardService::class)->build(Request::create('/', 'GET', $filters + ['period' => 'today']), $all);
    }

    private function domestic(): DeliveryOrder
    {
        PartSetting::create(['part_number' => 'SAME-PART', 'qty_box' => 100]);
        MasterLocation::create(['code' => 'DOM-A']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-DOM']);
        StockLocation::create(['pallet_id' => $pallet->id, 'warehouse_location' => 'DOM-A', 'stored_at' => now()]);
        $box = Box::create(['box_number' => '00000001', 'part_number' => 'SAME-PART', 'pcs_quantity' => 100, 'qty_box' => 100, 'qr_code' => 'DOM-1', 'user_id' => $this->monitor->id, 'expired_status' => 'active']);
        $pallet->boxes()->attach($box->id);
        StockInput::create(['pallet_id' => $pallet->id, 'user_id' => $this->monitor->id, 'warehouse_location' => 'DOM-A', 'pcs_quantity' => 100, 'box_quantity' => 1, 'stored_at' => now()->setTime(8, 0), 'part_numbers' => ['SAME-PART']]);
        StockWithdrawal::create(['user_id' => $this->monitor->id, 'part_number' => 'SAME-PART', 'pcs_quantity' => 30, 'box_quantity' => 1, 'withdrawn_at' => now()->setTime(9, 0), 'status' => 'completed', 'warehouse_location' => 'DOM-A']);
        $order = DeliveryOrder::create(['sales_user_id' => $this->monitor->id, 'customer_name' => 'DOM CUSTOMER', 'delivery_date' => today(), 'status' => 'processing']);
        $order->items()->create(['part_number' => 'SAME-PART', 'quantity' => 100, 'fulfilled_quantity' => 40]);

        return $order;
    }

    private function plan(array $counts = [2, 1]): ExportDeliveryPlan
    {
        $code = 'DASH-EX-'.++$this->plans;
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => 'EXPORT CUSTOMER', 'delivery_deadline' => '2026-10-30', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => array_sum($counts) * 10]]], $this->operator);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize', 'pallets' => array_map(fn ($n, $i) => [
            'master_location_id' => ExportMasterLocation::create(['code' => $code.'-'.$i])->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => $n]]], $counts, array_keys($counts))], $this->operator);
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id, $notice->id, 1, $this->operator);

        return $delivery->fresh('palletPlan.pallets');
    }

    private function receive(bool $place = true): ExportStockBox
    {
        $service = app(ExportStockReceivingService::class);
        $box = $service->receive(['box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        if ($place) {
            $service->place($box->id, ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()], $this->operator);
        }

        return $box->fresh();
    }

    public function test_default_dashboard_separates_identical_ids_units_and_full_stock_totals(): void
    {
        $this->domestic();
        $this->plan();
        $this->receive();
        $this->receive(false);
        $d = $this->data();
        $this->assertSame(['domestic', 'export'], array_keys($d['domains']));
        $this->assertSame('PCS', $d['domains']['domestic']['unit']);
        $this->assertSame('box', $d['domains']['export']['unit']);
        $this->assertSame(1, (int) $d['stockTotals']['domestic']->boxes);
        $this->assertSame(2, (int) $d['stockTotals']['export']->boxes);
        $this->assertSame(1, (int) $d['stockTotals']['export']->unplaced);
        $this->assertSame(2, $d['stockRows']->where('box_number', '00000001')->count());
        $this->assertSame(100, (int) $d['domains']['domestic']['inbound']->pcs);
        $this->assertSame(30, (int) $d['domains']['domestic']['outbound']->pcs);
        $this->assertSame(2, (int) $d['domains']['export']['inbound']->boxes);
        $this->assertSame(0, (int) $d['domains']['export']['outbound']->boxes);
        $ex = $d['orders']->firstWhere('scope', 'export');
        $this->assertSame(3, (int) $ex->required);
        $this->assertSame(1, (int) $ex->fulfilled);
        $this->assertSame(1, (int) $ex->reserved);
        $this->assertSame(0.0, (float) $d['fulfillmentRate']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Domestik · grafik dalam PCS')->assertSee('Ekspor · grafik dalam box');
    }

    public function test_scope_filters_every_source_and_ajax_has_no_layout_or_external_charts(): void
    {
        $this->domestic();
        $this->plan();
        $this->receive();
        $this->getJson(route('dashboard', ['scope' => 'export', 'period' => 'today']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('title', 'Dashboard Operasional - Warehouse FG');
        $response = $this->getJson(route('dashboard', ['scope' => 'export', 'period' => 'today']));
        $html = $response->json('html');
        $this->assertStringContainsString('EXPORT CUSTOMER', $html);
        $this->assertStringNotContainsString('DOM CUSTOMER', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('code.highcharts.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('@else', $html);
        $this->assertStringNotContainsString('Pemenuhan mengikuti realisasi order domestik', $html);
        foreach (['domestic', 'export'] as $scope) {
            $d = $this->data(['scope' => $scope]);
            $this->assertSame([$scope], array_keys($d['domains']));
            $this->assertTrue($d['stockRows']->every(fn ($r) => $r->scope === $scope));
        }
    }

    public function test_placed_and_ready_pallets_are_not_shipments_but_completed_archive_remains_in_history(): void
    {
        $delivery = $this->plan();
        for ($i = 0; $i < 3; $i++) {
            $this->receive();
        }$service = app(ExportFulfillmentCompletionService::class);
        foreach ($delivery->palletPlan->pallets as $p) {
            $service->ready($delivery->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'location_id' => $p->master_location_id], $this->operator);
        }
        $before = $this->data(['scope' => 'export']);
        $this->assertSame(100.0, (float) $before['fulfillmentRate']);
        $this->assertSame(0, (int) $before['domains']['export']['outbound']->boxes);
        $this->assertSame(2, (int) $before['orders']->sole()->ready_pallets);
        Carbon::setTestNow(now()->addHour());
        $service->complete($delivery->id, ['revision' => 1, 'request_token' => (string) Str::uuid()], $this->operator);
        $after = $this->data(['scope' => 'export']);
        $this->assertSame(0, (int) $after['stockTotals']['export']->boxes);
        $this->assertSame(3, (int) $after['domains']['export']['outbound']->boxes);
        $this->assertSame(3, (int) $after['domains']['export']['inbound']->boxes);
        $this->assertSame('shipped', $after['orders']->sole()->status);
        $this->assertSame(100.0, (float) $after['fulfillmentRate']);
        $this->assertSame(3, $after['domains']['export']['charts'][1]['rows'][11]['second']);
        $this->assertSame(60.0, (float) $after['processing']->sole()->duration_min);
        $this->assertNull($after['processing']->sole()->scan_duration_min);
    }

    public function test_revision_return_is_not_new_inbound_and_cancellation_is_not_outbound_or_demand(): void
    {
        $delivery = $this->plan([4]);
        for ($i = 0; $i < 4; $i++) {
            $this->receive();
        }$p = $delivery->palletPlan->pallets->sole();
        $service = app(ExportFulfillmentCompletionService::class);
        $preview = $service->reduction($p, [$p->items()->sole()->id => 2]);
        $loc = ExportMasterLocation::create(['code' => 'DASH-RETURN']);
        $service->reduce($delivery->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'quantities' => $preview['quantities'],
            'fingerprint' => $preview['fingerprint'], 'destination' => 'location:'.$loc->id, 'return_confirmed' => 1], $this->operator);
        $d = $this->data(['scope' => 'export']);
        $this->assertSame(4, (int) $d['domains']['export']['inbound']->boxes);
        $this->assertSame(2, (int) $d['orders']->sole()->required);
        $delivery->refresh();
        $preview = $service->cancellation($delivery);
        $loc = ExportMasterLocation::create(['code' => 'DASH-CANCEL']);
        $service->cancel($delivery->id, ['revision' => 2, 'request_token' => (string) Str::uuid(), 'fingerprint' => $preview['fingerprint'], 'reason' => 'Customer cancel',
            'destination' => 'location:'.$loc->id, 'return_confirmed' => 1], $this->operator);
        $d = $this->data(['scope' => 'export']);
        $this->assertSame(4, (int) $d['stockTotals']['export']->boxes);
        $this->assertSame(0, (int) $d['domains']['export']['outbound']->boxes);
        $this->assertCount(0, $d['orders']);
    }

    public function test_never_final_draft_is_not_fulfillment_and_date_filters_are_inclusive(): void
    {
        app(ExportDeliveryPlanService::class)->save(['plan_code' => 'DRAFT-ONLY', 'customer_name' => 'Draft', 'delivery_deadline' => '2026-10-30', 'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 10]]], $this->operator);
        $this->assertCount(0, $this->data(['scope' => 'export'])['orders']);
        $this->plan([1]);
        $this->receive();
        $same = $this->data(['scope' => 'export', 'start_date' => '2026-10-30', 'end_date' => '2026-10-30']);
        $this->assertCount(1, $same['orders']);
        $later = $this->data(['scope' => 'export', 'start_date' => '2026-10-31', 'end_date' => '2026-10-31']);
        $this->assertCount(0, $later['orders']);
        $this->assertSame(0, (int) $later['domains']['export']['inbound']->boxes);
        $this->assertSame(1, (int) $later['stockTotals']['export']->boxes);
    }

    public function test_all_time_open_ranges_and_grouping_keep_units_and_activity_visible(): void
    {
        $this->plan([1]);
        $this->receive();
        foreach (['day', 'week', 'month'] as $group) {
            $d = $this->data(['scope' => 'export', 'period' => 'all', 'group_by' => $group]);
            $this->assertSame(1, array_sum(array_column($d['domains']['export']['charts'][0]['rows'], 'first')));
            $this->assertCount(1, $d['domains']['export']['charts'][0]['rows']);
        }
        $this->assertSame(1, (int) $this->data(['scope' => 'export', 'start_date' => '2026-10-30'])['domains']['export']['inbound']->boxes);
    }

    public function test_scope_and_filter_validation_returns_422_without_inventory_mutations(): void
    {
        $this->plan([1]);
        $this->receive();
        $before = ExportStockBox::first()->getAttributes();
        $logs = AuditLog::count();
        foreach ([['scope' => 'wrong'], ['scope' => ['export']], ['start_date' => 'bad'], ['start_date' => '2026-11-02', 'end_date' => '2026-11-01'], ['group_by' => 'bad'], ['audit_user' => 'abc']] as $filters) {
            $this->getJson(route('dashboard', $filters))->assertUnprocessable();
        }
        $this->assertSame($before, ExportStockBox::first()->getAttributes());
        $this->assertSame($logs, AuditLog::count());
    }

    public function test_admin_and_supervisor_use_dashboard_but_other_roles_and_revocations_keep_access_boundaries(): void
    {
        foreach (['admin', 'supervisi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('dashboard'))->assertOk()->assertViewIs('shared.operational-dashboard');
        }
        $this->actingAs($this->operator)->get(route('dashboard', ['scope' => 'all']))->assertOk()->assertViewIs('export.dashboard');
        $this->get(route('reports.operational.export', ['scope' => 'export']))->assertForbidden();
        RoleFeatureAccess::create(['role' => 'supervisi', 'permissions' => []]);
        $this->actingAs(User::factory()->create(['role' => 'supervisi']));
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-report-export', false);
        $this->get(route('reports.operational.export', ['scope' => 'all']))->assertForbidden();
    }

    public function test_excel_uses_scope_filters_has_native_unit_specific_charts_and_safe_text(): void
    {
        $delivery = $this->plan([1]);
        $delivery->update(['customer_name' => '=1+1']);
        $this->receive();
        $d = $this->data(['scope' => 'export'], true);
        $export = new OperationalDashboardExport($d);
        $bytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'dashboard-xlsx-');
        file_put_contents($file, $bytes);
        $zip = new \ZipArchive;
        $zip->open($file);
        $charts = [];
        $sheets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('~^xl/charts/chart\d+\.xml$~', $name)) {
                $charts[] = $zip->getFromName($name);
            }if (preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name)) {
                $sheets[] = $zip->getFromName($name);
            }
        }
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($file);
        $this->assertCount(3, $charts);
        $this->assertStringContainsString('box', implode('', $charts));
        $this->assertStringContainsString('00000001', $strings);
        $this->assertStringContainsString('=1+1', $strings);
        $this->assertStringNotContainsString('<f>', implode('', $sheets));
        $this->assertMatchesRegularExpression('/<c r="J2"[^>]*><v>0<\/v><\/c>/', $summary);
        Excel::fake();
        $this->get(route('reports.operational.export', ['scope' => 'export', 'period' => 'today']))->assertOk();
        Excel::assertDownloaded('dashboard_export_20261030_100000.xlsx', fn (OperationalDashboardExport $e) => array_keys($e->data['domains']) === ['export']);
    }

    public function test_stock_kpi_is_not_limited_to_the_preview_and_excel_includes_all_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 205; $i++) {
            $rows[] = ['box_number' => str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'part_setting_id' => $this->part->id,
                'part_number' => $this->part->part_number, 'pcs_quantity' => 10, 'state' => 'received',
                'receive_token' => (string) Str::uuid(), 'first_received_at' => now(), 'received_by' => $this->operator->id,
                'created_at' => now(), 'updated_at' => now()];
        }
        ExportStockBox::insert($rows);
        $d = $this->data(['scope' => 'export']);
        $this->assertCount(200, $d['stockRows']);
        $this->assertSame(205, (int) $d['stockTotals']['export']->boxes);
        $this->assertSame(2050, (int) $d['stockTotals']['export']->pcs);
        $this->assertSame(205, (int) $d['stockTotals']['export']->unplaced);
        $export = new OperationalDashboardExport($this->data(['scope' => 'export'], true));
        $this->assertCount(205, $export->sheets()[2]->array());
    }

    public function test_domestic_shared_positions_and_legacy_stock_do_not_double_count_box_totals(): void
    {
        $this->domestic();
        $shared = Pallet::create(['pallet_number' => 'DOM-SHARED']);
        StockLocation::create(['pallet_id' => $shared->id, 'warehouse_location' => 'DOM-B', 'stored_at' => now()]);
        $shared->boxes()->attach(Box::sole()->id);
        // Stale legacy quantities on a pallet with box history must stay excluded.
        $shared->items()->create(['part_number' => 'SAME-PART', 'pcs_quantity' => 900, 'box_quantity' => 9]);
        $legacy = Pallet::create(['pallet_number' => 'DOM-LEGACY']);
        StockLocation::create(['pallet_id' => $legacy->id, 'warehouse_location' => 'DOM-C', 'stored_at' => now()]);
        $legacy->items()->create(['part_number' => 'SAME-PART', 'pcs_quantity' => 200, 'box_quantity' => 2]);
        $d = $this->data(['scope' => 'domestic']);
        $this->assertSame(3, (int) $d['stockTotals']['domestic']->boxes);
        $this->assertSame(300, (int) $d['stockTotals']['domestic']->pcs);
        $this->assertSame(3, (int) $d['stockTotals']['domestic']->pallets);
        $this->assertCount(3, $d['palletRows']);
        $this->assertSame('multiple_locations', $d['stockRows']->firstWhere('record_kind', 'box')->status);
        $this->assertNull($d['stockRows']->firstWhere('record_kind', 'legacy')->box_number);
    }

    public function test_fulfillment_rate_counts_requests_not_mixed_units_and_excludes_split_parents(): void
    {
        $parent = $this->domestic();
        $child = DeliveryOrder::create(['sales_user_id' => $this->monitor->id, 'customer_name' => 'CHILD',
            'delivery_date' => today(), 'status' => 'processing', 'parent_delivery_order_id' => $parent->id]);
        $child->items()->create(['part_number' => 'SAME-PART', 'quantity' => 200, 'fulfilled_quantity' => 200]);
        $this->plan([1]);
        $d = $this->data();
        $this->assertCount(2, $d['orders']);
        $this->assertSame(1, (int) $d['orderSummary']->full);
        $this->assertSame(50.0, (float) $d['fulfillmentRate']);
        $this->assertSame(200, array_sum(array_column($d['domains']['domestic']['charts'][2]['rows'], 'first')));
        $this->assertSame(1, array_sum(array_column($d['domains']['export']['charts'][2]['rows'], 'first')));
        $this->assertSame('export', $this->data(['fulfillment_filter' => 'partial'])['orders']->sole()->scope);
        $this->assertSame('domestic', $this->data(['fulfillment_filter' => 'full'])['orders']->sole()->scope);
    }

    public function test_audit_scope_pagination_and_closed_input_errors_are_kept_separate(): void
    {
        $order = $this->domestic();
        $session = $order->pickSessions()->create(['created_by' => $this->monitor->id, 'started_at' => now()]);
        $session->issues()->create(['scanned_code' => '00000001', 'issue_type' => 'scan_mismatch', 'status' => 'pending']);
        foreach (['pending', 'awaiting_return', 'returned', 'input_error'] as $status) {
            \App\Models\ExportPickingIssue::create(['box_number' => '00000001', 'issue_type' => 'unknown_box',
                'status' => $status, 'context' => [], 'reported_by' => $this->operator->id, 'registration_token' => (string) Str::uuid()]);
        }
        // Isolate audit fixtures from setup audits. Master events identify scope through their snapshot.
        AuditLog::query()->delete();
        for ($i = 0; $i < 25; $i++) {
            AuditLog::create(['type' => 'part_setting', 'action' => 'updated', 'model' => 'PartSetting',
                'user_id' => $this->operator->id, 'new_values' => json_encode(['scope' => 'export']), 'description' => 'EX-AUDIT-'.$i]);
        }
        AuditLog::create(['type' => 'export_picking', 'action' => 'input_error_closed', 'model' => 'ExportPickingIssue', 'user_id' => $this->monitor->id]);
        AuditLog::create(['type' => 'stock_input', 'action' => 'created', 'new_values' => 'old malformed JSON', 'user_id' => $this->monitor->id]);
        AuditLog::create(['type' => 'user_management', 'action' => 'updated', 'user_id' => $this->monitor->id]);
        $ex = $this->data(['scope' => 'export']);
        $this->assertSame(4, (int) $ex['issueSummary']->total);
        $this->assertSame(2, (int) $ex['issueSummary']->open);
        $this->assertSame(26, $ex['auditLogs']->total());
        $this->assertCount(20, $ex['auditLogs']);
        $this->assertCount(6, $this->data(['scope' => 'export', 'audit_page' => 2])['auditLogs']);
        $filtered = $this->data(['scope' => 'export', 'audit_type' => 'part_setting', 'audit_action' => 'updated', 'audit_user' => $this->operator->id], true);
        $this->assertCount(25, $filtered['auditLogs']);
        $this->assertTrue($filtered['auditLogs']->every(fn ($r) => $r->scope === 'export' && ! property_exists($r, 'new_values')));
        $dom = $this->data(['scope' => 'domestic']);
        $this->assertSame(1, (int) $dom['issueSummary']->open);
        $this->assertSame(1, $dom['auditLogs']->total());
        $this->assertSame(28, $this->data()['auditLogs']->total());
        $this->getJson(route('dashboard', ['scope' => 'export', 'period' => 'today', 'audit_page' => 2]))->assertOk()
            ->assertSee('EX-AUDIT-0')->assertDontSee('old malformed JSON');
    }
}
