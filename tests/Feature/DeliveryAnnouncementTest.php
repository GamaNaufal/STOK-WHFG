<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryAnnouncement;
use App\Models\DeliveryOrder;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportStockAllocationNotice;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\DeliveryAnnouncementService;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationNoticeService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $exporter;
    private PartSetting $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin_warehouse']);
        $this->exporter = User::factory()->create(['role' => 'export_operator']);
        $this->part = PartSetting::create(['part_number' => 'STICKER-A', 'qty_box' => 10]);
    }

    private function rule(string $type, int $quota): DeliveryAnnouncement
    {
        $this->actingAs($this->admin)->post(route('delivery-announcements.store'), [
            'customer_name' => '  EURONAK  ', 'part_number' => $this->part->part_number,
            'message' => 'Tempelkan stiker hijau', 'quota_type' => $type, 'quota' => $quota,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return DeliveryAnnouncement::latest('id')->firstOrFail();
    }

    private function local(string $customer = 'eUrOnAk', ?string $part = null): DeliveryOrder
    {
        $order = DeliveryOrder::create(['sales_user_id' => User::factory()->create(['role' => 'sales'])->id, 'customer_name' => $customer, 'delivery_date' => now()->addDays(3), 'status' => 'pending']);
        $order->items()->create(['part_number' => $part ?? $this->part->part_number, 'quantity' => 30, 'fulfilled_quantity' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'ppc']))->post(route('delivery.status', $order), [
            'status' => 'approved', 'notes' => 'Persetujuan test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $order->refresh();
    }

    private function export(string $code, array $counts = [2]): ExportDeliveryPlan
    {
        $delivery = app(ExportDeliveryPlanService::class)->save(['plan_code' => $code, 'customer_name' => 'euronak',
            'delivery_deadline' => now()->addDays(4)->toDateString(), 'source_type' => 'manual',
            'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => array_sum($counts) * 10]]], $this->exporter);
        $plan = app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
            'action' => 'finalize', 'pallets' => array_map(fn ($count, $i) => [
                'master_location_id' => ExportMasterLocation::create(['code' => $code.'-'.$i])->id,
                'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => $count]],
            ], $counts, array_keys($counts))], $this->exporter);
        $notice = ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->sole();
        app(ExportStockAllocationNoticeService::class)->acknowledge($delivery->id, $notice->id, $plan->revision, $this->exporter);

        return $delivery->refresh()->load('palletPlan.pallets');
    }

    private function receive(string $number)
    {
        $service = app(ExportStockReceivingService::class);
        $box = $service->receive(['box_number' => $number, 'part_number' => $this->part->part_number,
            'receive_token' => (string) Str::uuid()], $this->exporter);

        return $service->place($box->id, ['revision' => $box->revision, 'request_token' => (string) Str::uuid()], $this->exporter);
    }

    public function test_approval_and_finalization_share_delivery_quota_and_cancellation_refunds_it(): void
    {
        $rule = $this->rule('delivery', 2);
        $differentCustomer = $this->local('OTHER');
        $differentPart = $this->local(part: 'STICKER-B');
        $local = $this->local();
        $export = $this->export('ANN-DELIVERY');
        $waiting = $this->local();
        $this->assertSame('approved', $local->status);
        $this->assertSame(2, $rule->fresh('targets')->usage()['used']);
        $this->assertDatabaseMissing('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $differentCustomer->id]);
        $this->assertDatabaseMissing('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $differentPart->id]);
        $this->assertDatabaseHas('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $waiting->id, 'delivery_granted' => false]);

        $print = route('export.delivery-plans.print', $export);
        $this->actingAs($this->exporter)->get($print)->assertOk()->assertSee('Tempelkan stiker hijau');
        $this->get($print)->assertOk()->assertSee('Semua box part ini');
        $this->assertDatabaseCount('delivery_announcement_impressions', 1);
        $this->assertDatabaseHas('delivery_announcement_impressions', ['surface' => 'export_print_delivery',
            'delivery_reference' => $export->plan_code, 'user_id' => $this->exporter->id]);
        $this->get(route('delivery-announcements.show', $rule))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('delivery.destroy', $local))->assertRedirect()
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertDatabaseHas('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $local->id,
            'state' => 'cancelled', 'delivery_granted' => false]);
        $this->assertDatabaseHas('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $waiting->id, 'delivery_granted' => true]);
        $this->get(route('delivery.index'))->assertOk()->assertSee('Tempelkan stiker hijau');
        $this->get(route('delivery-announcements.show', $rule))->assertOk()
            ->assertSee('Riwayat penayangan')->assertSee($export->plan_code)->assertSee('Cetak delivery Ekspor');
    }

    public function test_box_quota_reserves_only_matching_ids_and_consumes_on_completion(): void
    {
        $rule = $this->rule('box', 2);
        $local = $this->local();
        $localBox = Box::create(['box_number' => '71001001', 'part_number' => $this->part->part_number,
            'pcs_quantity' => 10, 'qr_code' => '71001001', 'user_id' => $this->admin->id, 'assigned_delivery_order_id' => $local->id]);
        app(DeliveryAnnouncementService::class)->sync($local);

        $export = $this->export('ANN-BOXES', [1, 1]);
        $this->actingAs($this->exporter)->get(route('export.delivery-plans.show', $export))->assertOk()
            ->assertSee('Menunggu penetapan ID Box');
        $this->assertSame(1, $rule->fresh('targets')->usage()['remaining']);
        $first = $this->receive('71002001');
        $second = $this->receive('71002002');
        $this->assertSame(['used' => 0, 'reserved' => 2, 'remaining' => 0], $rule->fresh('targets')->usage());
        $this->assertSame([$localBox->box_number], $rule->targets()->where('scope', 'domestic')->sole()->reserved_boxes);
        $this->assertSame([$first->box_number], $rule->targets()->where('scope', 'export')->sole()->reserved_boxes);

        $this->actingAs($this->exporter);
        [$palletOne, $palletTwo] = $export->palletPlan->pallets->all();
        $this->get(route('export.pallet-plans.print-pallet', [$export, $palletOne]))->assertOk()->assertSee($first->box_number);
        $this->get(route('export.pallet-plans.print-pallet', [$export, $palletTwo]))->assertOk()->assertDontSee('Tempelkan stiker hijau');
        $this->get(route('export.fulfillment.show', $export))->assertOk()->assertSee('1 box terpilih');

        foreach ([$palletOne, $palletTwo] as $pallet) {
            $this->post(route('export.fulfillment.ready', [$export, $pallet]), ['revision' => $export->palletPlan->revision,
                'fulfillment_revision' => $pallet->fresh()->fulfillment_revision, 'location_id' => $pallet->master_location_id,
                'request_token' => (string) Str::uuid(), 'placement_confirmed' => 1])->assertSessionHasNoErrors();
        }
        $this->post(route('export.fulfillment.complete', $export), ['revision' => $export->palletPlan->revision,
            'request_token' => (string) Str::uuid(), 'shipped_confirmed' => 1])->assertSessionHasNoErrors();

        $this->assertSame(['used' => 1, 'reserved' => 1, 'remaining' => 0], $rule->fresh('targets')->usage());
        $target = $rule->targets()->where('scope', 'export')->sole();
        $this->assertSame([$first->box_number], $target->shipped_boxes);
        $this->assertNotContains($second->box_number, $target->shipped_boxes);
        $this->assertSame('Tempelkan stiker hijau', $target->closed_message);
    }

    public function test_local_picking_reserves_and_renders_the_box_before_consuming_it(): void
    {
        $rule = $this->rule('box', 1);
        $order = $this->local();
        $order->items()->update(['quantity' => 10]);
        $pallet = Pallet::create(['pallet_number' => 'ANN-PALLET-LOCAL']);
        StockLocation::create(['pallet_id' => $pallet->id, 'warehouse_location' => 'ANN-LOCAL', 'stored_at' => now()]);
        PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => $this->part->part_number, 'box_quantity' => 1, 'pcs_quantity' => 10]);
        $box = Box::create(['box_number' => '71001003', 'part_number' => $this->part->part_number, 'pcs_quantity' => 10,
            'qr_code' => '71001003', 'user_id' => $this->admin->id]);
        $pallet->boxes()->attach($box->id);

        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']));
        $session = $this->postJson(route('delivery.pick.start', $order))->assertOk()->json('session_id');
        $this->assertSame([$box->box_number], $rule->targets()->sole()->reserved_boxes);
        $this->get(route('delivery.pick.verify', [$order, $session]))->assertOk()->assertSee('Tempelkan stiker hijau')->assertSee($box->box_number);
        $this->get(route('delivery.pick.print-preview', [$order, $session]))->assertOk()->assertSee('Keterangan khusus');
        $this->postJson(route('delivery.pick.scan.submit', $session), ['box_number' => $box->box_number])->assertOk();
        $this->postJson(route('delivery.pick.complete', $session))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['used' => 1, 'reserved' => 0, 'remaining' => 0], $rule->fresh('targets')->usage());
        $this->assertDatabaseHas('delivery_announcement_impressions', ['surface' => 'local_verify', 'context' => 'Sesi #'.$session]);
    }

    public function test_managers_can_review_and_pause_but_operators_cannot_manage(): void
    {
        $order = $this->local();
        $rule = $this->rule('delivery', 1);
        $this->assertDatabaseHas('delivery_announcement_targets', ['scope' => 'domestic', 'delivery_id' => $order->id, 'delivery_granted' => true]);
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        $this->actingAs($supervisor)->get(route('delivery-announcements.create'))->assertOk()->assertSee('Jumlah delivery');
        $this->get(route('delivery-announcements.edit', $rule))->assertOk()->assertSee('Tempelkan stiker hijau');
        $this->patch(route('delivery-announcements.status', $rule), ['enabled' => 0])->assertRedirect();
        $this->assertFalse($rule->fresh()->enabled);
        $warehouse = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs($warehouse)->get(route('delivery.index'))->assertOk()->assertDontSee('Tempelkan stiker hijau');
        $this->patch(route('delivery-announcements.status', $rule), ['enabled' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('delivery-announcements.index'))
            ->assertOk()->assertSee('Nonaktif');
    }
}
