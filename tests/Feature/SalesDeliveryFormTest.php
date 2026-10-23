<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDeliveryFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_restores_multiple_items_after_validation_error(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'FORM-A', 'qty_box' => 100]);
        PartSetting::create(['part_number' => 'FORM-B', 'qty_box' => 50]);

        $this->actingAs($sales)->withSession(['_old_input' => [
            'customer_name' => 'PT Kembali',
            'delivery_date' => '2026-10-25',
            'items' => [
                ['part_number' => 'FORM-A', 'quantity' => 40],
                ['part_number' => 'FORM-B', 'quantity' => 70],
            ],
        ]])->get(route('delivery.create'))
            ->assertOk()
            ->assertSee('value="PT Kembali"', false)
            ->assertSee('value="2026-10-25"', false)
            ->assertSee('name="items[1][part_number]"', false)
            ->assertSee('value="70"', false);
    }

    public function test_edit_form_shows_ppc_reason_and_existing_order_items(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'FORM-C', 'qty_box' => 25]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'PT Koreksi',
            'delivery_date' => '2026-10-27',
            'status' => 'correction',
            'notes' => 'Jumlah FORM-C perlu diperiksa ulang.',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'FORM-C',
            'quantity' => 125,
        ]);

        $this->actingAs($sales)->get(route('delivery.edit', $order->id))
            ->assertOk()
            ->assertSee('Jumlah FORM-C perlu diperiksa ulang.')
            ->assertSee('value="FORM-C" selected', false)
            ->assertSee('value="125"', false)
            ->assertSee('Kirim ulang koreksi');
    }

    public function test_create_form_keeps_sparse_item_keys_after_validation_error(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'FORM-D', 'qty_box' => 25]);

        $this->actingAs($sales)->withSession(['_old_input' => [
            'items' => [3 => ['part_number' => 'FORM-D', 'quantity' => 12]],
        ]])->get(route('delivery.create'))
            ->assertOk()
            ->assertSee('name="items[3][part_number]"', false)
            ->assertSee('data-next-index="4"', false);
    }

    public function test_sales_history_keeps_correction_action_and_item_details_available(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'PT Riwayat',
            'delivery_date' => '2026-10-27',
            'status' => 'correction',
            'notes' => 'Ubah jumlah pesanan.',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'FORM-E',
            'quantity' => 125,
        ]);

        $this->actingAs($sales)->get(route('delivery.create'))
            ->assertOk()
            ->assertSee('Order #'.$order->id)
            ->assertSee('PT Riwayat')
            ->assertSee('Perlu koreksi')
            ->assertSee('Ubah jumlah pesanan.')
            ->assertSee('data-bs-target="#order-detail-'.$order->id.'"', false)
            ->assertSee('FORM-E')
            ->assertSee('125 PCS')
            ->assertSee(route('delivery.edit', $order->id), false);
    }
}
