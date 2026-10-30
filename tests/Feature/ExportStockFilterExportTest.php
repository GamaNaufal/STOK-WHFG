<?php

namespace Tests\Feature;

use App\Models\{ExportMasterLocation, ExportPartSetting, ExportStockBox, ExportStockPallet, MasterLocation, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $filters = ['pallet_id' => $first->id, 'location_id' => $first->master_location_id, 'status' => 'spare', 'q' => '00123-001'];
        $response = $this->get(route('export.stock.index', $filters))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 21 && $boxes->count() === 20)
            ->assertSee('Export Excel')->assertSee('id="stock-pallet"', false)->assertSee('id="stock-location"', false)
            ->assertSee('Pallet cadangan · EX-A1 (21 box)');
        $this->assertStringContainsString('pallet_id='.$first->id, $response->viewData('boxes')->nextPageUrl());
        $this->get(route('export.stock.index', $filters + ['stock_page' => 2]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->count() === 1);
        $this->get(route('export.stock.index', ['location_id' => $second->master_location_id]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->pluck('box_number')->all() === ['00000100']);
        $this->get(route('export.stock.index', ['pallet_id' => $first->id, 'location_id' => $second->master_location_id]))->assertOk()
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

        $response = $this->get(route('export.stock.index', ['pallet_id' => $source->id, 'status' => 'reserved']))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 1)
            ->assertViewHas('filterPallets', fn ($pallets) => $pallets->pluck('id')->all() === [$source->id]);
        $response->assertSee('Direservasi · menunggu Oke FIFO');
        $this->get(route('export.stock.index', ['location_id' => $target->id]))->assertOk()
            ->assertViewHas('boxes', fn ($boxes) => $boxes->total() === 0);
        $sheet = $this->workbook(['pallet_id' => $source->id, 'status' => 'reserved'])->getActiveSheet();
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
            ->assertViewHas('filterPallets', fn ($pallets) => $pallets->isEmpty());
        $empty = $this->pallet('EMPTY');
        $this->get(route('export.stock.index'))->assertViewHas('filterPallets', fn ($pallets) => $pallets->isEmpty());
        $sheet = $this->workbook(['pallet_id' => $empty->id])->getActiveSheet();
        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertSame('Nomor box', $sheet->getCell('A1')->getValue());
        $this->assertSame('Lokasi tujuan', $sheet->getCell('J1')->getValue());
    }

    public function test_filters_reject_domestic_locations_and_invalid_values_on_both_endpoints(): void
    {
        $domestic = MasterLocation::create(['code' => 'LOCAL']);
        foreach (['export.stock.index', 'export.stock.export'] as $route) {
            foreach ([['location_id' => $domestic->id], ['pallet_id' => 99999], ['status' => 'invalid'], ['pallet_id' => 'bad']] as $filters) {
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
}
