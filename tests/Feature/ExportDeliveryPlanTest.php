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
            ->assertSee('Rencana delivery baru')->assertSee('Dari foto')->assertSee('Dari Excel');
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

    public function test_photo_and_excel_results_require_explicit_review_before_save(): void
    {
        ExportPartSetting::create(['part_number' => 'EXP-001', 'qty_box' => 20]);
        $this->actingAs($this->operator());
        foreach (['photo', 'excel'] as $source) {
            $this->postJson(route('export.delivery-plans.store'), $this->payload(['source_type' => $source]))
                ->assertUnprocessable()->assertJsonValidationErrors('reviewed');
        }
        $this->post(route('export.delivery-plans.store'), $this->payload([
            'source_type' => 'photo', 'source_name' => 'rencana.jpg', 'reviewed' => '1',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('export_delivery_plans', ['source_type' => 'photo', 'source_name' => 'rencana.jpg']);
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
}
