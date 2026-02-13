<?php

namespace Tests\Unit;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pallet;
use App\Models\StockInput;
use App\Models\StockWithdrawal;
use App\Models\User;
use App\Services\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OperationalReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_calculates_fulfillment_rate_from_orders(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $orderFull = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Full',
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderFull->id,
            'part_number' => 'P-OR-1',
            'quantity' => 100,
            'fulfilled_quantity' => 100,
        ]);

        $orderPartial = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Partial',
            'delivery_date' => now()->toDateString(),
            'status' => 'processing',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderPartial->id,
            'part_number' => 'P-OR-2',
            'quantity' => 100,
            'fulfilled_quantity' => 40,
        ]);

        $service = app(OperationalReportService::class);
        $data = $service->build(new Request(['period' => 'all']));

        $this->assertSame(2, $data['fulfillmentRows']->count());
        $this->assertSame(50.0, (float) $data['fulfillmentRate']);
    }

    public function test_build_groups_delivery_trend_by_week_bucket(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $day1 = now()->subDays(2)->toDateString();
        $day2 = now()->subDay()->toDateString();

        $orderA = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Trend A',
            'delivery_date' => $day1,
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderA->id,
            'part_number' => 'P-TREND',
            'quantity' => 80,
            'fulfilled_quantity' => 70,
        ]);

        $orderB = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Trend B',
            'delivery_date' => $day2,
            'status' => 'completed',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderB->id,
            'part_number' => 'P-TREND',
            'quantity' => 20,
            'fulfilled_quantity' => 20,
        ]);

        $service = app(OperationalReportService::class);
        $data = $service->build(new Request([
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->toDateString(),
            'group_by' => 'week',
        ]));

        $this->assertCount(1, $data['deliveryTrend']);
        $this->assertSame(100, (int) $data['deliveryTrend'][0]['planned_qty']);
        $this->assertSame(90, (int) $data['deliveryTrend'][0]['actual_qty']);
    }

    public function test_build_generates_peak_hours_with_sqlite_compatible_hour_extraction(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-SRV-01']);

        StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'S1',
            'pcs_quantity' => 120,
            'box_quantity' => 1,
            'stored_at' => now()->setTime(8, 15),
            'part_numbers' => ['P-HOUR-1'],
        ]);

        StockWithdrawal::create([
            'user_id' => $operator->id,
            'part_number' => 'P-HOUR-1',
            'pcs_quantity' => 80,
            'box_quantity' => 1,
            'warehouse_location' => 'S1',
            'status' => 'completed',
            'withdrawn_at' => now()->setTime(14, 0),
        ]);

        $service = app(OperationalReportService::class);
        $data = $service->build(new Request([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'group_by' => 'day',
        ]));

        $peak = collect($data['peakHours']);
        $hour8 = $peak->firstWhere('hour', '08:00');
        $hour14 = $peak->firstWhere('hour', '14:00');

        $this->assertNotNull($hour8);
        $this->assertNotNull($hour14);
        $this->assertSame(120, (int) ($hour8['inbound_pcs'] ?? 0));
        $this->assertSame(80, (int) ($hour14['outbound_pcs'] ?? 0));
    }
}
