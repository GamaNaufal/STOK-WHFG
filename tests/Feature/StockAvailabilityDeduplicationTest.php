<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAvailabilityDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_schedule_counts_shared_box_once(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Dedup Schedule',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-DEDUP-SCHEDULE',
            'quantity' => 40,
            'fulfilled_quantity' => 0,
        ]);

        $sharedBox = Box::create([
            'box_number' => '960101',
            'part_number' => 'P-DEDUP-SCHEDULE',
            'pcs_quantity' => 50,
            'qty_box' => 1,
            'qr_code' => '960101|P-DEDUP-SCHEDULE|50',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $palletA = Pallet::create(['pallet_number' => 'PLT-DEDUP-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-DEDUP-B']);

        StockLocation::create([
            'pallet_id' => $palletA->id,
            'warehouse_location' => 'D1',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'warehouse_location' => 'D2',
            'stored_at' => now(),
        ]);

        $palletA->boxes()->attach($sharedBox->id);
        $palletB->boxes()->attach($sharedBox->id);

        $response = $this->actingAs($operator)->get(route('delivery.index'));
        $response->assertOk();

        $approvedOrders = $response->viewData('approvedOrders');
        $viewOrder = $approvedOrders->firstWhere('id', $order->id);
        $this->assertNotNull($viewOrder);

        $item = $viewOrder->items->firstWhere('part_number', 'P-DEDUP-SCHEDULE');
        $this->assertNotNull($item);
        $this->assertSame(50, (int) $item->available_total);
    }

    public function test_withdrawal_preview_counts_shared_box_once(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $sharedBox = Box::create([
            'box_number' => '960201',
            'part_number' => 'P-DEDUP-PREVIEW',
            'pcs_quantity' => 30,
            'qty_box' => 1,
            'qr_code' => '960201|P-DEDUP-PREVIEW|30',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        \App\Models\PartSetting::create([
            'part_number' => 'P-DEDUP-PREVIEW',
            'qty_box' => 100,
        ]);

        $palletA = Pallet::create(['pallet_number' => 'PLT-DEDUP-PREV-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-DEDUP-PREV-B']);

        StockLocation::create([
            'pallet_id' => $palletA->id,
            'warehouse_location' => 'D3',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'warehouse_location' => 'D4',
            'stored_at' => now(),
        ]);

        $palletA->boxes()->attach($sharedBox->id);
        $palletB->boxes()->attach($sharedBox->id);

        $response = $this->actingAs($operator)->postJson(route('stock-withdrawal.preview'), [
            'part_number' => 'P-DEDUP-PREVIEW',
            'pcs_quantity' => 30,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(30, (int) $response->json('total_available'));
        $this->assertCount(1, $response->json('locations', []));
    }

    public function test_withdrawal_preview_rejects_when_exact_quantity_cannot_be_formed(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $olderBox = Box::create([
            'box_number' => '960301',
            'part_number' => 'P-STRICT-PREVIEW',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => '960301|P-STRICT-PREVIEW|100',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $newerBox = Box::create([
            'box_number' => '960302',
            'part_number' => 'P-STRICT-PREVIEW',
            'pcs_quantity' => 60,
            'qty_box' => 1,
            'qr_code' => '960302|P-STRICT-PREVIEW|60',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        \App\Models\PartSetting::create([
            'part_number' => 'P-STRICT-PREVIEW',
            'qty_box' => 100,
        ]);

        $palletA = Pallet::create(['pallet_number' => 'PLT-STRICT-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-STRICT-B']);

        StockLocation::create([
            'pallet_id' => $palletA->id,
            'warehouse_location' => 'D5',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'warehouse_location' => 'D6',
            'stored_at' => now(),
        ]);

        $palletA->boxes()->attach($olderBox->id);
        $palletB->boxes()->attach($newerBox->id);

        $response = $this->actingAs($operator)->postJson(route('stock-withdrawal.preview'), [
            'part_number' => 'P-STRICT-PREVIEW',
            'pcs_quantity' => 70,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('kombinasi box', (string) $response->json('message'));
    }
}
