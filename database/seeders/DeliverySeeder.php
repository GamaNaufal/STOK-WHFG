<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliverySeeder extends Seeder
{
    /**
     * Seed sample delivery orders and items.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $salesUser = User::where('email', 'siti@sales.local')->first()
                ?? User::where('role', 'sales')->first()
                ?? User::first();

            if (!$salesUser) {
                return;
            }

            $orderParent = DeliveryOrder::updateOrCreate(
                ['notes' => '[SEED] DO-20260517-001'],
                [
                    'sales_user_id' => $salesUser->id,
                    'customer_name' => 'PT Sakura Metal Indonesia',
                    'delivery_date' => now()->addDay()->toDateString(),
                    'status' => 'approved',
                ]
            );

            $orderFollowUp = DeliveryOrder::updateOrCreate(
                ['notes' => '[SEED] DO-20260517-002'],
                [
                    'sales_user_id' => $salesUser->id,
                    'customer_name' => 'PT Sakura Metal Indonesia',
                    'delivery_date' => now()->addDays(2)->toDateString(),
                    'status' => 'approved',
                ]
            );

            $orderRegular = DeliveryOrder::updateOrCreate(
                ['notes' => '[SEED] DO-20260517-003'],
                [
                    'sales_user_id' => $salesUser->id,
                    'customer_name' => 'PT Fuji Components',
                    'delivery_date' => now()->addDays(3)->toDateString(),
                    'status' => 'pending',
                ]
            );

            $items = [
                [$orderParent->id, 'PN-1001', 3, 2],
                [$orderParent->id, 'PN-1002', 2, 1],
                [$orderFollowUp->id, 'PN-1001', 1, 0],
                [$orderFollowUp->id, 'PN-1002', 1, 0],
                [$orderRegular->id, 'PN-1003', 2, 0],
            ];

            foreach ($items as [$orderId, $partNumber, $qty, $fulfilled]) {
                DeliveryOrderItem::updateOrCreate(
                    ['delivery_order_id' => $orderId, 'part_number' => $partNumber],
                    ['quantity' => $qty, 'fulfilled_quantity' => $fulfilled]
                );
            }

            // Assign a few active boxes to approved follow-up order for pickup simulation.
            $boxIds = Box::where('assigned_delivery_order_id', $orderFollowUp->id)
                ->pluck('id')
                ->all();

            if (count($boxIds) === 0) {
                Box::whereNull('assigned_delivery_order_id')
                    ->where('is_withdrawn', false)
                    ->whereNotIn('expired_status', ['handled', 'expired'])
                    ->whereIn('part_number', ['PN-1001', 'PN-1002'])
                    ->limit(2)
                    ->update(['assigned_delivery_order_id' => $orderFollowUp->id]);
            }
        });
    }
}
