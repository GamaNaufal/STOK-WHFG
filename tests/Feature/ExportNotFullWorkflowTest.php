<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPartSetting;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Models\User;
use App\Services\ExportBoxRequirements;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportFulfillmentCompletionService;
use App\Services\ExportFulfillmentService;
use App\Services\ExportNotFullService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationNoticeService;
use App\Services\ExportStockReceivingService;
use App\Services\OperationalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExportNotFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouse;

    private User $supervisor;

    private User $operator;

    private ExportPartSetting $part;

    private int $sequence = 0;

    private int $plans = 0;

    private int $places = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->warehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $this->supervisor = User::factory()->create(['role' => 'supervisi']);
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->part = ExportPartSetting::create(['part_number' => '99', 'qty_box' => 80]);
        $this->actingAs($this->operator);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function delivery(int $pcs = 100): ExportDeliveryPlan
    {
        return app(ExportDeliveryPlanService::class)->save(['plan_code' => 'NF-'.++$this->plans, 'customer_name' => 'Customer NF',
            'delivery_deadline' => '2026-11-03', 'source_type' => 'manual', 'items' => [['part_number' => '99', 'pcs_quantity' => $pcs]]], $this->operator);
    }

    private function finalize(ExportDeliveryPlan $d): ExportDeliveryPlan
    {
        $loc = ExportMasterLocation::create(['code' => 'NF-LOC-'.++$this->places]);
        $rows = ExportBoxRequirements::catalog($d)->map(fn ($r) => ['part_setting_id' => $r['part_setting_id'], 'pcs_per_box' => $r['pcs_per_box'], 'box_quantity' => $r['required']])->values()->all();
        $plan = app(ExportPalletPlanningService::class)->save($d->id, ['revision' => $d->palletPlan?->revision ?? 0, 'delivery_revision' => $d->revision,
            'action' => 'finalize', 'pallets' => [['master_location_id' => $loc->id, 'items' => $rows]]], $this->operator);
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->orderByDesc('id')->firstOrFail();
        app(ExportStockAllocationNoticeService::class)->acknowledge($d->id, $notice->id, $plan->revision, $this->operator);

        return $d->fresh('palletPlan.pallets.items');
    }

    private function full(int $days = 1): ExportStockBox
    {
        $s = app(ExportStockReceivingService::class);
        $box = $s->receive(['box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT), 'part_number' => '99', 'receive_token' => (string) Str::uuid()], $this->operator, false, now()->subDays($days));
        $loc = ExportMasterLocation::create(['code' => 'SPARE-'.++$this->places]);

        return $s->place($box->id, ['revision' => $box->revision, 'request_token' => (string) Str::uuid(), 'location_id' => $loc->id], $this->operator);
    }

    private function nf(ExportDeliveryPlan $d): ExportNotFullRequest
    {
        return ExportNotFullRequest::where('export_delivery_plan_id', $d->id)->sole();
    }

    private function prepare(ExportNotFullRequest $r, string $mode = 'external', string $number = '90000001'): void
    {
        app(ExportNotFullService::class)->prepare($r->id, ['revision' => $r->fresh()->revision, 'mode' => $mode, 'box_number' => $number, 'notes' => 'Siapkan sesuai kebutuhan dan pallet rencana.'], $this->warehouse);
    }

    private function approve(ExportNotFullRequest $r): void
    {
        app(ExportNotFullService::class)->approve($r->id, ['revision' => $r->fresh()->revision, 'physical_confirmed' => 1], $this->supervisor);
    }

    private function invalid(callable $work, string $message = ''): void
    {
        try {
            $work();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            if ($message) {
                $this->assertStringContainsString($message, implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }

    public function test_save_creates_one_mandatory_request_and_reimport_edits_keep_it_without_creating_stock(): void
    {
        $d = $this->delivery();
        $r = $this->nf($d);
        $this->assertSame(100, $d->total_pcs);
        $this->assertSame(2, $d->total_boxes);
        $this->assertSame(20, $d->items->sole()->not_full_pcs);
        $this->assertSame(20, $r->pcs_quantity);
        $this->assertSame(80, $r->fixed_qty);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        app(ExportDeliveryPlanService::class)->save(['plan_code' => $d->plan_code, 'customer_name' => $d->customer_name, 'delivery_deadline' => '2026-11-03',
            'source_type' => 'excel', 'source_name' => 'repeat.xlsx', 'revision' => 1, 'items' => [['part_number' => '99', 'pcs_quantity' => 180]]], $this->operator, $d->id);
        $this->assertDatabaseCount('export_not_full_requests', 1);
        $this->assertSame($r->id, $this->nf($d)->id);
        $this->assertSame(20, $r->fresh()->pcs_quantity);
        $this->assertSame(3, $d->fresh()->total_boxes);
        $this->invalid(fn () => $this->prepare($r), 'pallet final');
        $this->get(route('export.delivery-plans.show', $d))->assertOk()->assertSee('Kebutuhan wajib Box Not Full')->assertSee('Not Full 20 PCS');
    }

    public function test_excel_save_alert_and_retry_do_not_duplicate_requests(): void
    {
        $data = ['request_token' => (string) Str::uuid(), 'plan_code' => 'NF-HTTP', 'customer_name' => 'Excel Customer', 'delivery_deadline' => '2026-11-03',
            'source_type' => 'excel', 'source_name' => 'input.xlsx', 'reviewed' => 1, 'items' => [['part_number' => '99', 'pcs_quantity' => 100]]];
        $this->post(route('export.delivery-plans.store'), $data)->assertRedirect()->assertSessionHas('success', fn ($s) => str_contains($s, 'Request wajib sudah dicatat otomatis') && str_contains($s, 'Hubungi Admin Warehouse'));
        $this->post(route('export.delivery-plans.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('export_not_full_requests', 1);
        $this->assertDatabaseCount('export_delivery_plans', 1);
    }

    public function test_pallet_plan_requires_separate_exact_full_and_not_full_allocations(): void
    {
        $d = $this->delivery();
        $loc = ExportMasterLocation::create(['code' => 'BAD-ALLOC']);
        $this->invalid(fn () => app(ExportPalletPlanningService::class)->save($d->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $loc->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 2]]]]], $this->operator), 'Alokasi');
        $d = $this->finalize($d);
        $items = $d->palletPlan->pallets->sole()->items;
        $this->assertSame([20, 80], $items->pluck('pcs_per_box')->sort()->values()->all());
        $this->assertSame(2, (int) $items->sum('box_quantity'));
        $this->get(route('export.pallet-plans.edit', $d))->assertRedirect();
        $this->get(route('export.pallet-plans.show', $d))->assertOk()->assertSee('Not Full 20 PCS');
    }

    public function test_full_demand_is_reserved_first_and_split_does_not_steal_its_only_box(): void
    {
        $full = $this->full(3);
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->assertNotNull($full->fresh()->fulfillment);
        $this->assertNull(app(ExportNotFullService::class)->source($r));
        $this->invalid(fn () => $this->prepare($r, 'split'), 'Tidak tersedia box penuh');
        $this->assertSame(80, $full->fresh()->pcs_quantity);
        $this->assertSame('waiting', $r->fresh()->status);
        $this->prepare($r, 'external');
        $this->approve($r);
        $this->assertSame(20, $r->fresh()->stockBox->pcs_quantity);
    }

    public function test_external_approval_creates_and_places_exact_not_full_without_a_second_receipt(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $rev = $r->fresh()->revision;
        $this->approve($r);
        $b = $r->fresh()->stockBox;
        $target = app(ExportNotFullService::class)->target($r->fresh());
        $this->assertTrue($b->is_not_full);
        $this->assertSame(20, $b->pcs_quantity);
        $this->assertSame('stored', $b->state);
        $this->assertSame($target->pallet->master_location_id, $b->currentPallet->master_location_id);
        $this->assertNotNull($b->fulfillment->placed_at);
        $this->assertFalse($b->fulfillment->picking_required);
        $this->assertSame(1, (int) app(ExportFulfillmentService::class)->overview()->sole()->fulfilled_boxes);
        app(ExportNotFullService::class)->approve($r->id, ['revision' => $rev, 'physical_confirmed' => 1], $this->supervisor);
        $this->assertDatabaseCount('export_stock_boxes', 1);
        $this->invalid(fn () => app(ExportStockReceivingService::class)->receive(['box_number' => $b->box_number, 'part_number' => '99', 'receive_token' => (string) Str::uuid()], $this->operator), 'sudah tercatat');
        $p = $target->pallet;
        $this->invalid(fn () => app(ExportFulfillmentCompletionService::class)->ready($d->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'location_id' => $p->master_location_id, 'request_token' => (string) Str::uuid()], $this->operator), 'harus terpenuhi');
    }

    public function test_split_uses_oldest_free_full_box_preserves_remainder_fifo_and_original_receipt_totals(): void
    {
        $first = $this->full(5);
        $source = $this->full(4);
        $young = $this->full(1);
        $originalLocation = $source->current_pallet_id;
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r, 'split');
        $this->assertSame($source->id, $r->fresh()->preparation['source_box_id']);
        $this->approve($r);
        $child = $r->fresh()->stockBox;
        $source->refresh();
        $this->assertSame(80, $first->fresh()->pcs_quantity);
        $this->assertSame(80, $young->fresh()->pcs_quantity);
        $this->assertSame(60, $source->pcs_quantity);
        $this->assertTrue($source->is_not_full);
        $this->assertSame($originalLocation, $source->current_pallet_id);
        $this->assertSame(20, $child->pcs_quantity);
        $this->assertSame($source->id, $child->source_box_id);
        $this->assertSame('split', $child->origin);
        $this->assertTrue($child->first_received_at->equalTo($source->first_received_at));
        $this->assertSame(80, $source->receipt_pcs_quantity);
        $this->assertSame(240, (int) ExportStockBox::sum('pcs_quantity'));
        $this->assertDatabaseCount('export_stock_boxes', 4);
        $data = app(OperationalDashboardService::class)->build(Request::create('/', 'GET', ['scope' => 'export', 'period' => 'all']));
        $this->assertSame(3, (int) $data['domains']['export']['inbound']->boxes);
        $this->assertSame(240, (int) $data['domains']['export']['inbound']->pcs);
        $this->assertSame(0, (int) $data['domains']['export']['outbound']->boxes);
        $this->assertDatabaseHas('audit_logs', ['type' => 'export_not_full', 'action' => 'split', 'model_id' => $source->id]);
    }

    public function test_new_full_reservation_after_preparation_invalidates_split_approval_without_holding_source(): void
    {
        $this->full(5);
        $source = $this->full(3);
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r, 'split');
        $this->assertNull($source->fresh()->fulfillment);
        $this->finalize($this->delivery(80));
        $this->assertNotNull($source->fresh()->fulfillment);
        $this->invalid(fn () => $this->approve($r), 'Sumber FIFO berubah');
        $this->assertSame(80, $source->fresh()->pcs_quantity);
        $this->assertDatabaseCount('export_stock_boxes', 2);
    }

    public function test_existing_exact_not_full_is_reserved_fifo_and_keeps_normal_placement_queue(): void
    {
        $this->full(6);
        $this->full(5);
        foreach ([4, 2] as $i => $days) {
            $b = ExportStockBox::create(['box_number' => '8000000'.($i + 1), 'part_setting_id' => $this->part->id, 'part_number' => '99', 'pcs_quantity' => 20,
                'standard_pcs_quantity' => 80, 'is_not_full' => true, 'origin' => 'external', 'state' => 'received', 'receive_token' => (string) Str::uuid(), 'first_received_at' => now()->subDays($days), 'received_by' => $this->admin->id]);
            $loc = ExportMasterLocation::create(['code' => 'PARTIAL-'.$i]);
            app(ExportStockReceivingService::class)->place($b->id, ['revision' => 1, 'request_token' => (string) Str::uuid(), 'location_id' => $loc->id], $this->admin);
        }
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $target = app(ExportNotFullService::class)->target($r);
        $this->assertSame('80000001', $target->fulfillmentBoxes()->sole()->box_number);
        $this->assertSame('Direservasi · menunggu penempatan', app(ExportNotFullService::class)->state($r, $target));
        $this->prepare($r);
        $this->assertSame('waiting', $r->fresh()->status);
        $this->assertDatabaseCount('export_stock_boxes', 4);
    }

    public function test_reducing_only_partial_requirement_returns_20_pcs_and_cancels_request_without_changing_full_quantity(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r);
        $this->approve($r);
        $p = $d->palletPlan->pallets->sole();
        $partial = $p->items->firstWhere('pcs_per_box', 20);
        $full = $p->items->firstWhere('pcs_per_box', 80);
        $service = app(ExportFulfillmentCompletionService::class);
        $preview = $service->reduction($p, [$full->id => 1, $partial->id => 0]);
        $loc = ExportMasterLocation::create(['code' => 'NF-RETURN']);
        $service->reduce($d->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'quantities' => $preview['quantities'],
            'fingerprint' => $preview['fingerprint'], 'destination' => 'location:'.$loc->id, 'return_confirmed' => 1], $this->operator);
        $this->assertSame(80, $d->fresh()->total_pcs);
        $this->assertSame(1, $d->fresh()->total_boxes);
        $this->assertSame(0, $d->fresh()->items->sole()->not_full_pcs);
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame(20, $r->fresh()->stockBox->pcs_quantity);
        $this->assertSame($loc->id, $r->fresh()->stockBox->currentPallet->master_location_id);
    }

    public function test_prepared_number_cannot_enter_normal_receiving_and_domestic_identity_remains_separate(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        Box::create(['box_number' => '90000001', 'part_number' => 'LOCAL', 'pcs_quantity' => 80, 'qty_box' => 80, 'qr_code' => 'LOCAL-NF', 'user_id' => $this->admin->id, 'expired_status' => 'active']);
        $this->prepare($r);
        $this->invalid(fn () => app(ExportStockReceivingService::class)->receive(['box_number' => '90000001', 'part_number' => '99', 'receive_token' => (string) Str::uuid()], $this->operator), 'sedang disiapkan');
        $this->approve($r);
        $this->assertDatabaseCount('boxes', 1);
        $this->assertDatabaseCount('export_stock_boxes', 1);
    }

    public function test_approval_failure_rolls_back_source_split_child_stock_and_audit(): void
    {
        $this->full(5);
        $source = $this->full(3);
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r, 'split');
        $target = app(ExportNotFullService::class)->target($r->fresh());
        ExportMasterLocation::whereKey($target->pallet->master_location_id)->update(['is_occupied' => true]);
        $logs = AuditLog::count();
        $this->invalid(fn () => $this->approve($r));
        $this->assertSame(80, $source->fresh()->pcs_quantity);
        $this->assertDatabaseCount('export_stock_boxes', 2);
        $this->assertSame($logs, AuditLog::count());
        $this->assertSame('prepared', $r->fresh()->status);
    }

    public function test_rejection_does_not_remove_mandatory_demand_or_create_stock(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r);
        app(ExportNotFullService::class)->reject($r->id, ['revision' => $r->fresh()->revision, 'reason' => 'Data fisik belum sesuai'], $this->supervisor);
        $this->assertSame('rejected', $r->fresh()->status);
        $this->assertSame(2, $d->fresh()->total_boxes);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $this->prepare($r);
        $this->approve($r);
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_preparation_and_approval_roles_are_separate_and_invalid_ids_are_rejected(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->actingAs($this->warehouse)->get(route('box-not-full.create', ['scope' => 'export']))->assertOk()->assertSee('Box Not Full · Ekspor');
        $this->get(route('box-not-full.export.show', $r))->assertOk()->assertSee('Siapkan data Not Full')->assertDontSee('Setujui dan catat stok');
        $this->post(route('box-not-full.export.approve', $r), ['revision' => 1, 'physical_confirmed' => 1])->assertForbidden();
        $this->post(route('box-not-full.export.prepare', $r), ['revision' => 1, 'mode' => 'external', 'box_number' => '123', 'notes' => 'Test'])->assertSessionHasErrors('box_number');
        $this->prepare($r);
        $this->actingAs($this->supervisor)->get(route('box-not-full.approvals', ['scope' => 'export']))->assertOk();
        $this->get(route('box-not-full.export.show', $r))->assertOk()->assertDontSee('Siapkan data Not Full')->assertSee('Setujui dan catat stok');
        $this->post(route('box-not-full.export.prepare', $r), ['revision' => 2, 'mode' => 'external', 'box_number' => '90000002', 'notes' => 'Test'])->assertForbidden();
        $this->post(route('box-not-full.export.approve', $r), ['revision' => 2])->assertSessionHasErrors('physical_confirmed');
        $this->actingAs($this->operator)->get(route('box-not-full.export.show', $r))->assertForbidden();
    }

    public function test_full_and_not_full_boxes_can_complete_one_pallet_and_ship_with_exact_pcs(): void
    {
        $box = $this->full(2);
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $s = app(ExportNotFullService::class);
        $this->assertSame(1, $s->pending()->count());
        $this->prepare($r);
        $this->approve($r);
        $this->assertSame(0, $s->pending()->count());
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()], $this->operator);
        $p = $d->palletPlan->pallets->sole();
        $completion = app(ExportFulfillmentCompletionService::class);
        $completion->ready($d->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'location_id' => $p->master_location_id], $this->operator);
        $completion->complete($d->id, ['revision' => 1, 'request_token' => (string) Str::uuid()], $this->operator);
        $this->assertSame('completed', ExportDeliveryPlan::withTrashed()->find($d->id)->status);
        $this->assertDatabaseCount('export_stock_boxes', 2);
        $this->assertSame(0, ExportStockBox::count());
        $data = app(OperationalDashboardService::class)->build(Request::create('/', 'GET', ['scope' => 'export', 'period' => 'all']));
        $this->assertSame(2, (int) $data['domains']['export']['outbound']->boxes);
        $this->assertSame(100, (int) $data['domains']['export']['outbound']->pcs);
    }

    public function test_reducing_full_box_retains_only_20_pcs_requirement_and_reuses_one_request(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r);
        $this->approve($r);
        $p = $d->palletPlan->pallets->sole();
        $partial = $p->items->firstWhere('pcs_per_box', 20);
        $full = $p->items->firstWhere('pcs_per_box', 80);
        $s = app(ExportFulfillmentCompletionService::class);
        $preview = $s->reduction($p, [$full->id => 0, $partial->id => 1]);
        $s->reduce($d->id, $p->id, ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'quantities' => $preview['quantities'], 'fingerprint' => $preview['fingerprint']], $this->operator);
        $this->assertSame(20, $d->fresh()->total_pcs);
        $this->assertSame(1, $d->fresh()->total_boxes);
        $this->assertSame(20, $d->fresh()->items->sole()->not_full_pcs);
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertDatabaseCount('export_not_full_requests', 1);
    }

    public function test_cancel_returns_partial_stock_and_cancels_mandatory_request(): void
    {
        $d = $this->finalize($this->delivery());
        $r = $this->nf($d);
        $this->prepare($r);
        $this->approve($r);
        $s = app(ExportFulfillmentCompletionService::class);
        $preview = $s->cancellation($d);
        $loc = ExportMasterLocation::create(['code' => 'CANCEL-NF']);
        $s->cancel($d->id, ['revision' => 1, 'request_token' => (string) Str::uuid(), 'fingerprint' => $preview['fingerprint'], 'reason' => 'Customer cancelled',
            'destination' => 'location:'.$loc->id, 'return_confirmed' => 1], $this->operator);
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame(20, ExportStockBox::sole()->pcs_quantity);
        $this->assertSame(0, app(ExportNotFullService::class)->pending()->count());
    }

    public function test_changed_final_destination_requires_a_new_preparation_before_approval(): void
    {
        $d = $this->finalize($this->delivery(20));
        $r = $this->nf($d);
        $this->prepare($r);
        app(ExportPalletPlanningService::class)->transition($d->id, 1, $this->operator);
        $d = $this->finalize($d->fresh('items', 'palletPlan'));
        $this->invalid(fn () => $this->approve($r), 'Rencana/lokasi berubah');
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $this->prepare($r);
        $this->approve($r);
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_source_with_inconsistent_location_cannot_be_split(): void
    {
        $source = $this->full(3);
        $d = $this->finalize($this->delivery(20));
        $r = $this->nf($d);
        ExportMasterLocation::whereKey($source->currentPallet->master_location_id)->update(['is_occupied' => false]);
        $this->assertNull(app(ExportNotFullService::class)->source($r));
        $this->invalid(fn () => $this->prepare($r, 'split'), 'Tidak tersedia box penuh');
        $this->assertSame(80, $source->fresh()->pcs_quantity);
    }

    public function test_unfulfilled_requests_stay_above_recent_completed_requests(): void
    {
        $waiting = $this->nf($this->delivery());
        $done = $this->nf($this->finalize($this->delivery(20)));
        $this->prepare($done);
        $this->approve($done);
        $response = $this->actingAs($this->warehouse)->get(route('box-not-full.create', ['scope' => 'export']));
        $response->assertOk();
        $this->assertSame($waiting->id, $response->viewData('requests')->first()->id);
    }
}
