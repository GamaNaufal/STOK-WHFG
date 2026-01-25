<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_retrieve_order()
    {
        // One issue: Unit tests in Laravel default to PHPUnit\Framework\TestCase which doesn't boot Laravel app.
        // We need Tests\TestCase for DB access.
        
        // Create a user
        $user = User::factory()->create([
            'role' => 'sales',
            'name' => 'Sales User'
        ]);

        // Create Order direct via Model (like Controller does)
        $order = DeliveryOrder::create([
            'sales_user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'delivery_date' => now()->addDays(3),
            'status' => 'pending'
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'PART-123',
            'quantity' => 100
        ]);

        // Retrieve
        $retrieved = DeliveryOrder::where('sales_user_id', $user->id)->get();

        $this->assertCount(1, $retrieved);
        $this->assertEquals('Test Customer', $retrieved->first()->customer_name);
        $this->assertEquals($user->id, $retrieved->first()->sales_user_id);
    }
}
