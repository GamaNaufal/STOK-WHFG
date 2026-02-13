<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\MasterLocation;
use App\Models\NotFullBoxRequest;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInputAndNotFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_full_approval_with_target_location_creates_new_pallet_and_occupies_location(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);

        PartSetting::create([
            'part_number' => 'P-NF-LOC',
            'qty_box' => 100,
        ]);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust NF LOC',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        MasterLocation::create([
            'code' => 'LOC-NEW-1',
            'is_occupied' => false,
        ]);

        $requestCreate = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => 'BOX-NF-LOC-01',
            'part_number' => 'P-NF-LOC',
            'pcs_quantity' => 75,
            'delivery_order_id' => $order->id,
            'reason' => 'Urgent topup',
            'request_type' => 'supplement',
            'target_type' => 'location',
            'target_location_id' => MasterLocation::where('code', 'LOC-NEW-1')->value('id'),
        ]);

        $requestCreate->assertRedirect();
        $requestCreate->assertSessionHas('success');

        $requestId = (int) NotFullBoxRequest::query()->value('id');

        $approve = $this->actingAs($supervisi)->post(route('box-not-full.approve', $requestId));
        $approve->assertRedirect();
        $approve->assertSessionHas('success');

        $approvedRequest = NotFullBoxRequest::findOrFail($requestId);

        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $requestId,
            'status' => 'approved',
            'target_location_code' => 'LOC-NEW-1',
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $approvedRequest->box_id,
            'box_number' => 'BOX-NF-LOC-01',
            'is_not_full' => 1,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $newPalletId = (int) MasterLocation::where('code', 'LOC-NEW-1')->value('current_pallet_id');
        $this->assertGreaterThan(0, $newPalletId);

        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $newPalletId,
            'warehouse_location' => 'LOC-NEW-1',
        ]);

        $this->assertDatabaseHas('pallet_boxes', [
            'pallet_id' => $newPalletId,
            'box_id' => $approvedRequest->box_id,
        ]);
    }

    public function test_reject_pending_not_full_request_has_no_stock_side_effect(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);

        PartSetting::create([
            'part_number' => 'P-NF-REJ',
            'qty_box' => 100,
        ]);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Reject NF',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        $targetPallet = Pallet::create(['pallet_number' => 'PLT-REJ-01']);

        $create = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => 'BOX-NF-REJ-01',
            'part_number' => 'P-NF-REJ',
            'pcs_quantity' => 80,
            'delivery_order_id' => $order->id,
            'reason' => 'Need adjust',
            'request_type' => 'supplement',
            'target_type' => 'pallet',
            'target_pallet_id' => $targetPallet->id,
        ]);

        $create->assertRedirect();
        $requestId = (int) NotFullBoxRequest::query()->value('id');

        $reject = $this->actingAs($supervisi)->post(route('box-not-full.reject', $requestId));
        $reject->assertRedirect();
        $reject->assertSessionHas('success');

        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $requestId,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseMissing('boxes', [
            'box_number' => 'BOX-NF-REJ-01',
        ]);
    }

    public function test_cannot_reprocess_not_full_request_after_approved_or_rejected(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);

        PartSetting::create([
            'part_number' => 'P-NF-GUARD',
            'qty_box' => 100,
        ]);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Guard NF',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        $targetPallet = Pallet::create(['pallet_number' => 'PLT-GUARD-01']);

        $create = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => 'BOX-NF-GUARD-01',
            'part_number' => 'P-NF-GUARD',
            'pcs_quantity' => 80,
            'delivery_order_id' => $order->id,
            'reason' => 'Guard check',
            'request_type' => 'supplement',
            'target_type' => 'pallet',
            'target_pallet_id' => $targetPallet->id,
        ]);

        $create->assertRedirect();
        $requestId = (int) NotFullBoxRequest::query()->value('id');

        $approve = $this->actingAs($supervisi)->post(route('box-not-full.approve', $requestId));
        $approve->assertRedirect();

        $reApprove = $this->actingAs($supervisi)->post(route('box-not-full.approve', $requestId));
        $reApprove->assertRedirect();
        $reApprove->assertSessionHas('error');

        $rejectAfterApprove = $this->actingAs($supervisi)->post(route('box-not-full.reject', $requestId));
        $rejectAfterApprove->assertRedirect();
        $rejectAfterApprove->assertSessionHas('error');

        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $requestId,
            'status' => 'approved',
        ]);
    }

    public function test_stock_input_not_full_scan_requires_reason_and_delivery_then_persists_on_store(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust NF',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        $orderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-01',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        PartSetting::create([
            'part_number' => 'P-NF-01',
            'qty_box' => 100,
        ]);

        MasterLocation::create([
            'code' => 'A1',
            'is_occupied' => false,
        ]);

        $activePallet = Pallet::create([
            'pallet_number' => 'PLT-001',
        ]);

        $scanBox = $this->actingAs($operator)->withSession([
            'current_pallet_id' => $activePallet->id,
        ])->postJson(route('stock-input.scan-barcode'), [
            'barcode' => 'BOX-NF-01',
        ]);
        $scanBox->assertOk()->assertJson(['success' => true]);

        $missingReason = $this->actingAs($operator)->postJson(route('stock-input.scan-part'), [
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
        ]);
        $missingReason->assertStatus(422)->assertJson(['success' => false]);

        $missingDelivery = $this->actingAs($operator)->postJson(route('stock-input.scan-part'), [
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
            'not_full_reason' => 'Kurang dari standar',
        ]);
        $missingDelivery->assertStatus(422)->assertJson(['success' => false]);

        $scanPart = $this->actingAs($operator)->postJson(route('stock-input.scan-part'), [
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
            'not_full_reason' => 'Kurang dari standar',
            'delivery_order_id' => $order->id,
        ]);
        $scanPart->assertOk()->assertJson(['success' => true]);

        $palletData = $this->actingAs($operator)->getJson(route('stock-input.get-pallet-data'));
        $palletData->assertOk()->assertJson(['success' => true]);
        $palletId = (int) $palletData->json('pallet.id');

        $store = $this->actingAs($operator)->postJson(route('stock-input.store'), [
            'pallet_id' => $palletId,
            'warehouse_location' => 'A1',
        ]);

        $store->assertOk();

        $this->assertDatabaseHas('boxes', [
            'box_number' => 'BOX-NF-01',
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
            'is_not_full' => 1,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $orderItem->id,
            'quantity' => 190,
            'fulfilled_quantity' => 0,
        ]);

        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $palletId,
            'warehouse_location' => 'A1',
        ]);

        $this->assertDatabaseHas('master_locations', [
            'code' => 'A1',
            'is_occupied' => 1,
            'current_pallet_id' => $palletId,
        ]);

        $this->assertDatabaseHas('stock_inputs', [
            'pallet_id' => $palletId,
            'pcs_quantity' => 90,
            'box_quantity' => 1,
            'warehouse_location' => 'A1',
        ]);
    }

    public function test_admin_warehouse_can_create_and_supervisi_can_approve_not_full_request_to_existing_pallet(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);

        PartSetting::create([
            'part_number' => 'P-NF-02',
            'qty_box' => 100,
        ]);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust NF 2',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        $orderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-02',
            'quantity' => 50,
            'fulfilled_quantity' => 0,
        ]);

        $targetPallet = Pallet::create([
            'pallet_number' => 'PLT-500',
        ]);

        \App\Models\StockLocation::create([
            'pallet_id' => $targetPallet->id,
            'warehouse_location' => 'B1',
            'stored_at' => now(),
        ]);

        $create = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => 'BOX-NF-APP-01',
            'part_number' => 'P-NF-02',
            'pcs_quantity' => 80,
            'delivery_order_id' => $order->id,
            'reason' => 'Tambahan urgent',
            'request_type' => 'additional',
            'target_type' => 'pallet',
            'target_pallet_id' => $targetPallet->id,
        ]);

        $create->assertRedirect();
        $create->assertSessionHas('success');

        $request = NotFullBoxRequest::query()->first();
        $this->assertNotNull($request);

        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $request->id,
            'status' => 'pending',
            'requested_by' => $adminWarehouse->id,
        ]);

        $approve = $this->actingAs($supervisi)->post(route('box-not-full.approve', $request->id));

        $approve->assertRedirect();
        $approve->assertSessionHas('success');

        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'approved_by' => $supervisi->id,
        ]);

        $boxId = (int) NotFullBoxRequest::findOrFail($request->id)->box_id;

        $this->assertDatabaseHas('boxes', [
            'id' => $boxId,
            'box_number' => 'BOX-NF-APP-01',
            'part_number' => 'P-NF-02',
            'pcs_quantity' => 80,
            'is_not_full' => 1,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('pallet_boxes', [
            'pallet_id' => $targetPallet->id,
            'box_id' => $boxId,
        ]);

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $targetPallet->id,
            'part_number' => 'P-NF-02',
            'box_quantity' => 1,
            'pcs_quantity' => 80,
        ]);

        $this->assertDatabaseHas('stock_inputs', [
            'pallet_id' => $targetPallet->id,
            'pcs_quantity' => 80,
            'box_quantity' => 1,
            'warehouse_location' => 'B1',
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $orderItem->id,
            'quantity' => 130,
        ]);
    }
}
