<?php

namespace Tests\Feature;

use App\Models\{ExportMasterLocation, ExportPalletPlan, ExportPartSetting, ExportPlannedPallet, ExportStockBox, ExportStockPallet, MasterLocation, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder};
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportStockFilterExportTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ExportPartSetting $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => '00123-001', 'qty_box' => 100]);
    }

    private function pallet(string $code): ExportStockPallet
    {
        $location = ExportMasterLocation::create(['code' => $code]);
        return ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id,
            'location_code' => $code, 'created_by' => $this->operator->id]);
    }

    private function box(string $number, ?ExportStockPallet $pallet = null, array $extra = []): ExportStockBox
    {
        return ExportStockBox::create($extra + ['box_number' => $number, 'part_setting_id' => $this->part->id,
            'part_number' => $this->part->part_number, 'pcs_quantity' => 100, 'state' => $pallet ? 'stored' : 'received',
            'receive_token' => (string) Str::uuid(), 'first_received_at' => now()->subDay(), 'received_by' => $this->operator->id,
            'current_pallet_id' => $pallet?->id, 'stored_at' => $pallet ? now() : null,
            'stored_by' => $pallet ? $this->operator->id : null]);
    }

    private function plannedPallet(string $code, int $position = 1): ExportStockPallet
    {
        $pallet = $this->pallet($code);
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'RD-'.$code, 'customer_name' => 'Customer',
            'delivery_deadline' => '2026-11-10', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 100]]], $this->operator);
        $plan = ExportPalletPlan::create(['export_delivery_plan_id' => $delivery->id, 'delivery_revision' => 1,
            'created_by' => $this->operator->id, 'updated_by' => $this->operator->id]);
        $planned = ExportPlannedPallet::create(['export_pallet_plan_id' => $plan->id, 'position' => $position,
            'master_location_id' => $pallet->master_location_id, 'location_code' => $code]);
        $pallet->update(['export_planned_pallet_id' => $planned->id]);
        return $pallet;
    }

    private function workbook(array $filters = [])
    {
        $response = $this->get(route('export.stock.export', $filters))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $path = $response->baseResponse->getFile()->getPathname();
        Cell::setValueBinder(new DefaultValueBinder());
        $workbook = IOFactory::load($path);
        $this->beforeApplicationDestroyed(fn () => $workbook->disconnectWorksheets());
        unlink($path);
        return $workbook;
    }

    public function test_physical_filters_combine_with_search_and_status_and_remain_in_pagination(): void
    {
        $first = $this->pallet('EX-A1'); $second = $this->pallet('EX-A2');
        for ($i = 1; $i <= 21; $i++) $this->box(str_pad((string) $i, 8, '0', STR_PAD_LEFT), $first);
        $this->box('00000100', $second); $this->box('00000101');
        $filters = ['location_id' => $first->master_location_id, 'status' => 'spare', 'q' => '00123-001'];
        $response = $this->get(route('export.stock.index', $filters))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 21 && $boxes->count() === 20)
            ->assertSee('Export Excel')->assertSee('id="stock-mode"', false)->assertSee('id="stock-location"', false)
            ->assertDontSee('id="stock-pallet"', false)->assertDontSee('Cari stok</button>', false);
        $this->assertStringContainsString('location_id='.$first->master_location_id, $response->viewData('boxes')->nextPageUrl());
        $this->get(route('export.stock.index', $filters + ['stock_page' => 2]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->count() === 1);
        $this->get(route('export.stock.index', ['location_id' => $second->master_location_id]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->pluck('box_number')->all() === ['00000100']);
        $this->get(route('export.stock.index', ['q' => 'EX-A1', 'location_id' => $second->master_location_id]))->assertOk()
            ->assertSee('Tidak ada box yang cocok')->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 0);

        $sheet = $this->workbook($filters + ['stock_page' => 2])->getActiveSheet();
        $this->assertSame(22, $sheet->getHighestRow());
        $this->assertSame('00000001', $sheet->getCell('A2')->getValue());
        $this->assertSame('00000021', $sheet->getCell('A22')->getValue());
        $this->assertSame('EX-A1', $sheet->getCell('F22')->getValue());
        $this->assertSame('Stok cadangan', $sheet->getCell('G2')->getValue());
    }

    public function test_reservation_does_not_move_box_into_destination_filter_or_hide_source(): void
    {
        $source = $this->pallet('SOURCE'); $box = $this->box('00000001', $source);
        $target = ExportMasterLocation::create(['code' => 'TARGET']);
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'RD-FILTER', 'customer_name' => 'Customer',
            'delivery_deadline' => '2026-11-10', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 100]]], $this->operator);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $target->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]]], $this->operator);

        $response = $this->get(route('export.stock.index', ['location_id' => $source->master_location_id, 'status' => 'reserved']))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 1)
            ->assertViewHas('filterLocations', fn ($locations) => $locations->pluck('id')->all() === [$source->master_location_id]);
        $response->assertSee('Direservasi · menunggu Oke FIFO');
        $this->get(route('export.stock.index', ['location_id' => $target->id]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 0);
        $sheet = $this->workbook(['location_id' => $source->master_location_id, 'status' => 'reserved'])->getActiveSheet();
        $this->assertSame('Pallet cadangan', $sheet->getCell('E2')->getValue());
        $this->assertSame('SOURCE', $sheet->getCell('F2')->getValue());
        $this->assertSame('RD-FILTER', $sheet->getCell('H2')->getValue());
        $this->assertSame('Pallet 1', $sheet->getCell('I2')->getValue());
        $this->assertSame('TARGET', $sheet->getCell('J2')->getValue());
        $this->assertSame('Direservasi · menunggu Oke FIFO', $sheet->getCell('G2')->getValue());
        $this->assertSame($source->id, $box->fresh()->current_pallet_id);
        $this->assertNull($box->fresh()->fulfillment->placed_at);
    }

    public function test_export_keeps_ids_and_user_text_literal_and_has_numeric_pcs_and_dates(): void
    {
        $this->box('00000123', extra: ['part_number' => '=1+1']);
        $sheet = $this->workbook(['status' => 'pending', 'q' => '00000123'])->getActiveSheet();
        $this->assertSame('00000123', $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame(100, $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('D2')->getDataType());
        $this->assertSame('Menunggu penempatan', $sheet->getCell('E2')->getValue());
        $this->assertSame('Pilih penempatan cadangan', $sheet->getCell('G2')->getValue());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:J2', $sheet->getAutoFilter()->getRange());
    }

    public function test_empty_stock_and_empty_export_keep_filters_and_readable_headers(): void
    {
        $this->get(route('export.stock.index'))->assertOk()->assertSee('Belum ada stok ekspor')
            ->assertViewHas('filterLocations', fn ($locations) => $locations->isEmpty());
        $empty = $this->pallet('EMPTY');
        $this->get(route('export.stock.index'))->assertViewHas('filterLocations', fn ($locations) => $locations->isEmpty());
        $sheet = $this->workbook(['location_id' => $empty->master_location_id])->getActiveSheet();
        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertSame('Nomor box', $sheet->getCell('A1')->getValue());
        $this->assertSame('Lokasi tujuan', $sheet->getCell('J1')->getValue());
    }

    public function test_filters_reject_domestic_locations_and_invalid_values_on_both_endpoints(): void
    {
        $domestic = MasterLocation::create(['code' => 'LOCAL']);
        foreach (['export.stock.index', 'export.stock.export'] as $route) {
            foreach ([['location_id' => $domestic->id], ['mode' => 'invalid'], ['status' => 'invalid'], ['location_id' => 'bad']] as $filters) {
                $this->getJson(route($route, $filters))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($filters));
            }
        }
    }

    public function test_excel_endpoint_uses_existing_export_stock_permission(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']))->get(route('export.stock.export'))->assertForbidden();
        $this->actingAs($this->operator);
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get(route('export.stock.export'))->assertForbidden();
    }

    public function test_pallet_view_groups_all_matching_boxes_and_loads_contents_with_separate_pagination(): void
    {
        $first = $this->pallet('A1'); $second = $this->pallet('A2');
        for ($i = 1; $i <= 21; $i++) $this->box(str_pad((string) $i, 8, '0', STR_PAD_LEFT), $first);
        $this->box('00000022', $second); $this->box('00000023');
        $groups = $this->getJson(route('export.stock.index', ['mode' => 'pallet']))->assertOk()
            ->assertJsonPath('count', 23)->json('html');
        $this->assertStringContainsString('21 box · 2.100 PCS', $groups);
        $this->assertStringContainsString('Lihat detail Pallet cadangan di lokasi A1', $groups);
        $this->assertStringNotContainsString('<details', $groups);
        $this->assertSame(3, substr_count($groups, 'data-export-stock-pallet='));
        $this->assertStringContainsString('Belum ditempatkan', $groups);
        $this->assertStringNotContainsString('00000001', $groups);
        $this->assertStringNotContainsString('<html', $groups);
        $page = $this->getJson(route('export.stock.pallet-boxes', ['pallet' => $first->id, 'mode' => 'pallet']))->assertOk()->json('html');
        $this->assertStringContainsString('stock-pallet-detail-heading', $page);
        $this->assertStringContainsString('Lokasi A1', $page);
        $this->assertStringContainsString('21 box sesuai filter', $page);
        $this->assertStringContainsString('00000001', $page);
        $this->assertStringNotContainsString('00000021', $page);
        $this->assertStringNotContainsString('00000022', $page);
        $this->assertStringContainsString('box_page=2', $page);
        $last = $this->getJson(route('export.stock.pallet-boxes', ['pallet' => $first->id, 'box_page' => 2]))->assertOk()->json('html');
        $this->assertStringContainsString('00000021', $last);
        $this->assertStringNotContainsString('00000022', $last);
        $unplaced = $this->getJson(route('export.stock.pallet-boxes', ['pallet' => 'unplaced']))->assertOk()->json('html');
        $this->assertStringContainsString('00000023', $unplaced);
        $this->assertStringNotContainsString('00000022', $unplaced);
        $this->get(route('export.stock.pallet-boxes', ['pallet' => $first->id]))->assertOk()->assertSee('Isi Pallet Ekspor');
    }

    public function test_search_finds_exact_pallet_position_delivery_code_and_physical_location_in_both_modes(): void
    {
        $first = $this->plannedPallet('A1'); $second = $this->plannedPallet('A2'); $ten = $this->plannedPallet('A10', 10);
        $this->box('00000001', $first); $this->box('00000002', $second); $this->box('00000003', $ten);
        foreach (['box', 'pallet'] as $mode) {
            $this->getJson(route('export.stock.index', ['q' => 'Pallet 1', 'mode' => $mode]))->assertOk()->assertJsonPath('count', 2);
            $this->getJson(route('export.stock.index', ['q' => 'Pallet 10', 'mode' => $mode]))->assertOk()->assertJsonPath('count', 1);
            $this->getJson(route('export.stock.index', ['q' => 'RD-A2', 'mode' => $mode]))->assertOk()->assertJsonPath('count', 1);
            $this->getJson(route('export.stock.index', ['q' => 'A2', 'mode' => $mode]))->assertOk()->assertJsonPath('count', 1);
        }
        $html = $this->getJson(route('export.stock.index', ['q' => 'Pallet 1', 'mode' => 'pallet']))->json('html');
        $this->assertStringContainsString('RD-A1', $html);
        $this->assertStringContainsString('RD-A2', $html);
        $this->assertStringNotContainsString('RD-A10', $html);
        $sheet = $this->workbook(['q' => 'Pallet 1', 'mode' => 'pallet'])->getActiveSheet();
        $this->assertSame(3, $sheet->getHighestRow());
        $this->assertSame('Pallet 1', $sheet->getCell('E2')->getValue());
        $this->assertSame('00000002', $sheet->getCell('A3')->getValue());
    }

    public function test_group_contents_keep_filters_and_never_include_another_pallets_boxes(): void
    {
        $first = $this->pallet('A1'); $second = $this->pallet('A2');
        $this->box('00000001', $first); $this->box('00000002', $first, ['part_number' => 'OTHER']);
        $this->box('00000003', $second);
        $filters = ['mode' => 'pallet', 'q' => '00123-001', 'status' => 'spare', 'location_id' => $first->master_location_id];
        $groups = $this->getJson(route('export.stock.index', $filters))->assertOk()->assertJsonPath('count', 1)->json('html');
        $this->assertStringContainsString('dari 2 box fisik di pallet', $groups);
        $html = $this->getJson(route('export.stock.pallet-boxes', ['pallet' => $first->id] + $filters))->assertOk()->json('html');
        $this->assertStringContainsString('00000001', $html);
        $this->assertStringNotContainsString('00000002', $html);
        $this->assertStringNotContainsString('00000003', $html);
        $this->getJson(route('export.stock.index', ['mode' => 'pallet', 'q' => 'none']))->assertOk()->assertJsonPath('count', 0);
    }

    public function test_ajax_list_does_not_load_layout_location_choices_or_destination_catalogs(): void
    {
        $pallet = $this->pallet('A1'); $this->box('00000001', $pallet);
        foreach (['box', 'pallet'] as $mode) {
            DB::enableQueryLog(); DB::flushQueryLog();
            $json = $this->getJson(route('export.stock.index', ['mode' => $mode]))->assertOk()->assertJsonPath('count', 1);
            $queries = DB::getQueryLog(); DB::disableQueryLog();
            $this->assertStringNotContainsString('export-stock-filters', $json->json('html'));
            foreach ($queries as $query) $this->assertStringNotContainsString('master_locations', $query['query']);
        }
    }

    public function test_group_detail_and_ajax_list_use_the_existing_permission_and_reject_missing_pallets(): void
    {
        $this->getJson(route('export.stock.pallet-boxes', ['pallet' => 99999]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']));
        $this->getJson(route('export.stock.index'))->assertForbidden();
        $this->getJson(route('export.stock.pallet-boxes', ['pallet' => 'unplaced']))->assertForbidden();
        $this->actingAs($this->operator);
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->getJson(route('export.stock.pallet-boxes', ['pallet' => 'unplaced']))->assertForbidden();
    }

    public function test_pallet_pagination_counts_groups_without_splitting_a_pallet_between_pages(): void
    {
        for ($i = 1; $i <= 11; $i++) $this->box(str_pad((string) $i, 8, '0', STR_PAD_LEFT), $this->pallet('A'.$i));
        $this->box('00000012');
        $this->get(route('export.stock.index', ['mode' => 'pallet']))->assertOk()->assertViewHas('resultCount', 12)
            ->assertViewHas('palletGroups', fn ($groups) => $groups->total() === 12 && $groups->count() === 10);
        $last = $this->getJson(route('export.stock.index', ['mode' => 'pallet', 'stock_page' => 2]))->assertOk()
            ->assertHeader('Vary', 'Accept')->assertJsonPath('count', 12)->json('html');
        $this->assertStringContainsString('Lihat detail Pallet cadangan di lokasi A11', $last);
        $this->assertStringContainsString('Belum ditempatkan', $last);
        $this->assertStringNotContainsString('Lihat detail Pallet cadangan di lokasi A10', $last);
    }
}
