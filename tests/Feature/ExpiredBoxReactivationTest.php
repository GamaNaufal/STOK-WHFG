<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\ExpiredBoxReport;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredBoxReactivationTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $ageMonths = 13): array
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0));
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        PartSetting::create(['part_number' => 'P-RENEW', 'qty_box' => 20]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-RENEW']);
        $location = MasterLocation::create(['code' => 'RENEW-01', 'is_occupied' => false]);
        StockLocation::create([
            'pallet_id' => $pallet->id, 'master_location_id' => $location->id,
            'warehouse_location' => $location->code, 'stored_at' => now()->subMonthsNoOverflow($ageMonths),
        ]);
        $box = Box::create([
            'box_number' => '99110001', 'part_number' => 'P-RENEW', 'pcs_quantity' => 20,
            'qty_box' => 1, 'qr_code' => '99110001', 'user_id' => $operator->id,
            'expired_status' => 'active', 'created_at' => now()->subMonthsNoOverflow($ageMonths),
        ]);
        $pallet->boxes()->attach($box->id);
        PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => 'P-RENEW', 'box_quantity' => 1, 'pcs_quantity' => 20]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $operator->id, 'customer_name' => 'Renewal',
            'delivery_date' => now()->addDay(), 'status' => 'approved',
        ]);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => 'P-RENEW', 'quantity' => 20, 'fulfilled_quantity' => 0]);

        return [$operator, $supervisor, $box, $pallet, $location, $order];
    }

    public function test_warning_remains_available_to_fifo_without_handle(): void
    {
        [$operator, , $box, , , $order] = $this->inventory(10);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertSame('warning', $box->fresh()->expired_status);
        $this->assertTrue(Box::active()->whereKey($box->id)->exists());
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertOk();
        $this->assertDatabaseHas('delivery_pick_items', ['box_id' => $box->id]);
    }

    public function test_handle_restores_stock_and_fifo_for_one_calendar_month_without_resetting_age(): void
    {
        [$operator, $supervisor, $box, $pallet, $location, $order] = $this->inventory();
        $storedAt = $box->created_at->toDateTimeString();
        $service = app(ExpiredBoxService::class);
        $service->syncStatuses();
        $this->assertFalse(Box::active()->whereKey($box->id)->exists());
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $pallet->id]);
        $this->actingAs($supervisor)->post(route('expired-box.handle', $box->id), ['location_id' => $location->id])
            ->assertRedirect(route('expired-box.index'))->assertSessionHas('success');
        $box->refresh();
        $this->assertSame('active', $box->expired_status);
        $this->assertSame('2026-02-28 10:00:00', $box->reactivated_until->toDateTimeString());
        $this->assertSame($storedAt, $box->created_at->toDateTimeString());
        $service->syncStatuses();
        $this->assertSame('active', $box->fresh()->expired_status);
        $this->assertDatabaseHas('pallet_items', ['pallet_id' => $pallet->id, 'box_quantity' => 1, 'pcs_quantity' => 20]);
        $this->assertDatabaseHas('master_locations', ['id' => $location->id, 'is_occupied' => true, 'current_pallet_id' => $pallet->id]);
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertOk();
        $this->assertDatabaseHas('delivery_pick_items', ['box_id' => $box->id]);
    }

    public function test_deadline_blocks_fifo_even_before_status_sync_then_returns_to_expired(): void
    {
        [$operator, $supervisor, $box, $pallet, , $order] = $this->inventory();
        $service = app(ExpiredBoxService::class);
        $service->handleBox($box->id, $supervisor->id);
        $this->travelTo($box->fresh()->reactivated_until->copy()->subSecond());
        $this->assertTrue(Box::active()->whereKey($box->id)->exists());
        $this->travel(1)->seconds();
        $this->assertSame('active', $box->fresh()->expired_status);
        $this->assertFalse(Box::active()->whereKey($box->id)->exists());
        $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id))->assertStatus(422);
        $this->artisan('expired-box:sync-statuses')->assertSuccessful();
        $this->assertSame('expired', $box->fresh()->expired_status);
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $pallet->id]);
        $this->assertDatabaseHas('pallet_items', ['pallet_id' => $pallet->id, 'box_quantity' => 0, 'pcs_quantity' => 0]);
    }

    public function test_repeated_handle_creates_separate_history_and_duplicate_active_request_does_not_extend(): void
    {
        [, $supervisor, $box] = $this->inventory();
        $service = app(ExpiredBoxService::class);
        $service->handleBox($box->id, $supervisor->id);
        $first = $box->fresh()->reactivated_until;
        $this->travel(1)->days();
        $service->handleBox($box->id, $supervisor->id);
        $this->assertSame($first->toDateTimeString(), $box->fresh()->reactivated_until->toDateTimeString());
        $this->assertSame(1, ExpiredBoxReport::where('status', 'handled')->count());
        $this->travelTo($first->copy()->addDay());
        $service->handleBox($box->id, $supervisor->id);
        $this->assertSame(2, ExpiredBoxReport::where('status', 'handled')->count());
        $this->assertSame($first->toDateTimeString(), ExpiredBoxReport::where('status', 'handled')->oldest('id')->first()->reactivated_until->toDateTimeString());
        $this->assertSame(now()->addMonthNoOverflow()->toDateTimeString(), $box->fresh()->reactivated_until->toDateTimeString());
    }

    public function test_expired_renewal_cannot_be_verified_scanned_or_completed(): void
    {
        [$operator, $supervisor, $box, , , $order] = $this->inventory();
        app(ExpiredBoxService::class)->handleBox($box->id, $supervisor->id);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id, 'status' => 'scanning', 'started_at' => now()]);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $box->id, 'part_number' => 'P-RENEW', 'pcs_quantity' => 20, 'status' => 'scanned']);
        $this->travelTo($box->fresh()->reactivated_until);
        $this->actingAs($operator)->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $box->box_number])->assertStatus(422);
        $this->postJson(route('delivery.pick.complete', $session->id))->assertStatus(409);
        $this->postJson(route('delivery.pick.scan.submit', $session->id), ['box_number' => $box->box_number])->assertStatus(422);
        $this->assertSame('blocked', $session->fresh()->status);
        $this->assertDatabaseHas('delivery_issues', ['pick_session_id' => $session->id, 'issue_type' => 'box_expired']);
        $this->postJson(route('delivery.pick.scan.submit', $session->id), ['box_number' => $box->box_number])->assertStatus(423);
        $this->assertFalse($box->fresh()->is_withdrawn);
        $this->assertDatabaseCount('stock_withdrawals', 0);
    }

    public function test_occupied_old_location_is_not_overwritten_and_another_free_location_can_be_chosen(): void
    {
        [, $supervisor, $box, $pallet, $location] = $this->inventory();
        $service = app(ExpiredBoxService::class);
        $service->syncStatuses();
        $other = Pallet::create(['pallet_number' => 'OTHER']);
        $location->update(['is_occupied' => true, 'current_pallet_id' => $other->id]);
        $this->actingAs($supervisor)->post(route('expired-box.handle', $box->id))->assertSessionHas('error');
        $this->assertSame('expired', $box->fresh()->expired_status);
        $this->assertNull($box->fresh()->reactivated_until);
        $this->assertSame($other->id, $location->fresh()->current_pallet_id);
        $free = MasterLocation::create(['code' => 'RENEW-02', 'is_occupied' => false]);
        $this->post(route('expired-box.handle', $box->id), ['location_id' => $free->id])->assertSessionHas('success');
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $pallet->id, 'master_location_id' => $free->id]);
    }

    public function test_handle_history_view_shows_expiry_and_warehouse_operator_cannot_activate(): void
    {
        [$operator, $supervisor, $box] = $this->inventory();
        $this->actingAs($operator)->post(route('expired-box.handle', $box->id))->assertForbidden();
        app(ExpiredBoxService::class)->handleBox($box->id, $supervisor->id);
        $this->actingAs($supervisor)->get(route('expired-box.index'))->assertOk()
            ->assertSee('Aktif Sampai')->assertSee('28 Feb 2026 10:00');
    }
}
