<?php

namespace Tests\Feature;

use App\Models\{Box, ExportFulfillmentBox, ExportMasterLocation, ExportPartSetting, ExportPickingIssue, ExportPickingScan, ExportStockBox, ExportStockMovement, ExportStockPallet, MasterLocation, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportPickingService, ExportStockAllocationNoticeService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportPickingVerificationTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private User $admin;
    private ExportPartSetting $part;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->admin = User::factory()->create(['role' => 'admin_warehouse']);
        $this->actingAs($this->operator);
        $this->part = ExportPartSetting::create(['part_number' => 'PICK-A', 'qty_box' => 10]);
    }

    private function receive(?ExportPartSetting $part = null): ExportStockBox
    {
        return app(ExportStockReceivingService::class)->receive(['box_number' => str_pad((string) ++$this->sequence, 8, '0', STR_PAD_LEFT),
            'part_number' => ($part ?? $this->part)->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
    }

    private function place(ExportStockBox $box, array $destination = []): void
    {
        app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()] + $destination, $this->operator);
    }

    private function plan(int $quantity = 1)
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'PICK-PLAN', 'customer_name' => 'Customer Pick', 'delivery_deadline' => '2026-11-10',
            'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => $quantity * $this->part->qty_box]]], $this->operator);
        $loc = ExportMasterLocation::create(['code' => 'TARGET']);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $loc->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => $quantity]]]]], $this->operator);
        $notice = \App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id, $notice->id, $plan->revision, $this->operator);
        return $plan->pallets()->firstOrFail();
    }

    private function mixed(): array
    {
        $other = ExportPartSetting::create(['part_number' => 'PICK-B', 'qty_box' => 20]);
        $first = $this->receive(); $second = $this->receive(); $third = $this->receive($other);
        $loc = ExportMasterLocation::create(['code' => 'SOURCE']);
        $this->place($first, ['location_id' => $loc->id]);
        $source = $first->fresh()->currentPallet;
        foreach ([$second, $third] as $box) $this->place($box, ['pallet_id' => $source->id]);
        return [$this->plan(), $first->fresh(), $second->fresh(), $source];
    }

    private function scan($pallet, string $number, ?string $token = null)
    {
        return $this->postJson(route('export.receiving.picking.scan', ['pallet' => $pallet->id]), ['box_number' => $number, 'request_token' => $token ?? (string) Str::uuid()]);
    }

    private function approve(ExportPickingIssue $issue, array $extra = [])
    {
        $this->actingAs($this->admin);
        $response = $this->post(route('delivery.pick.export-issue.approve', $issue), $extra + ['revision' => $issue->fresh()->revision, 'notes' => 'Periksa lalu kembalikan sesuai arahan.']);
        $this->actingAs($this->operator);
        return $response;
    }

    private function confirm(ExportPickingIssue $issue, array $extra = [])
    {
        return $this->post(route('export.receiving.picking.return', $issue), $extra + ['revision' => $issue->fresh()->revision, 'request_token' => (string) Str::uuid(), 'placement_confirmed' => '1']);
    }

    private function blockedPlacement(ExportStockBox $box): void
    {
        $this->postJson(route('export.receiving.place', $box), ['revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson(route('export.receiving.place-bulk'), ['placements_json' => json_encode([['id' => $box->id, 'revision' => $box->fresh()->revision, 'request_token' => (string) Str::uuid()]])])->assertUnprocessable();
    }

    public function test_historical_input_error_can_be_closed_by_admin_without_creating_stock(): void
    {
        $historical=$this->receive();$historical->update(['state'=>'shipped']);$historical->delete();
        [$pallet,$first]=$this->mixed();$token=(string)Str::uuid();
        $this->scan($pallet,$historical->box_number,$token)->assertStatus(423);
        $issue=ExportPickingIssue::sole();$stockCount=ExportStockBox::withTrashed()->count();$moves=ExportStockMovement::count();
        $data=['revision'=>$issue->revision,'request_token'=>(string)Str::uuid(),'notes'=>'Salah ketik, tidak ada box fisik yang diambil.','no_physical_box'=>1];
        $url=route('delivery.pick.export-issue.input-error',$issue);
        $this->actingAs($this->admin)->post($url,array_replace($data,['no_physical_box'=>0]))->assertSessionHasErrors('no_physical_box');
        $this->post($url,array_replace($data,['notes'=>'']))->assertSessionHasErrors('notes');
        $this->post($url,$data)->assertSessionHasNoErrors();$this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,array_replace($data,['notes'=>'Ubah alasan melalui retry']))->assertSessionHasErrors('revision');
        $this->assertSame('input_error',$issue->fresh()->status);$this->assertSame(0,ExportPickingIssue::open()->count());
        $this->assertNull($issue->fresh()->confirmed_at);$this->assertSame($stockCount,ExportStockBox::withTrashed()->count());
        $this->assertSame($moves,ExportStockMovement::count());$this->assertTrue(ExportStockBox::onlyTrashed()->whereKey($historical->id)->exists());
        $this->get(route('delivery.pick.issues'))->assertSee('SALAH INPUT · TANPA BOX FISIK');
        $this->actingAs($this->operator);$this->scan($pallet,$historical->box_number,$token)->assertJsonPath('blocked',false);
        $this->scan($pallet,$first->box_number)->assertOk();$this->place($first);
        $this->assertSame(1,\App\Models\AuditLog::where('type','export_picking_issue')->where('action','input_error_closed')->count());
    }

    public function test_operator_cannot_close_unknown_issue_as_input_error_even_with_valid_payload(): void
    {
        [$pallet]=$this->mixed();$this->scan($pallet,'99999999')->assertStatus(423);$issue=ExportPickingIssue::sole();
        $data=['revision'=>$issue->revision,'request_token'=>(string)Str::uuid(),'notes'=>'Typo','no_physical_box'=>1];
        $this->post(route('delivery.pick.export-issue.input-error',$issue),$data)->assertForbidden();
        $this->assertSame('pending',$issue->fresh()->status);
        $this->actingAs($this->admin)->post(route('delivery.pick.export-issue.input-error',$issue),$data)->assertSessionHasNoErrors();
        $this->assertFalse(ExportStockBox::where('box_number','99999999')->exists());
    }

    public function test_verified_unplaced_pick_requires_confirmed_return_during_cancellation(): void
    {
        [$pallet,$first,,$source]=$this->mixed();$this->scan($pallet,$first->box_number)->assertOk();
        $service=app(\App\Services\ExportFulfillmentCompletionService::class);$delivery=$pallet->palletPlan->deliveryPlan;
        $preview=$service->reduction($pallet,[$pallet->items()->sole()->id=>0]);$this->assertCount(1,$preview['returns']);
        $data=['revision'=>1,'fulfillment_revision'=>1,'request_token'=>(string)Str::uuid(),'quantities'=>$preview['quantities'],
            'fingerprint'=>$preview['fingerprint'],'destination'=>'pallet:'.$source->id];
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data)->assertSessionHasErrors('return_confirmed');
        $this->post(route('export.fulfillment.reduce',[$delivery,$pallet]),$data+['return_confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame($source->id,$first->fresh()->current_pallet_id);$this->assertNull($first->fresh()->fulfillment);
        $this->assertNull(\App\Models\ExportDeliveryPlan::find($delivery->id));
    }

    public function test_open_wrong_pick_issue_prevents_cancellation_until_real_return_is_confirmed(): void
    {
        [$pallet,,$wrong]=$this->mixed();$this->scan($pallet,$wrong->box_number)->assertStatus(423);
        $issue=ExportPickingIssue::sole();$this->approve($issue)->assertSessionHasNoErrors();
        $delivery=$pallet->palletPlan->deliveryPlan;
        $this->get(route('export.fulfillment.cancel-form',$delivery))->assertSessionHasErrors('fulfillment');
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->get(route('export.fulfillment.cancel-form',$delivery))->assertOk();
    }

    public function test_mixed_source_requires_exact_fifo_box_scan_and_helper_cannot_certify_a_pick(): void
    {
        [$pallet, $first, $second] = $this->mixed();
        $this->assertTrue($first->fulfillment->picking_required);
        $this->get(route('export.picking.index'))->assertOk()->assertSee('PICK-PLAN');
        $this->postJson(route('export.picking.seek', ['pallet' => $pallet->id]), ['box_number' => $second->box_number])->assertOk()->assertJsonPath('success', false)->assertJsonPath('blocked', false);
        $this->postJson(route('export.picking.seek', ['pallet' => $pallet->id]), ['box_number' => $first->box_number])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('export_picking_issues', 0);
        $this->assertNull($first->fresh()->fulfillment->picking_verified_at);
        $this->blockedPlacement($first);
        $this->scan($pallet, $first->box_number)->assertOk()->assertJsonPath('success', true)->assertJsonPath('blocked', false);
        $this->assertNotNull($first->fresh()->fulfillment->picking_verified_at);
        $this->place($first);
        $this->assertNotNull($first->fresh()->fulfillment->placed_at);
        $html = $this->getJson(route('export.stock.show', $first))->assertOk()->json('html');
        $this->assertStringContainsString('Penempatan sudah dikonfirmasi', $html);
        $this->assertStringNotContainsString('Buka verifikasi pengambilan', $html);
    }

    public function test_short_nonnumeric_and_long_inputs_do_not_create_issues_or_block(): void
    {
        [$pallet] = $this->mixed();
        foreach (['123', '123456789', '1234ABCD', '１２３４５６７８', ''] as $number) {
            $this->scan($pallet, $number)->assertUnprocessable()->assertJsonValidationErrors('box_number');
            $this->postJson(route('export.picking.seek', ['pallet' => $pallet->id]), ['box_number' => $number])->assertUnprocessable();
        }
        $this->assertDatabaseCount('export_picking_issues', 0); $this->assertDatabaseCount('export_picking_scans', 0);
    }

    public function test_approval_alone_does_not_unblock_until_operator_confirms_return(): void
    {
        [$pallet, $first, $wrong, $source] = $this->mixed();
        $originalDate = $wrong->first_received_at->toDateTimeString();
        $token = (string) Str::uuid();
        $this->scan($pallet, $wrong->box_number, $token)->assertStatus(423)->assertJsonPath('blocked', true);
        $this->scan($pallet, $wrong->box_number, $token)->assertStatus(423)->assertJsonPath('replay', true);
        $this->assertDatabaseCount('export_picking_issues', 1);
        $issue = ExportPickingIssue::sole();
        $this->get(route('export.receiving.picking.show', ['pallet' => $pallet->id]))->assertOk()->assertSee('SOURCE')->assertSee('Pengambilan diblokir');
        $this->confirm($issue)->assertSessionHasErrors('issue');
        $this->approve($issue)->assertSessionHasNoErrors()->assertRedirect(route('delivery.pick.issues'));
        $this->assertSame('awaiting_return', $issue->fresh()->status);
        $this->scan($pallet, $first->box_number)->assertStatus(423); $this->blockedPlacement($first);
        $this->assertSame($source->id, $wrong->fresh()->current_pallet_id);
        $returnToken = (string) Str::uuid();
        $this->confirm($issue, ['request_token' => $returnToken])->assertSessionHasNoErrors()->assertRedirect();
        $this->confirm($issue, ['request_token' => $returnToken])->assertSessionHasNoErrors();
        $this->assertSame('returned', $issue->fresh()->status);
        $this->assertSame($originalDate, $wrong->fresh()->first_received_at->toDateTimeString());
        $this->scan($pallet, $first->box_number)->assertOk()->assertJsonPath('success', true);
        $this->place($first);
    }

    public function test_unknown_eight_digit_box_is_registered_only_on_confirmed_placement_after_admin_supplies_part_and_destination(): void
    {
        [$pallet] = $this->mixed();
        Box::create(['box_number' => '00999999', 'part_number' => 'LOCAL', 'pcs_quantity' => 99, 'qr_code' => 'local', 'user_id' => $this->operator->id]);
        $this->scan($pallet, '00999999')->assertStatus(423);
        $issue = ExportPickingIssue::sole(); $this->assertSame('unknown_box', $issue->issue_type);
        $this->approve($issue)->assertSessionHasErrors('destination');
        $loc = ExportMasterLocation::create(['code' => 'RETURN']);
        $this->approve($issue, ['destination' => 'location:'.$loc->id])->assertSessionHasErrors('part_number');
        $this->approve($issue, ['destination' => 'location:'.$loc->id, 'part_number' => 'PICK-A'])->assertSessionHasNoErrors();
        $this->assertFalse(ExportStockBox::where('box_number', '00999999')->exists());
        $this->postJson(route('export.receiving.check-box'), ['box_number' => '00999999'])->assertJsonPath('available', false);
        $this->postJson(route('export.receiving.store'), ['box_number' => '00999999', 'part_number' => 'PICK-A', 'receive_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->confirm($issue)->assertSessionHasNoErrors();
        $box = ExportStockBox::where('box_number', '00999999')->sole();
        $this->assertSame(10, $box->pcs_quantity); $this->assertSame('RETURN', $box->currentPallet->location_code);
        $this->assertSame('stored', $box->state); $this->assertSame('returned', $issue->fresh()->status);
        $this->assertSame(1, Box::where('box_number', '00999999')->count());
        $this->assertSame(1, ExportStockMovement::where('export_stock_box_id', $box->id)->count());
    }

    public function test_known_box_without_location_needs_destination_but_keeps_part_pcs_and_fifo_date(): void
    {
        [$pallet] = $this->mixed(); $missing = $this->receive();
        $original = $missing->only(['part_setting_id', 'pcs_quantity', 'first_received_at']);
        $this->scan($pallet, $missing->box_number)->assertStatus(423); $issue = ExportPickingIssue::sole();
        $this->assertSame('missing_location', $issue->issue_type);
        $loc = ExportMasterLocation::create(['code' => 'RETURN']);
        $this->approve($issue, ['destination' => 'location:'.$loc->id])->assertSessionHasNoErrors();
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->assertEquals($original, $missing->fresh()->only(array_keys($original)));
        $this->assertSame('RETURN', $missing->fresh()->currentPallet->location_code);
    }

    public function test_other_operator_cannot_confirm_return_and_unapproved_or_stale_confirmation_does_not_unlock(): void
    {
        [$pallet, $first, $wrong] = $this->mixed(); $this->scan($pallet, $wrong->box_number)->assertStatus(423);
        $issue = ExportPickingIssue::sole(); $this->approve($issue)->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->confirm($issue)->assertForbidden();
        $this->blockedPlacement($first);
        $this->actingAs($this->operator);
        $this->confirm($issue, ['revision' => 1])->assertSessionHasErrors('revision');
        $this->confirm($issue, ['placement_confirmed' => '0'])->assertSessionHasErrors('placement_confirmed');
        $this->assertSame('awaiting_return', $issue->fresh()->status);
    }

    public function test_single_part_pallet_can_supply_36_of_72_boxes_without_any_unit_scans(): void
    {
        $boxes = collect(); for ($i = 0; $i < 72; $i++) $boxes->push($this->receive());
        $sourceLocation = ExportMasterLocation::create(['code' => 'SINGLE']);
        app(ExportStockReceivingService::class)->placeMany($boxes->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision,
            'request_token' => (string) Str::uuid(), 'location_id' => $sourceLocation->id])->all(), $this->operator);
        $pallet = $this->plan(36);
        $selected = ExportStockBox::whereHas('fulfillment')->orderBy('id')->get();
        $this->assertSame($boxes->take(36)->pluck('id')->all(), $selected->pluck('id')->all());
        $this->assertSame(0, ExportFulfillmentBox::where('picking_required', true)->count());
        $this->get(route('export.picking.index'))->assertOk()->assertSee('Belum ada tugas pallet campuran');
        app(ExportStockReceivingService::class)->placeMany($selected->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision,
            'request_token' => (string) Str::uuid()])->all(), $this->operator);
        $this->assertSame(36, ExportFulfillmentBox::whereNotNull('placed_at')->count());
        $this->assertDatabaseCount('export_picking_scans', 0);
        $this->assertSame(36, ExportStockPallet::where('master_location_id', $sourceLocation->id)->sole()->boxes()->count());
    }

    public function test_export_issues_share_approval_page_with_scope_labels_and_keep_admin_access_independent_of_export_masters(): void
    {
        [$pallet, , $wrong] = $this->mixed(); $this->scan($pallet, $wrong->box_number)->assertStatus(423);
        $this->actingAs($this->admin)->get(route('delivery.pick.issues'))->assertOk()->assertSee('Ekspor')->assertSee('PICK-PLAN')->assertSee($wrong->box_number);
        $this->actingAs($this->operator)->post(route('delivery.pick.export-issue.approve', ExportPickingIssue::sole()), ['revision' => 1, 'notes' => 'no'])->assertForbidden();
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => []]);
        $this->get(route('export.picking.index'))->assertForbidden();
        $this->scan($pallet, $wrong->box_number)->assertForbidden();
    }

    public function test_admin_cannot_use_domestic_master_for_an_unknown_export_box(): void
    {
        [$pallet] = $this->mixed(); $this->scan($pallet, '00888888')->assertStatus(423); $issue = ExportPickingIssue::sole();
        $domestic = MasterLocation::create(['code' => 'LOCAL-RETURN']);
        \App\Models\PartSetting::create(['part_number' => 'ONLY-LOCAL', 'qty_box' => 999]);
        $this->approve($issue, ['destination' => 'location:'.$domestic->id, 'part_number' => 'PICK-A'])->assertSessionHasErrors('destination');
        $export = ExportMasterLocation::create(['code' => 'EXPORT-RETURN']);
        $this->approve($issue, ['destination' => 'location:'.$export->id, 'part_number' => 'ONLY-LOCAL'])->assertSessionHasErrors('part_number');
        $this->assertSame('pending', $issue->fresh()->status);
        $this->assertFalse(ExportStockBox::where('box_number', '00888888')->exists());
    }

    public function test_changed_return_destination_needs_updated_admin_approval_and_stays_blocked(): void
    {
        [$pallet] = $this->mixed(); $this->scan($pallet, '00888888')->assertStatus(423); $issue = ExportPickingIssue::sole();
        $loc = ExportMasterLocation::create(['code' => 'RETURN']);
        $this->approve($issue, ['destination' => 'location:'.$loc->id, 'part_number' => 'PICK-A'])->assertSessionHasNoErrors();
        $another = User::factory()->create(['role' => 'export_operator']);
        $otherPart = ExportPartSetting::create(['part_number' => 'OTHER', 'qty_box' => 2]);
        $otherBox = app(ExportStockReceivingService::class)->receive(['box_number' => '00777777', 'part_number' => $otherPart->part_number,
            'receive_token' => (string)Str::uuid()], $another);
        app(ExportStockReceivingService::class)->place($otherBox->id, ['revision' => $otherBox->revision,
            'request_token' => (string)Str::uuid(), 'location_id' => $loc->id], $another);
        $this->confirm($issue)->assertSessionHasErrors('destination');
        $this->assertSame('awaiting_return', $issue->fresh()->status);
        $this->assertFalse(ExportStockBox::where('box_number', '00888888')->exists());
        $newLoc = ExportMasterLocation::create(['code' => 'RETURN-2']);
        $this->approve($issue, ['destination' => 'location:'.$newLoc->id, 'part_number' => 'PICK-A'])->assertSessionHasNoErrors();
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->assertSame('RETURN-2', ExportStockBox::where('box_number', '00888888')->sole()->currentPallet->location_code);
    }

    public function test_changed_part_capacity_requires_reapproval_before_registering_unknown_box(): void
    {
        [$pallet] = $this->mixed(); $this->scan($pallet, '00888888')->assertStatus(423); $issue = ExportPickingIssue::sole();
        $loc = ExportMasterLocation::create(['code' => 'RETURN']);
        $data = ['destination' => 'location:'.$loc->id, 'part_number' => 'PICK-A'];
        $this->approve($issue, $data)->assertSessionHasNoErrors();
        $this->part->update(['qty_box' => 30]);
        $this->confirm($issue)->assertSessionHasErrors('part_number');
        $this->assertFalse(ExportStockBox::where('box_number', '00888888')->exists());
        $this->approve($issue, $data)->assertSessionHasNoErrors();
        $this->confirm($issue)->assertSessionHasNoErrors();
        $this->assertSame(30, ExportStockBox::where('box_number', '00888888')->sole()->pcs_quantity);
    }

    public function test_source_that_becomes_mixed_after_recommendation_requires_verification(): void
    {
        $first = $this->receive(); $loc = ExportMasterLocation::create(['code' => 'SOURCE']);
        $this->place($first, ['location_id' => $loc->id]); $pallet = $this->plan();
        $this->assertFalse($first->fresh()->fulfillment->picking_required);
        $otherPart = ExportPartSetting::create(['part_number' => 'OTHER', 'qty_box' => 2]);
        $other = $this->receive($otherPart); $this->place($other, ['pallet_id' => $first->fresh()->current_pallet_id]);
        $this->assertTrue($first->fresh()->fulfillment->picking_required);
        $this->blockedPlacement($first);
        $this->scan($pallet, $first->box_number)->assertOk()->assertJsonPath('success', true);
        $this->place($first);
    }

    public function test_wrong_box_is_held_against_other_operators_and_scan_tokens_cannot_be_reused_for_different_data(): void
    {
        [$pallet, $first, $wrong] = $this->mixed(); $token = (string)Str::uuid();
        $this->scan($pallet, $wrong->box_number, $token)->assertStatus(423);
        $this->scan($pallet, $first->box_number, $token)->assertUnprocessable();
        $other = User::factory()->create(['role' => 'export_operator']);
        $loc = ExportMasterLocation::create(['code' => 'OTHER-RETURN']);
        $this->actingAs($other)->postJson(route('export.receiving.place', $wrong), ['revision' => $wrong->fresh()->revision,
            'request_token' => (string)Str::uuid(), 'destination' => 'location:'.$loc->id])->assertUnprocessable();
        $this->assertSame('SOURCE', $wrong->fresh()->currentPallet->location_code);
        $this->assertDatabaseCount('export_picking_issues', 1);
    }

    public function test_domestic_and_export_approval_rows_share_the_page_but_resolve_independently(): void
    {
        [$pallet, , $wrong] = $this->mixed(); $this->scan($pallet, $wrong->box_number)->assertStatus(423);
        $order = \App\Models\DeliveryOrder::create(['sales_user_id' => $this->admin->id, 'customer_name' => 'Customer Lokal',
            'delivery_date' => now()->addDay()->toDateString(), 'status' => 'processing']);
        $session = \App\Models\DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $this->admin->id, 'status' => 'blocked', 'started_at' => now()]);
        $localIssue = \App\Models\DeliveryIssue::create(['pick_session_id' => $session->id, 'scanned_code' => 'LOCAL-NG', 'issue_type' => 'scan_mismatch', 'status' => 'pending']);
        $this->actingAs($this->admin)->get(route('delivery.pick.issues'))->assertOk()->assertSee('Domestik / Lokal')->assertSee('Ekspor')->assertSee('Customer Lokal')->assertSee('PICK-PLAN');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['pending_scan_issues'] === 2);
        $this->post(route('delivery.pick.issue.approve', $localIssue), ['notes' => 'Koreksi lokal'])->assertRedirect();
        $this->assertSame('scanning', $session->fresh()->status);
        $this->assertSame('pending', ExportPickingIssue::sole()->status);
    }

    public function test_wrong_pick_invalidates_an_earlier_certificate_for_that_box_on_another_target(): void
    {
        [$pallet, , $wrong] = $this->mixed();
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => 'SECOND-PLAN', 'customer_name' => 'Customer Second', 'delivery_deadline' => '2026-11-09',
            'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 10]]], $this->operator);
        $loc = ExportMasterLocation::create(['code' => 'SECOND-TARGET']);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $loc->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]]], $this->operator);
        $notice = \App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id, $notice->id, $plan->revision, $this->operator);
        $otherTarget = $plan->pallets()->firstOrFail();
        $this->assertSame([$otherTarget->id, $pallet->id], app(ExportPickingService::class)->tasks()->pluck('export_planned_pallets.id')->all());
        $this->scan($otherTarget, $wrong->box_number)->assertOk()->assertJsonPath('success', true);
        $this->assertNotNull($wrong->fresh()->fulfillment->picking_verified_at);
        $this->scan($pallet, $wrong->box_number)->assertStatus(423);
        $this->assertNull($wrong->fresh()->fulfillment->picking_verified_at);
        $issue = ExportPickingIssue::sole(); $this->approve($issue)->assertSessionHasNoErrors(); $this->confirm($issue)->assertSessionHasNoErrors();
        $this->blockedPlacement($wrong);
        $this->scan($otherTarget, $wrong->box_number)->assertOk()->assertJsonPath('success', true);
        $this->place($wrong);
    }

    public function test_new_bulk_receipts_after_the_plan_need_no_picking_scan_even_when_destination_is_mixed(): void
    {
        $this->plan(2);
        $service = app(\App\Services\ExportReceivingBatchService::class);
        $batch = $service->start($this->operator)->fresh();
        foreach (['00000101', '00000102'] as $number) {
            $batch = $service->scan($batch->id, ['box_number' => $number, 'part_number' => $this->part->part_number,
                'revision' => $batch->revision, 'scan_token' => (string)Str::uuid()], $this->operator);
        }
        $batch = $service->process($batch->id, $batch->revision, $this->operator);
        $boxes = ExportStockBox::with('fulfillment')->whereIn('box_number', ['00000101', '00000102'])->orderBy('id')->get();
        foreach ($boxes as $box) {
            $this->assertNull($box->fulfillment->allocation_notice_id);
            $this->assertFalse($box->fulfillment->picking_required);
        }
        $this->place($boxes[0]);
        $otherPart = ExportPartSetting::create(['part_number' => 'NEW-OTHER', 'qty_box' => 20]);
        $other = $this->receive($otherPart);
        $this->place($other, ['pallet_id' => $boxes[0]->fresh()->current_pallet_id]);
        $this->assertTrue(ExportStockPallet::whereKey($boxes[0]->fresh()->current_pallet_id)->mixed()->exists());
        $this->get(route('export.receiving.transactions.show', $batch))->assertOk()
            ->assertSee('Konfirmasi 1 box sudah ditempatkan')->assertSee('Penerimaan box baru tidak memerlukan verifikasi pengambilan tambahan.');
        $this->place($boxes[1]);
        $this->assertSame(2, ExportFulfillmentBox::whereNotNull('placed_at')->count());
        $this->assertSame(0, app(ExportPickingService::class)->tasks()->count());
        $this->assertDatabaseCount('export_picking_scans', 0);
        $this->assertDatabaseCount('export_picking_issues', 0);
    }

    public function test_receiving_allocation_from_a_mixed_source_without_plan_notice_does_not_require_verification(): void
    {
        $pallet = $this->plan();
        $service = app(ExportStockReceivingService::class);
        $otherPart = ExportPartSetting::create(['part_number' => 'LATER-OTHER', 'qty_box' => 20]);
        $first = $service->receive(['box_number' => '00000101', 'part_number' => $this->part->part_number, 'receive_token' => (string)Str::uuid()], $this->operator, false);
        $other = $service->receive(['box_number' => '00000102', 'part_number' => $otherPart->part_number, 'receive_token' => (string)Str::uuid()], $this->operator, false);
        $source = ExportMasterLocation::create(['code' => 'LATER-SOURCE']);
        $this->place($first, ['location_id' => $source->id]);
        $this->place($other, ['pallet_id' => $first->fresh()->current_pallet_id]);
        app(\App\Services\ExportInventoryService::class)->transaction(fn () => app(\App\Services\ExportStockAllocationService::class)->allocate($this->operator));
        $f = $first->fresh()->fulfillment;
        $this->assertNull($f->allocation_notice_id); $this->assertFalse($f->picking_required);
        app(ExportPickingService::class)->requireForSource($first->fresh()->current_pallet_id);
        $this->assertFalse($f->fresh()->picking_required);
        // A flag retained from an earlier deployment must not change the allocation's origin.
        $f->update(['picking_required' => true]);
        $this->assertFalse(app(ExportPickingService::class)->rows($pallet->id)->sole()->requires_scan);
        $this->get(route('export.picking.index'))->assertOk()->assertSee('Belum ada tugas pallet campuran');
        $this->place($first);
        $this->assertNotNull($first->fresh()->fulfillment->placed_at);
        $this->assertDatabaseCount('export_picking_scans', 0); $this->assertDatabaseCount('export_picking_issues', 0);
    }
}
