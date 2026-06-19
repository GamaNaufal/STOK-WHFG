<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockTruthRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_rebuilds_summary_from_unique_active_boxes_instead_of_stale_pallet_items(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $palletA = Pallet::create(['pallet_number' => 'MERGE-TRUTH-A']);
        $palletB = Pallet::create(['pallet_number' => 'MERGE-TRUTH-B']);

        foreach ([[$palletA, 'MT-A'], [$palletB, 'MT-B']] as [$pallet, $code]) {
            MasterLocation::create([
                'code' => $code,
                'is_occupied' => false,
            ]);
            StockLocation::create([
                'pallet_id' => $pallet->id,
                'warehouse_location' => $code,
                'stored_at' => now(),
            ]);
        }

        $target = MasterLocation::create([
            'code' => 'MT-TARGET',
            'is_occupied' => false,
        ]);

        $boxA = $this->createBox($operator, '93000001', 'P-MERGE-TRUTH', 10);
        $boxB = $this->createBox($operator, '93000002', 'P-MERGE-TRUTH', 20);
        $palletA->boxes()->attach($boxA->id);
        $palletB->boxes()->attach($boxB->id);

        PalletItem::create([
            'pallet_id' => $palletA->id,
            'part_number' => 'P-MERGE-TRUTH',
            'box_quantity' => 9,
            'pcs_quantity' => 999,
        ]);
        PalletItem::create([
            'pallet_id' => $palletB->id,
            'part_number' => 'P-MERGE-TRUTH',
            'box_quantity' => 1,
            'pcs_quantity' => 20,
        ]);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$palletA->id, $palletB->id],
            'warehouse_location' => $target->code,
            'location_id' => $target->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $newPallet = Pallet::where('pallet_number', $response->json('new_pallet_number'))->firstOrFail();

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $newPallet->id,
            'part_number' => 'P-MERGE-TRUTH',
            'box_quantity' => 2,
            'pcs_quantity' => 30,
        ]);
    }

    public function test_start_pick_does_not_include_reserved_boxes_beyond_remaining_order_quantity(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-RESERVED-EXACT', 60);

        $pallet = Pallet::create(['pallet_number' => 'PLT-RESERVED-EXACT']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'RES-1',
            'stored_at' => now(),
        ]);

        foreach (['93000003', '93000004'] as $boxNumber) {
            $box = $this->createBox($operator, $boxNumber, 'P-RESERVED-EXACT', 60, [
                'assigned_delivery_order_id' => $order->id,
            ]);
            $pallet->boxes()->attach($box->id);
        }

        $response = $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id));
        $response->assertOk();

        $sessionId = (int) $response->json('session_id');
        $this->assertSame(
            1,
            DeliveryPickItem::where('pick_session_id', $sessionId)->count()
        );
        $this->assertSame(
            60,
            (int) DeliveryPickItem::where('pick_session_id', $sessionId)->sum('pcs_quantity')
        );
    }

    public function test_complete_rejects_when_box_snapshot_has_changed(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-SNAPSHOT', 10, 'processing');
        [$pallet, $box] = $this->createStoredBox($operator, '93000005', 'P-SNAPSHOT', 10, 'SNAP-1');

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'part_number' => 'P-SNAPSHOT',
            'pcs_quantity' => 10,
            'status' => 'scanned',
            'scanned_at' => now(),
            'scanned_by' => $operator->id,
        ]);

        $box->update(['pcs_quantity' => 12]);

        $response = $this->actingAs($operator)->postJson(route('delivery.pick.complete', $session->id));
        $response->assertStatus(409)->assertJson(['success' => false]);

        $this->assertFalse($box->fresh()->is_withdrawn);
        $this->assertDatabaseMissing('stock_withdrawals', ['box_id' => $box->id]);
        $this->assertDatabaseHas('pallets', ['id' => $pallet->id]);
    }

    public function test_shared_box_withdrawal_uses_latest_canonical_pallet_and_clears_all_summaries(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-SHARED-CANONICAL', 10, 'processing');

        $palletA = Pallet::create(['pallet_number' => 'PLT-SHARED-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-SHARED-B']);
        StockLocation::create(['pallet_id' => $palletA->id, 'warehouse_location' => 'SH-A', 'stored_at' => now()]);
        StockLocation::create(['pallet_id' => $palletB->id, 'warehouse_location' => 'SH-B', 'stored_at' => now()]);

        $box = $this->createBox($operator, '93000006', 'P-SHARED-CANONICAL', 10);
        $palletA->boxes()->attach($box->id);
        $palletB->boxes()->attach($box->id);

        foreach ([$palletA, $palletB] as $pallet) {
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => 'P-SHARED-CANONICAL',
                'box_quantity' => 1,
                'pcs_quantity' => 10,
            ]);
        }

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'part_number' => 'P-SHARED-CANONICAL',
            'pcs_quantity' => 10,
            'status' => 'scanned',
            'scanned_at' => now(),
            'scanned_by' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->postJson(route('delivery.pick.complete', $session->id))
            ->assertOk();

        $this->assertDatabaseHas('stock_withdrawals', [
            'box_id' => $box->id,
            'warehouse_location' => 'SH-B',
            'status' => 'completed',
        ]);

        foreach ([$palletA, $palletB] as $pallet) {
            $this->assertDatabaseHas('pallet_items', [
                'pallet_id' => $pallet->id,
                'part_number' => 'P-SHARED-CANONICAL',
                'box_quantity' => 0,
                'pcs_quantity' => 0,
            ]);
            $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $pallet->id]);
        }
    }

    public function test_box_correction_updates_qr_inbound_header_and_expiry_date(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        PartSetting::create(['part_number' => 'P-CORRECTED', 'qty_box' => 100]);
        [$pallet, $box] = $this->createStoredBox($adminWarehouse, '93000007', 'P-ORIGINAL', 100, 'COR-1');

        $palletItem = PalletItem::where('pallet_id', $pallet->id)->firstOrFail();
        $input = StockInput::create([
            'pallet_id' => $pallet->id,
            'pallet_item_id' => $palletItem->id,
            'user_id' => $adminWarehouse->id,
            'warehouse_location' => 'COR-1',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => ['P-ORIGINAL'],
        ]);
        DB::table('stock_input_boxes')->insert([
            'stock_input_id' => $input->id,
            'box_id' => $box->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storedAt = now()->subMonths(13)->startOfDay();
        $this->actingAs($adminWarehouse)
            ->postJson(route('stock-view.box-update', $box->id), [
                'part_number' => 'P-CORRECTED',
                'pcs_quantity' => 80,
                'stored_at' => $storedAt->format('Y-m-d H:i:s'),
                'reason' => 'Koreksi stok aktual',
            ])
            ->assertOk();

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'part_number' => 'P-CORRECTED',
            'pcs_quantity' => 80,
            'qr_code' => '93000007|P-CORRECTED|80',
        ]);

        $input->refresh();
        $this->assertSame(80, (int) $input->pcs_quantity);
        $this->assertSame(['P-CORRECTED'], $input->part_numbers);
        $this->assertSame($storedAt->toDateString(), $input->stored_at->toDateString());

        app(ExpiredBoxService::class)->syncStatuses();
        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'expired_status' => 'expired',
        ]);
    }

    public function test_withdrawn_box_id_cannot_be_scanned_back_into_stock(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-RESCAN-GUARD']);
        $box = $this->createBox($operator, '93000008', 'P-RESCAN', 10, [
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
        ]);

        $this->actingAs($operator)
            ->withSession(['current_pallet_id' => $pallet->id])
            ->postJson(route('stock-input.scan-barcode'), ['barcode' => $box->box_number])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    private function createOrder(User $sales, string $partNumber, int $quantity, string $status = 'approved'): DeliveryOrder
    {
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Stock Truth Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => $status,
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => $partNumber,
            'quantity' => $quantity,
            'fulfilled_quantity' => 0,
        ]);

        return $order;
    }

    private function createStoredBox(
        User $user,
        string $boxNumber,
        string $partNumber,
        int $pcs,
        string $location
    ): array {
        $pallet = Pallet::create(['pallet_number' => 'PLT-' . $boxNumber]);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => $location,
            'stored_at' => now(),
        ]);
        $box = $this->createBox($user, $boxNumber, $partNumber, $pcs);
        $pallet->boxes()->attach($box->id);
        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => $partNumber,
            'box_quantity' => 1,
            'pcs_quantity' => $pcs,
        ]);

        return [$pallet, $box];
    }

    private function createBox(
        User $user,
        string $boxNumber,
        string $partNumber,
        int $pcs,
        array $extra = []
    ): Box {
        return Box::create(array_merge([
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'pcs_quantity' => $pcs,
            'qty_box' => $pcs,
            'qr_code' => "{$boxNumber}|{$partNumber}|{$pcs}",
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ], $extra));
    }
}
