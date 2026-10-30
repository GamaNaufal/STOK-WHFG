<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportMasterLocation, ExportPartSetting, MasterLocation, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportReceivingBatchService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportPlacementGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        ExportPartSetting::create(['part_number' => 'TX-PART', 'qty_box' => 100]);
    }

    public function test_print_contains_destination_and_checklist_without_mutating_receipt(): void
    {
        $actor = \App\Models\User::where('role', 'export_operator')->firstOrFail();
        $part = ExportPartSetting::firstOrCreate(['part_number' => 'GUIDE-PART'], ['qty_box' => 20]);
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'GUIDE-PLAN', 'customer_name' => 'Customer Guide',
            'delivery_deadline' => '2026-11-05', 'source_type' => 'manual', 'items' => [['part_number' => $part->part_number, 'pcs_quantity' => 40]]], $actor);
        $location = ExportMasterLocation::create(['code' => 'GUIDE-A1']);
        app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id, 'items' => [['part_setting_id' => $part->id, 'box_quantity' => 2]]]]], $actor);
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($actor);
        foreach (['00000101', '00000102'] as $number) $batch = $service->scan($batch->id, ['box_number' => $number,
            'part_number' => $part->part_number, 'revision' => $batch->revision, 'scan_token' => (string) Str::uuid()], $actor);
        $batch = $service->process($batch->id, $batch->revision, $actor); $audits = AuditLog::count();
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()->assertSee('Antarkan box ke')->assertSee('Cetak panduan');
        $this->get(route('export.receiving.transactions.print', $batch))->assertOk()->assertSee('LOKASI: GUIDE-A1')
            ->assertSee('Pallet 1')->assertSee('00000101')->assertSee('00000102')
            ->assertSee('paper-check')->assertDontSee('<form', false)
            ->assertViewHas('guideWarning', null)->assertViewHas('printGroups', fn ($g) => count($g) === 1 && count($g[0]['rows']) === 2);
        $this->assertSame($audits, AuditLog::count());
        $this->assertDatabaseCount('export_stock_movements', 0);
        $this->assertDatabaseCount('export_stock_pallets', 0);
        $this->assertFalse($location->fresh()->is_occupied);
    }

    public function test_print_keeps_finished_spare_at_its_real_location_and_uses_selected_target_only_for_pending(): void
    {
        $actor = \App\Models\User::where('role', 'export_operator')->firstOrFail();
        $part = ExportPartSetting::first();
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($actor);
        foreach (['00000201', '00000202'] as $number) $batch = $service->scan($batch->id, ['box_number' => $number,
            'part_number' => $part->part_number, 'revision' => $batch->revision, 'scan_token' => (string) Str::uuid()], $actor);
        $batch = $service->process($batch->id, $batch->revision, $actor);
        $first = \App\Models\ExportStockBox::where('box_number', '00000201')->firstOrFail();
        $source = ExportMasterLocation::create(['code' => 'GUIDE-SOURCE']);
        $target = ExportMasterLocation::create(['code' => 'GUIDE-TARGET']);
        app(ExportStockReceivingService::class)->place($first->id, ['revision' => $first->revision, 'request_token' => (string) Str::uuid(), 'location_id' => $source->id], $actor);
        $audits = AuditLog::count();
        $this->get(route('export.receiving.transactions.print', ['batch' => $batch->id, 'spare_destination' => 'location:'.$target->id]))
            ->assertOk()->assertSee('LOKASI: GUIDE-TARGET')->assertSee('LOKASI: GUIDE-SOURCE')->assertViewHas('guideWarning', null)
            ->assertViewHas('printGroups', fn ($g) => count($g) === 2 && $g[0]['rows'][0]['pending'] === false && $g[1]['rows'][0]['pending'] === true);
        $this->assertSame($audits, AuditLog::count()); $this->assertDatabaseCount('export_stock_movements', 1);
        $this->assertDatabaseHas('export_stock_boxes', ['box_number' => '00000202', 'state' => 'received', 'current_pallet_id' => null]);
        $this->assertFalse($target->fresh()->is_occupied);
    }

    public function test_guide_marks_unknown_or_unavailable_spare_destinations_and_rejects_malformed_input(): void
    {
        $actor = \App\Models\User::where('role', 'export_operator')->firstOrFail(); $part = ExportPartSetting::first();
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '00000301', 'part_number' => $part->part_number, 'receive_token' => (string) Str::uuid()], $actor);
        $this->get(route('export.receiving.transactions.legacy-print', $box))->assertOk()->assertSee('BELUM DITENTUKAN')
            ->assertViewHas('guideWarning', fn ($message) => is_string($message) && $message !== '');
        $domestic = MasterLocation::create(['code' => 'DOMESTIC-NOT-ALLOWED']);
        $this->get(route('export.receiving.transactions.legacy-print', ['stockBox' => $box->id, 'spare_destination' => 'location:'.$domestic->id]))
            ->assertOk()->assertSee('BELUM DITENTUKAN')->assertDontSee('DOMESTIC-NOT-ALLOWED');
        $this->getJson(route('export.receiving.transactions.legacy-print', ['stockBox' => $box->id, 'spare_destination' => 'invalid']))->assertUnprocessable();
    }

    public function test_print_routes_protect_drafts_and_revoked_access(): void
    {
        $actor = \App\Models\User::where('role', 'export_operator')->firstOrFail();
        $service = app(ExportReceivingBatchService::class); $batch = $service->start($actor);
        $this->get(route('export.receiving.transactions.print', $batch))->assertNotFound();
        $part = ExportPartSetting::first();
        $batch = $service->scan($batch->id, ['box_number' => '00000401', 'part_number' => $part->part_number, 'revision' => $batch->revision, 'scan_token' => (string) Str::uuid()], $actor);
        $batch = $service->process($batch->id, $batch->revision, $actor);
        $this->get(route('export.receiving.transactions.print', $batch))->assertOk();
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get(route('export.receiving.transactions.print', $batch))->assertForbidden();
    }
}
