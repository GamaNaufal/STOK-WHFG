<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\PartSetting;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_part_and_alias_leave_master_lookup_and_a_prescanned_receipt_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $part = PartSetting::create(['part_number' => 'RETIRED-PART', 'qty_box' => 10]);
        $part->documentNumbers()->create(['document_number' => 'RETIRED-DOC']);
        $location = MasterLocation::create(['code' => 'RETIRE-LOCAL']);

        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '94019001'])->assertOk();
        $this->postJson(route('stock-input.scan-part'), ['part_number' => $part->part_number])->assertOk();
        $palletId = session('current_pallet_id');

        $this->delete(route('part-settings.destroy', $part))->assertSessionHas('success');
        $this->getJson(route('part-settings.search', ['q' => $part->part_number]))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('part-settings.search', ['q' => 'RETIRED-DOC']))->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('part_document_numbers', ['part_setting_id' => $part->id, 'document_number' => 'RETIRED-DOC']);
        $this->postJson(route('stock-input.store'), ['pallet_id' => $palletId, 'location_id' => $location->id])
            ->assertUnprocessable();
        $this->assertDatabaseCount('boxes', 0);
        $this->assertDatabaseCount('stock_inputs', 0);
    }

    public function test_completed_local_shipment_blocks_only_while_redo_remains_available(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $this->actingAs($actor);
        $part = PartSetting::create(['part_number' => 'RETIRED-REDO', 'qty_box' => 10]);
        $order = DeliveryOrder::create(['sales_user_id' => $actor->id, 'customer_name' => 'Customer',
            'delivery_date' => now(), 'status' => 'completed']);
        $order->items()->create(['part_number' => $part->part_number, 'quantity' => 10]);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $actor->id,
            'status' => 'completed', 'completion_status' => 'completed', 'redo_until' => now()->addDay()]);
        $withdrawal = StockWithdrawal::create(['pick_session_id' => $session->id, 'user_id' => $actor->id,
            'part_number' => $part->part_number, 'pcs_quantity' => 10, 'box_quantity' => 1,
            'warehouse_location' => 'RETIRE-LOCAL', 'status' => 'completed']);

        $this->delete(route('part-settings.destroy', $part))
            ->assertSessionHas('error', 'No Part belum dapat dihapus karena masih ada pengiriman Lokal yang dapat di-Redo.');
        $this->assertNotSoftDeleted($part);
        $this->travel(2)->days();
        $this->delete(route('part-settings.destroy', $part))->assertSessionHas('success');
        $this->assertSoftDeleted($part);
        $this->assertDatabaseHas('stock_withdrawals', ['id' => $withdrawal->id, 'part_number' => $part->part_number, 'pcs_quantity' => 10]);
    }
}
