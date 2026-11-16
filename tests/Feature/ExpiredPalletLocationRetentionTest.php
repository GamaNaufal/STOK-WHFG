<?php

namespace Tests\Feature;

use App\Models\{Box, DeliveryOrder, DeliveryOrderItem, DeliveryPickItem, DeliveryPickSession, MasterLocation, Pallet, PalletItem, PartSetting, StockInput, StockLocation, User};
use App\Services\{ExpiredBoxHandlingService, ExpiredBoxService, LocationAssignmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpiredPalletLocationRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(int $expiredCount = 2, bool $withActive = false): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        PartSetting::create(['part_number' => 'EXP', 'qty_box' => 200]);
        PartSetting::create(['part_number' => 'ACTIVE', 'qty_box' => 100]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-508']);
        $location = MasterLocation::create(['code' => 'A23', 'is_occupied' => true, 'current_pallet_id' => $pallet->id]);
        StockLocation::create(['pallet_id' => $pallet->id, 'master_location_id' => $location->id, 'warehouse_location' => 'A23', 'stored_at' => now()->subMonths(14)]);
        $expired = collect();
        for ($i = 1; $i <= $expiredCount; $i++) {
            $expired->push($this->box($pallet, $user, true));
        }
        $active = $withActive ? $this->box($pallet, $user, false) : null;
        return compact('user', 'pallet', 'location', 'expired', 'active');
    }

    private function box(Pallet $pallet, User $user, bool $expired): Box
    {
        $box = Box::create(['box_number' => sprintf('%08d', Box::withTrashed()->count() + 1),
            'part_number' => $expired ? 'EXP' : 'ACTIVE', 'pcs_quantity' => $expired ? 200 : 100,
            'qty_box' => $expired ? 200 : 100, 'qr_code' => 'test', 'user_id' => $user->id,
            'expired_status' => 'active', 'created_at' => $expired ? now()->subMonths(14) : now()]);
        $pallet->boxes()->attach($box->id);
        $item = PalletItem::firstOrCreate(['pallet_id' => $pallet->id, 'part_number' => $box->part_number], ['box_quantity' => 0, 'pcs_quantity' => 0]);
        $item->increment('box_quantity');
        $item->increment('pcs_quantity', $box->pcs_quantity);
        return $box;
    }

    private function assertRetained(array $f): void
    {
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $f['pallet']->id, 'master_location_id' => $f['location']->id, 'warehouse_location' => 'A23']);
        $this->assertTrue($f['location']->fresh()->is_occupied);
        $this->assertSame($f['pallet']->id, $f['location']->fresh()->current_pallet_id);
        $this->assertFalse($f['location']->fresh()->isPalletEmpty());
        $this->assertFalse(MasterLocation::available()->whereKey($f['location']->id)->exists());
        $this->assertFalse($f['pallet']->fresh()->trashed());
    }

    public function test_all_expired_boxes_keep_location_and_cannot_be_picked(): void
    {
        $f = $this->inventory();
        $service = app(ExpiredBoxService::class);
        $service->syncStatuses();
        $service->syncStatuses();
        $f['location']->fresh()->autoVacateIfEmpty();
        $this->assertRetained($f);
        $this->assertSame(0, $f['pallet']->activeBoxes()->count());
        $this->assertSame(2, $f['pallet']->storedBoxes()->count());
        $this->assertSame(0, $f['pallet']->items()->sum('pcs_quantity'));
        $this->assertSame(['A23', 'A23'], $service->getExpirableBoxesQuery()->orderBy('boxes.id')->pluck('warehouse_location')->all());
        $this->get(route('expired-box.index'))->assertOk()->assertSee('A23');
        $this->getJson('/api/stock/by-part')->assertOk()->assertExactJson([]);
    }

    public function test_elapsed_reactivation_keeps_location_before_and_after_status_sync(): void
    {
        $f = $this->inventory(0, true);
        $f['active']->update(['reactivated_until' => now()->subSecond()]);
        $f['location']->fresh()->autoVacateIfEmpty();
        $this->assertRetained($f);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertSame('expired', $f['active']->fresh()->expired_status);
        $this->assertRetained($f);
    }

    public function test_qa_releases_location_only_after_last_expired_box_leaves(): void
    {
        $f = $this->inventory();
        $service = app(ExpiredBoxHandlingService::class);
        $first = $service->start($f['expired'][0]->id, $f['user']->id);
        $this->assertRetained($f);
        $this->assertSame(0, $f['expired'][0]->pallets()->count());
        $this->assertSame('A23', $first->source_snapshot['pallets'][0]['stock_location']['warehouse_location']);
        $service->start($f['expired'][1]->id, $f['user']->id);
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $f['pallet']->id]);
        $this->assertFalse($f['location']->fresh()->is_occupied);
        $this->assertNull($f['location']->fresh()->current_pallet_id);
    }

    public function test_location_assignment_cannot_reclaim_expired_pallet_even_with_stale_free_flag(): void
    {
        $f = $this->inventory();
        app(ExpiredBoxService::class)->syncStatuses();
        $f['location']->update(['is_occupied' => false, 'current_pallet_id' => null]);
        $new = Pallet::create(['pallet_number' => 'NEW']);
        try {
            DB::transaction(fn () => app(LocationAssignmentService::class)->assign($f['location']->id, $new));
            $this->fail('Expired stock must block claiming its location.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sudah terisi', $exception->getMessage());
        }
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $f['pallet']->id, 'master_location_id' => $f['location']->id]);
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $new->id]);
    }

    public function test_deleting_last_active_box_preserves_expired_stock_until_last_physical_deletion(): void
    {
        $f = $this->inventory(1, true);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->deleteJson(route('stock-view.box-delete', $f['active']->id))->assertOk();
        $this->assertRetained($f);
        $this->deleteJson(route('stock-view.box-delete', $f['expired'][0]->id))->assertOk();
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $f['pallet']->id]);
        $this->assertFalse($f['location']->fresh()->is_occupied);
        $this->assertSoftDeleted($f['pallet']);
    }

    public function test_direct_withdrawal_of_last_active_stock_preserves_expired_pallet(): void
    {
        $f = $this->inventory(1, true);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->postJson(route('stock-withdrawal.confirm'), ['part_number' => 'ACTIVE', 'pcs_quantity' => 100])->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($f['active']->fresh()->is_withdrawn);
        $this->assertRetained($f);
    }

    public function test_delivery_and_redo_keep_expired_stock_at_original_location(): void
    {
        $f = $this->inventory(1, true);
        app(ExpiredBoxService::class)->syncStatuses();
        $order = DeliveryOrder::create(['sales_user_id' => $f['user']->id, 'customer_name' => 'Retain expired', 'delivery_date' => now()->addDay(), 'status' => 'processing']);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => 'ACTIVE', 'quantity' => 100, 'fulfilled_quantity' => 0]);
        $f['active']->update(['assigned_delivery_order_id' => $order->id]);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $f['user']->id, 'status' => 'scanning', 'started_at' => now()]);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $f['active']->id, 'part_number' => 'ACTIVE', 'pcs_quantity' => 100, 'status' => 'scanned']);
        $this->postJson(route('delivery.pick.complete', $session))->assertOk()->assertJson(['success' => true]);
        $this->assertRetained($f);
        // The former location is still physically occupied by expired stock.
        $this->post(route('delivery.pick.redo', $session))->assertSessionHas('error');
        $this->assertRetained($f);
        $this->assertSame('completed', $session->fresh()->completion_status);
        $destination = MasterLocation::create(['code' => 'B02']);
        $this->post(route('delivery.pick.redo', $session), ['relocation_location' => 'B02'])->assertSessionHas('success');
        $this->assertRetained($f);
        $this->assertSame('B02', $f['active']->fresh()->pallets()->sole()->stockLocation->warehouse_location);
        $this->assertSame($f['pallet']->id, $f['expired'][0]->pallets()->sole()->id);
        $this->assertTrue($destination->fresh()->is_occupied);
    }

    public static function mergeTargets(): array
    {
        return ['free destination' => [false], 'source still holding expired stock' => [true]];
    }

    #[DataProvider('mergeTargets')]
    public function test_merge_does_not_discard_expired_stock_location(bool $useOccupiedSource): void
    {
        $f = $this->inventory(1, true);
        app(ExpiredBoxService::class)->syncStatuses();
        $input = StockInput::create(['pallet_id' => $f['pallet']->id, 'user_id' => $f['user']->id, 'warehouse_location' => 'A23', 'pcs_quantity' => 300, 'box_quantity' => 2, 'stored_at' => now()->subMonths(14)]);
        $other = Pallet::create(['pallet_number' => 'SECOND']);
        $this->box($other, $f['user'], false);
        $target = $useOccupiedSource ? $f['location'] : MasterLocation::create(['code' => 'B02']);
        $response = $this->postJson(route('merge-pallet.store'), ['pallet_ids' => [$f['pallet']->id, $other->id], 'location_id' => $target->id]);
        $response->assertJson(['success' => ! $useOccupiedSource]);
        $this->assertRetained($f);
        $this->assertSame($f['pallet']->id, $input->fresh()->pallet_id);
        $this->assertSame($f['pallet']->id, $f['expired'][0]->pallets()->sole()->id);
        if (! $useOccupiedSource) {
            $this->assertSame('B02', $f['active']->pallets()->sole()->stockLocation->warehouse_location);
        } else {
            $this->assertSame($f['pallet']->id, $f['active']->pallets()->sole()->id);
        }
    }
}
