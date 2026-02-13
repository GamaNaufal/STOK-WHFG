<?php

namespace Tests\Unit;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_and_computed_totals_work_correctly(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $pending = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Pending',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $approved = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Approved',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        $completed = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Completed',
            'delivery_date' => now()->addDays(3)->toDateString(),
            'status' => 'completed',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $approved->id,
            'part_number' => 'P-001',
            'quantity' => 100,
            'fulfilled_quantity' => 60,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $approved->id,
            'part_number' => 'P-002',
            'quantity' => 50,
            'fulfilled_quantity' => 50,
        ]);

        $this->assertSame(1, DeliveryOrder::pending()->count());
        $this->assertSame((int) $pending->id, (int) DeliveryOrder::pending()->first()->id);

        $this->assertSame(1, DeliveryOrder::approved()->count());
        $this->assertSame((int) $approved->id, (int) DeliveryOrder::approved()->first()->id);

        $this->assertSame(1, DeliveryOrder::completed()->count());
        $this->assertSame((int) $completed->id, (int) DeliveryOrder::completed()->first()->id);

        $approved->refresh();
        $this->assertSame(150, (int) $approved->total_quantity);
        $this->assertSame(110, (int) $approved->total_fulfilled);
    }

    public function test_is_ready_for_pickup_only_when_approved(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $approved = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Ready',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        $pending = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Not Ready',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertTrue($approved->isReadyForPickup());
        $this->assertFalse($pending->isReadyForPickup());
    }
}
