<?php

namespace Tests\Feature;

use App\Models\{ExportMasterLocation, ExportPartSetting, ExportStockBox, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportStockModalTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $part;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'EX-MODAL', 'qty_box' => 100]);
    }

    private function receive(): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(['box_number' => '00000123',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
    }

    private function plan(): void
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'MODAL-PLAN', 'customer_name' => 'Customer Modal',
            'delivery_deadline' => '2026-11-01', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 100]]], $this->operator);
        $location = ExportMasterLocation::create(['code' => 'QA-A1']);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]]], $this->operator);
    }

    private function place(ExportStockBox $box, array $destination = []): void
    {
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->fresh()->revision,
            'request_token' => (string) Str::uuid()] + $destination, $this->operator);
    }

    public function test_stock_uses_actual_planned_pallet_number_and_loads_details_only_in_modal(): void
    {
        $this->plan(); $box = $this->receive(); $this->place($box);
        $this->get(route('export.stock.index'))->assertOk()->assertSee('Pallet 1 · QA-A1')
            ->assertDontSee('EX-000001')->assertDontSee('id="receiving-instruction"', false)
            ->assertSee('data-export-stock-box=', false);
        $html = $this->getJson(route('export.stock.show', $box))->assertOk()->json('html');
        $this->assertStringContainsString('00000123', $html);
        $this->assertStringContainsString('Pallet 1 · lokasi QA-A1', $html);
        $this->assertStringContainsString('Pertama diterima', $html);
        $this->assertStringContainsString('Penempatan sudah dikonfirmasi', $html);
        $this->get(route('export.stock.index', ['box' => $box->id]))->assertOk()
            ->assertSee('data-open-on-load="true"', false)
            ->assertSeeInOrder(['Daftar stok ekspor', 'id="export-stock-box-modal"', 'id="receiving-instruction"'], false);
    }

    public function test_spare_source_stays_distinct_from_newly_reserved_planned_destination(): void
    {
        $box = $this->receive(); $source = ExportMasterLocation::create(['code' => 'QA-SPARE']);
        $this->place($box, ['location_id' => $source->id]); $this->plan();
        $this->get(route('export.stock.index'))->assertOk()->assertSee('Pallet cadangan · QA-SPARE')->assertDontSee('Pallet 1 · QA-A1');
        $html = $this->getJson(route('export.stock.show', $box))->assertOk()->json('html');
        $this->assertStringContainsString('Pallet cadangan · lokasi QA-SPARE', $html);
        $this->assertStringContainsString('Pallet 1 · QA-A1', $html);
    }

    public function test_detail_endpoint_has_the_same_permission_and_missing_boxes_are_not_found(): void
    {
        $box = $this->receive();
        $this->getJson(route('export.stock.show', ['stockBox' => 99999]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']))
            ->getJson(route('export.stock.show', $box))->assertForbidden();
        $this->actingAs($this->operator);
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->getJson(route('export.stock.show', $box))->assertForbidden();
    }

    public function test_invalid_native_placement_returns_to_the_same_box_modal(): void
    {
        $box = $this->receive();
        $this->from(route('export.stock.index'))->post(route('export.receiving.place', $box), [
            'revision' => $box->revision, 'request_token' => (string) Str::uuid(), 'destination' => 'location:99999',
        ])->assertRedirect(route('export.stock.index', ['box' => $box->id]))->assertSessionHasErrors();
        $this->get(route('export.stock.index', ['box' => $box->id]))->assertOk()
            ->assertSee('data-open-on-load="true"', false)->assertSee('Penempatan belum disimpan.');
        $this->assertSame('received', $box->fresh()->state);
    }
}
