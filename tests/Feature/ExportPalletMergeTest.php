<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportNotFullRequest;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportBoxRequirements;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportFulfillmentCompletionService;
use App\Services\ExportFulfillmentRevisionService;
use App\Services\ExportNotFullService;
use App\Services\ExportPalletMergeService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationNoticeService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExportPalletMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private PartSetting $part;

    private int $sequence = 0;

    private int $locations = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($this->operator);
        $this->part = PartSetting::create(['part_number' => 'MERGE-A', 'qty_box' => 80]);
    }

    private function location(): ExportMasterLocation
    {
        return ExportMasterLocation::create(['code' => 'MERGE-'.++$this->locations]);
    }

    private function box(?PartSetting $part = null, ?int $pcs = null): ExportStockBox
    {
        $part ??= $this->part;

        return ExportStockBox::create(['box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
            'part_setting_id' => $part->id, 'part_number' => $part->part_number, 'pcs_quantity' => $pcs ?? $part->qty_box,
            'standard_pcs_quantity' => $part->qty_box, 'is_not_full' => $pcs !== null && $pcs < $part->qty_box,
            'state' => 'received', 'receive_token' => (string) Str::uuid(), 'first_received_at' => now()->subDays(100 - $this->sequence),
            'revision' => 1, 'received_by' => $this->operator->id]);
    }

    private function place(ExportStockBox $box, array $destination = []): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->place($box->id,
            ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()] + $destination, $this->operator);
    }

    private function spare(?PartSetting $part = null, ?int $pcs = null): ExportStockBox
    {
        return $this->place($this->box($part, $pcs), ['location_id' => $this->location()->id]);
    }

    private function plan(array $counts = [2, 2], ?int $notFull = null): ExportDeliveryPlan
    {
        $d = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'MERGE-RD-'.ExportDeliveryPlan::withTrashed()->count(),
            'customer_name' => 'Same customer', 'delivery_deadline' => '2026-11-20', 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => array_sum($counts) * 80 + ($notFull ?? 0)]]], $this->operator);
        $rows = array_map(fn ($count) => ['master_location_id' => $this->location()->id,
            'items' => [['part_setting_id' => $this->part->id, 'pcs_per_box' => 80, 'box_quantity' => $count]]], $counts);
        if ($notFull) {
            $rows[0]['items'][] = ['part_setting_id' => $this->part->id, 'pcs_per_box' => $notFull, 'box_quantity' => 1];
        }
        $p = app(ExportPalletPlanningService::class)->save($d->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize', 'pallets' => $rows], $this->operator);
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id', $p->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($d->id, $notice->id, $p->revision, $this->operator);

        return $d->fresh('palletPlan.pallets.items');
    }

    private function fill(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $b = app(ExportStockReceivingService::class)->receive(['box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
                'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
            $this->place($b);
        }
    }

    private function data(array $sources, string $mode = 'fill', ?int $locationId = null): array
    {
        $data = ['pallet_ids' => $sources, 'location_id' => $locationId ?? $this->location()->id, 'spare_mode' => $mode];
        $p = app(ExportPalletMergeService::class)->preview($data, $this->operator);

        return $data + ['fingerprint' => $p['fingerprint'], 'request_token' => (string) Str::uuid(),
            'physical_confirmed' => 1, 'ready_confirmed' => 1, 'demand_confirmed' => 1];
    }

    private function invalid(callable $work, string $message): void
    {
        try {
            $work();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, implode(' ', $e->validator->errors()->all()));
        }
    }

    public function test_spare_merge_preserves_box_identity_fifo_stock_totals_and_is_idempotent(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $before = $a->toArray();
        $data = $this->data([$a->current_pallet_id, $b->current_pallet_id], 'fill', $a->currentPallet->master_location_id);
        $r = app(ExportPalletMergeService::class)->merge($data, $this->operator);
        $this->assertSame($r, app(ExportPalletMergeService::class)->merge($data, $this->operator));
        $this->assertSame(2, ExportStockBox::count());
        $this->assertSame($r['result_pallet_id'], $a->fresh()->current_pallet_id);
        $this->assertSame($a->fresh()->current_pallet_id, $b->fresh()->current_pallet_id);
        $this->assertSame($before['first_received_at'], $a->fresh()->toArray()['first_received_at']);
        $this->assertDatabaseCount('export_pallet_merges', 1);
        $this->assertSame(1, AuditLog::where('type', 'export_pallet_merge')->count());
        $audit = \App\Support\AuditLogPresenter::present(AuditLog::where('type', 'export_pallet_merge')->sole());
        $this->assertNotEmpty(collect($audit['tables'])->first(fn ($t) => $t['title'] === 'Rincian pallet sebelum aksi'));
        $this->assertSame(1, ExportStockPallet::whereNull('retired_at')->count());
        $this->get(route('export.merge.result', $data['request_token']))->assertOk()->assertSee('Pallet sudah digabung');
    }

    public function test_same_delivery_merge_preserves_unfilled_demand_and_smallest_position(): void
    {
        $d = $this->plan();
        $this->fill(3);
        $sources = ExportStockPallet::whereNull('retired_at')->pluck('id')->all();
        $r = app(ExportPalletMergeService::class)->merge($this->data($sources), $this->operator);
        $p = $d->fresh('palletPlan.pallets')->palletPlan->pallets->sole();
        $this->assertSame(1, $p->position);
        $this->assertSame(4, $p->items()->sum('box_quantity'));
        $this->assertSame(3, $p->fulfillmentBoxes()->whereNotNull('placed_at')->count());
        $this->assertSame(4, $d->fresh()->total_boxes);
        $this->assertTrue($d->fresh()->palletPlan->isFinalFor($d->fresh()));
        $this->assertSame(2, $r['plan_revision']);
        $audit = \App\Support\AuditLogPresenter::present(AuditLog::where('type', 'export_pallet_merge')->sole());
        $this->assertNotEmpty(collect($audit['tables'])->first(fn ($t) => $t['title'] === 'Perubahan kebutuhan saat aksi'));
        app(ExportFulfillmentRevisionService::class)->contents($p);
        $this->get(route('export.pallet-plans.show', $d))->assertOk()->assertSee('Pallet 1')->assertDontSee('Pallet 2');
        $this->get(route('export.pallet-plans.print', $d))->assertOk();
    }

    public function test_cross_delivery_merge_is_rejected_even_for_the_same_customer(): void
    {
        $this->plan([1]);
        $this->fill(1);
        $this->plan([1]);
        $this->fill(1);
        $this->invalid(fn () => $this->data(ExportStockPallet::whereNull('retired_at')->pluck('id')->all()), 'Lintas rencana delivery');
        $this->assertDatabaseCount('export_pallet_merges', 0);
    }

    public function test_ready_status_requires_explicit_warning_acceptance_and_only_selected_readiness_is_cancelled(): void
    {
        $d = $this->plan([1, 1, 1]);
        $this->fill(3);
        $pallets = $d->palletPlan->pallets;
        foreach ($pallets as $p) {
            app(ExportFulfillmentCompletionService::class)->ready($d->id, $p->id,
                ['revision' => 1, 'fulfillment_revision' => 1, 'request_token' => (string) Str::uuid(), 'location_id' => $p->master_location_id, 'physical_confirmed' => 1], $this->operator);
        }
        $sources = ExportStockPallet::whereIn('export_planned_pallet_id', $pallets->take(2)->pluck('id'))->pluck('id')->all();
        $data = $this->data($sources);
        unset($data['ready_confirmed']);
        $this->invalid(fn () => app(ExportPalletMergeService::class)->merge($data, $this->operator), 'status siap');
        $data['ready_confirmed'] = 1;
        app(ExportPalletMergeService::class)->merge($data, $this->operator);
        $this->assertNull($pallets[0]->fresh()->ready_at);
        $this->assertNotNull($pallets[2]->fresh()->ready_at);
    }

    public function test_spare_mode_can_fill_unreserved_demand_or_add_all_boxes(): void
    {
        $d = $this->plan([3]);
        $this->fill(1);
        $spare = $this->spare();
        $target = ExportStockPallet::whereNotNull('export_planned_pallet_id')->sole();
        $data = ['pallet_ids' => [$target->id, $spare->current_pallet_id], 'location_id' => $target->master_location_id, 'spare_mode' => 'fill'];
        $preview = app(ExportPalletMergeService::class)->preview($data, $this->operator);
        $this->assertSame(0, $preview['added_boxes']);
        $data['spare_mode'] = 'add';
        $preview = app(ExportPalletMergeService::class)->preview($data, $this->operator);
        $this->assertSame(1, $preview['added_boxes']);
        $data = $this->data($data['pallet_ids'], 'add', $target->master_location_id);
        unset($data['demand_confirmed']);
        $this->invalid(fn () => app(ExportPalletMergeService::class)->merge($data, $this->operator), 'penambahan kebutuhan');
        $data['demand_confirmed'] = 1;
        app(ExportPalletMergeService::class)->merge($data, $this->operator);
        $this->assertSame(4, $d->fresh()->total_boxes);
        $this->assertSame(2, ExportFulfillmentBox::whereNotNull('placed_at')->count());
    }

    public function test_new_spare_allocation_cannot_skip_an_older_free_box_elsewhere(): void
    {
        $other = PartSetting::create(['part_number' => 'MERGE-B', 'qty_box' => 40]);
        $old = $this->spare($other);
        $new = $this->spare($other);
        $this->plan([1]);
        $this->fill(1);
        $target = ExportStockPallet::whereNotNull('export_planned_pallet_id')->sole();
        $this->invalid(fn () => $this->data([$target->id, $new->current_pallet_id]), 'FIFO: ambil ID Box '.$old->box_number);
    }

    public function test_preview_becomes_stale_and_replay_token_cannot_change_payload(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $data = $this->data([$a->current_pallet_id, $b->current_pallet_id]);
        $this->place($this->box(), ['pallet_id' => $a->current_pallet_id]);
        $this->invalid(fn () => app(ExportPalletMergeService::class)->merge($data, $this->operator), 'sudah berubah');
        $data = $this->data([$a->current_pallet_id, $b->current_pallet_id]);
        app(ExportPalletMergeService::class)->merge($data, $this->operator);
        $data['spare_mode'] = 'add';
        $this->invalid(fn () => app(ExportPalletMergeService::class)->merge($data, $this->operator), 'Token merge');
    }

    public function test_cross_delivery_reservations_block_both_spare_and_request_merges(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $first = $this->plan([1]);
        $this->plan([1]);
        $this->invalid(fn () => $this->data([$a->current_pallet_id, $b->current_pallet_id]), 'Lintas rencana delivery');
        $this->startRecommendation($a);
        $this->place($a->fresh());
        $physical = ExportStockPallet::where('export_planned_pallet_id', $first->palletPlan->pallets->sole()->id)->sole();
        $this->invalid(fn () => $this->data([$physical->id, $b->current_pallet_id]), 'delivery lain');
    }

    public function test_spare_merge_preserves_unstarted_reservations_within_one_delivery(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $this->plan([2]);
        $ids = ExportFulfillmentBox::orderBy('id')->pluck('export_planned_pallet_item_id')->all();
        app(ExportPalletMergeService::class)->merge($this->data([$a->current_pallet_id, $b->current_pallet_id]), $this->operator);
        $this->assertSame($ids, ExportFulfillmentBox::orderBy('id')->pluck('export_planned_pallet_item_id')->all());
        $this->assertSame(2, ExportFulfillmentBox::whereNull('placed_at')->count());
        $this->assertSame($a->fresh()->current_pallet_id, $b->fresh()->current_pallet_id);
    }

    public function test_started_picking_and_open_issue_block_merging(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $d = $this->plan([2]);
        $f = $a->fresh()->fulfillment;
        $f->update(['picking_verified_at' => now(), 'picking_verified_revision' => $a->fresh()->revision, 'picking_source_pallet_id' => $a->current_pallet_id]);
        $this->invalid(fn () => $this->data([$a->current_pallet_id, $b->current_pallet_id]), 'Pengambilan sudah berjalan');
        $f->update(['picking_verified_at' => null]);
        \App\Models\ExportPickingIssue::create(['export_planned_pallet_id' => $d->palletPlan->pallets->sole()->id,
            'export_stock_box_id' => $a->id, 'box_number' => $a->box_number, 'issue_type' => 'wrong_box', 'status' => 'pending',
            'context' => [], 'reported_by' => $this->operator->id, 'registration_token' => (string) Str::uuid()]);
        $this->invalid(fn () => $this->data([$a->current_pallet_id, $b->current_pallet_id]), 'issue picking');
    }

    public function test_merge_retains_multiple_not_full_boxes_without_repacking_and_reduction_preserves_composition(): void
    {
        $d = $this->plan([1], 20);
        $this->fill(1);
        $nf = $this->spare(null, 20);
        $target = ExportStockPallet::whereNotNull('export_planned_pallet_id')->sole();
        app(ExportPalletMergeService::class)->merge($this->data([$target->id, $nf->current_pallet_id], 'add'), $this->operator);
        $line = $d->fresh('items')->items->sole();
        $catalog = ExportBoxRequirements::catalog($d->fresh('items'));
        $this->assertSame(3, $line->box_quantity);
        $this->assertSame(120, $line->pcs_quantity);
        $this->assertSame(2, $catalog[ExportBoxRequirements::key($this->part->id, 20)]['required']);
        $r = ExportNotFullRequest::where('export_delivery_plan_id', $d->id)->sole();
        $this->assertSame(2, $r->required_boxes);
        $this->assertSame(1, app(ExportNotFullService::class)->pending()->whereKey($r->id)->count());
        $warehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        app(ExportNotFullService::class)->prepare($r->id, ['revision' => $r->revision, 'mode' => 'external', 'box_number' => '99000001', 'notes' => 'Not Full merge'], $warehouse);
        app(ExportNotFullService::class)->approve($r->id, ['revision' => $r->fresh()->revision, 'physical_confirmed' => 1], $supervisor);
        $this->assertSame(0, app(ExportNotFullService::class)->pending()->whereKey($r->id)->count());
        $p = $d->fresh('palletPlan.pallets.items')->palletPlan->pallets->sole();
        $quantities = $p->items->mapWithKeys(fn ($i) => [$i->id => $i->pcs_per_box === 20 ? 1 : $i->box_quantity])->all();
        $preview = app(ExportFulfillmentRevisionService::class)->preview($p, $quantities);
        $reuse = [];
        app(\App\Services\ExportInventoryService::class)->transaction(function () use ($d, $p, $preview, &$reuse) {
            app(ExportFulfillmentRevisionService::class)->apply($d->fresh(), $p, $preview, 'location:'.$this->location()->id, $this->operator, $reuse);
        });
        $this->assertSame(2, $line->fresh()->box_quantity);
        $this->assertSame(100, $line->fresh()->pcs_quantity);
        $this->get(route('export.delivery-plans.print', $d))->assertOk()->assertSee('1 Not Full 20 PCS');
    }

    public function test_authorization_respects_roles_and_explicit_feature_revocation(): void
    {
        $this->get(route('export.merge.index'))->assertOk()->assertSee('Merge Pallet Ekspor');
        foreach (['admin', 'admin_warehouse'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('export.merge.index'))->assertOk();
        }
        foreach (['warehouse_operator', 'supervisi', 'sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('export.merge.index'))->assertForbidden();
        }
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_stock_input']]);
        $this->actingAs($this->operator)->get(route('export.merge.index'))->assertForbidden();
    }

    public function test_http_preview_confirm_and_result_show_revision_and_location_without_double_merge(): void
    {
        $d = $this->plan([1, 1]);
        $this->fill(2);
        $data = $this->data(ExportStockPallet::whereNull('retired_at')->pluck('id')->all());
        $this->get(route('export.merge.index'))->assertOk()->assertSee('MERGE-A (1 box)')->assertSee('Same customer');
        $this->post(route('export.merge.preview'), $data)->assertOk()->assertSee('Tinjau sebelum digabung')->assertSee('Konfirmasi fisik');
        $this->post(route('export.merge.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('export.merge.result', $data['request_token']));
        $this->post(route('export.merge.store'), $data)->assertSessionHasNoErrors();
        $this->get(route('export.merge.result', $data['request_token']))->assertOk()->assertSee('Revisi 2');
        $this->assertSame(2, $d->fresh()->revision);
        $this->assertDatabaseCount('export_pallet_merges', 1);
    }

    public function test_pending_reservations_retarget_and_unreviewed_fifo_can_still_be_acknowledged_after_merge(): void
    {
        $outside = $this->spare();
        $d = $this->plan([2, 2]);
        // Keep the older box at its source; newly received boxes fill both planned pallets.
        $this->fill(2);
        $this->fill(1);
        $plan = $d->palletPlan;
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole();
        $notice->update(['acknowledged_at' => null, 'acknowledged_by' => null]);
        $sources = ExportStockPallet::whereNotNull('export_planned_pallet_id')->pluck('id')->all();
        app(ExportPalletMergeService::class)->merge($this->data($sources), $this->operator);
        $f = $outside->fresh()->fulfillment;
        $target = $d->fresh('palletPlan.pallets')->palletPlan->pallets->sole();
        $this->assertSame($target->id, $f->plannedItem->export_planned_pallet_id);
        $this->assertSame($outside->current_pallet_id, $outside->fresh()->current_pallet_id);
        $freshNotice = ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->orderByDesc('id')->first();
        $this->assertSame(2, $freshNotice->plan_revision);
        app(ExportStockAllocationNoticeService::class)->acknowledge($d->id, $freshNotice->id, 2, $this->operator);
        $this->startRecommendation($outside);
        $this->place($outside->fresh());
        $this->assertNotNull($outside->fresh()->fulfillment->placed_at);
    }

    private function startRecommendation(ExportStockBox $box): void
    {
        $f = $box->fresh()->fulfillment;
        $picking = app(\App\Services\ExportPickingService::class);
        $pallet = $f->plannedItem->export_planned_pallet_id;
        $picking->start($pallet, $f->allocation_notice_id, $picking->startFingerprint($picking->rows($pallet, $f->allocation_notice_id)), $this->operator);
    }

    public function test_prepared_not_full_is_invalidated_and_retargeted_to_new_location(): void
    {
        $d = $this->plan([1, 1], 20);
        $this->fill(2);
        $r = ExportNotFullRequest::sole();
        $admin = User::factory()->create(['role' => 'admin']);
        app(ExportNotFullService::class)->prepare($r->id, ['revision' => $r->revision, 'mode' => 'external', 'box_number' => '99000002', 'notes' => 'Prepared before merge'], $admin);
        $data = $this->data(ExportStockPallet::whereNull('retired_at')->pluck('id')->all());
        app(ExportPalletMergeService::class)->merge($data, $this->operator);
        $this->assertSame('waiting', $r->fresh()->status);
        $this->assertNull($r->fresh()->preparation);
        $target = app(ExportNotFullService::class)->target($r->fresh(['item', 'delivery.palletPlan']));
        $this->assertSame((int) $data['location_id'], $target->pallet->master_location_id);
    }

    public function test_merging_a_mixed_source_cannot_bypass_mandatory_picking_verification(): void
    {
        $old = $this->spare();
        $other = PartSetting::create(['part_number' => 'MERGE-B', 'qty_box' => 40]);
        $this->place($this->box($other), ['pallet_id' => $old->current_pallet_id]);
        $d = $this->plan([2]);
        $this->fill(1);
        $target = ExportStockPallet::whereNotNull('export_planned_pallet_id')->sole();
        app(ExportPalletMergeService::class)->merge($this->data([$old->current_pallet_id, $target->id]), $this->operator);
        $f = $old->fresh()->fulfillment;
        $pallet = $f->plannedItem->pallet;
        $this->assertTrue($f->merge_pending_verification);
        $this->assertNull($f->placed_at);
        $reduction = app(ExportFulfillmentRevisionService::class)->preview($pallet, $pallet->items()->get()
            ->mapWithKeys(fn ($i) => [$i->id => $i->part_setting_id === $this->part->id ? 0 : $i->box_quantity])->all());
        $this->assertTrue($reduction['returns']->contains('id', $f->id));
        $this->assertCount(0, $reduction['released']);
        $this->invalid(fn () => $this->place($old->fresh()), 'Scan verifikasi');
        app(ExportFulfillmentRevisionService::class)->contents($pallet);
        $result = app(\App\Services\ExportPickingService::class)->scan($pallet->id, ['box_number' => $old->box_number, 'request_token' => (string) Str::uuid()], $this->operator, $f->allocation_notice_id);
        $this->assertTrue($result['success']);
        $this->place($old->fresh());
        $this->assertNotNull($f->fresh()->placed_at);
        $this->assertFalse($f->fresh()->merge_pending_verification);
        app(ExportFulfillmentCompletionService::class)->ready($d->id, $pallet->id,
            ['revision' => 2, 'fulfillment_revision' => $pallet->fulfillment_revision, 'request_token' => (string) Str::uuid(), 'location_id' => $pallet->master_location_id], $this->operator);
        $this->assertNotNull($pallet->fresh()->ready_at);
    }

    public function test_fill_mode_does_not_inflate_delivery_and_can_use_a_selected_source_location(): void
    {
        $d = $this->plan([3]);
        $this->fill(1);
        $spare = $this->spare();
        $target = ExportStockPallet::whereNotNull('export_planned_pallet_id')->sole();
        app(ExportPalletMergeService::class)->merge($this->data([$target->id, $spare->current_pallet_id], 'fill', $spare->currentPallet->master_location_id), $this->operator);
        $this->assertSame(3, $d->fresh()->total_boxes);
        $this->assertSame(240, $d->fresh()->total_pcs);
        $this->assertSame(2, ExportFulfillmentBox::whereNotNull('placed_at')->count());
        $this->assertFalse(ExportMasterLocation::find($target->master_location_id)->is_occupied);
    }

    public function test_non_export_and_other_reserved_locations_are_rejected_and_failure_is_atomic(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $d = $this->plan([1]);
        $this->invalid(fn () => $this->data([$a->current_pallet_id, $b->current_pallet_id], 'fill', $d->palletPlan->pallets->sole()->master_location_id), 'Lokasi tujuan');
        $local = \App\Models\MasterLocation::create(['code' => 'LOCAL-MERGE']);
        $this->invalid(fn () => $this->data([$a->current_pallet_id, $b->current_pallet_id], 'fill', $local->id), 'Pilih lokasi ekspor');
        $this->assertSame(2, ExportStockPallet::whereNull('retired_at')->count());
        $this->assertDatabaseCount('export_pallet_merges', 0);
    }

    public function test_failure_after_physical_writes_rolls_back_stock_locations_plan_and_audit(): void
    {
        $d = $this->plan([1, 1]);
        $this->fill(2);
        $data = $this->data(ExportStockPallet::whereNull('retired_at')->pluck('id')->all());
        $before = ExportStockBox::orderBy('id')->pluck('current_pallet_id')->all();
        DB::statement("CREATE TRIGGER reject_export_merge BEFORE INSERT ON export_pallet_merges BEGIN SELECT RAISE(ABORT, 'simulated storage failure'); END");
        try {
            app(ExportPalletMergeService::class)->merge($data, $this->operator);
            $this->fail('Expected storage failure.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('simulated storage failure', $e->getMessage());
        }
        $this->assertSame($before, ExportStockBox::orderBy('id')->pluck('current_pallet_id')->all());
        $this->assertSame(1, $d->fresh()->revision);
        $this->assertSame(2, $d->palletPlan->pallets()->count());
        $this->assertDatabaseCount('export_pallet_merges', 0);
        $this->assertSame(0, AuditLog::where('type', 'export_pallet_merge')->count());
        $this->assertFalse(ExportMasterLocation::find($data['location_id'])->is_occupied);
    }

    public static function loadCases(): array
    {
        return [[36, 'warning'], [72, 'danger']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('loadCases')]
    public function test_preview_preserves_capacity_warning_at_36_and_72_boxes(int $count, string $level): void
    {
        $this->plan([1, $count - 1]);
        $this->fill(2);
        $data = $this->data(ExportStockPallet::whereNull('retired_at')->pluck('id')->all());
        $this->post(route('export.merge.preview'), $data)->assertOk()->assertSee('data-level="'.$level.'"', false);
    }

    public function test_stale_browser_confirmation_returns_to_the_form_with_visible_error_and_preserves_selection(): void
    {
        $a = $this->spare();
        $b = $this->spare();
        $data = $this->data([$a->current_pallet_id, $b->current_pallet_id]);
        $this->place($this->box(), ['pallet_id' => $a->current_pallet_id]);
        $this->from(route('export.merge.preview'))->post(route('export.merge.store'), $data)
            ->assertRedirect(route('export.merge.index'))->assertSessionHasErrors('merge');
        $this->get(route('export.merge.index'))->assertOk()->assertSee('Merge belum diproses.')->assertSee('sudah berubah');
        $this->assertDatabaseCount('export_pallet_merges', 0);
    }
}
