<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickSession;
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
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-LOC',
            'quantity' => 75,
            'fulfilled_quantity' => 0,
        ]);

        MasterLocation::create([
            'code' => 'LOC-NEW-1',
            'is_occupied' => false,
        ]);

        $requestCreate = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => '91000001',
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
            'box_number' => '91000001',
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
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-REJ',
            'quantity' => 80,
            'fulfilled_quantity' => 0,
        ]);

        $targetPallet = Pallet::create(['pallet_number' => 'PLT-REJ-01']);
        \App\Models\StockLocation::create([
            'pallet_id' => $targetPallet->id,
            'warehouse_location' => 'NF-REJ-1',
            'stored_at' => now(),
        ]);

        $create = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => '91000002',
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
            'box_number' => '91000002',
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
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-GUARD',
            'quantity' => 80,
            'fulfilled_quantity' => 0,
        ]);

        $targetPallet = Pallet::create(['pallet_number' => 'PLT-GUARD-01']);
        \App\Models\StockLocation::create([
            'pallet_id' => $targetPallet->id,
            'warehouse_location' => 'NF-GUARD-1',
            'stored_at' => now(),
        ]);

        $create = $this->actingAs($adminWarehouse)->post(route('box-not-full.store'), [
            'box_number' => '91000003',
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

    public function test_supplement_request_rejects_part_not_present_in_delivery_order(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create([
            'part_number' => 'P-NF-NOT-ORDERED',
            'qty_box' => 100,
        ]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Missing Part',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);
        $location = MasterLocation::create([
            'code' => 'NF-MISSING-PART',
            'is_occupied' => false,
        ]);

        $response = $this->actingAs($operator)->post(route('box-not-full.store'), [
            'box_number' => '91000006',
            'part_number' => 'P-NF-NOT-ORDERED',
            'pcs_quantity' => 50,
            'delivery_order_id' => $order->id,
            'reason' => 'Part tidak ada di order',
            'request_type' => 'supplement',
            'target_type' => 'location',
            'target_location_id' => $location->id,
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseMissing('not_full_box_requests', [
            'box_number' => '91000006',
        ]);
    }

    public function test_supplement_request_rejects_quantity_above_uncovered_order_need(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create([
            'part_number' => 'P-NF-CAPACITY',
            'qty_box' => 100,
        ]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Supplement Capacity',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-CAPACITY',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);
        Box::create([
            'box_number' => '91000007',
            'part_number' => 'P-NF-CAPACITY',
            'pcs_quantity' => 70,
            'qty_box' => 100,
            'qr_code' => '91000007|P-NF-CAPACITY|70',
            'user_id' => $operator->id,
            'is_not_full' => true,
            'assigned_delivery_order_id' => $order->id,
        ]);
        $location = MasterLocation::create([
            'code' => 'NF-CAPACITY',
            'is_occupied' => false,
        ]);

        $response = $this->actingAs($operator)->post(route('box-not-full.store'), [
            'box_number' => '91000008',
            'part_number' => 'P-NF-CAPACITY',
            'pcs_quantity' => 40,
            'delivery_order_id' => $order->id,
            'reason' => 'Melebihi sisa kebutuhan',
            'request_type' => 'supplement',
            'target_type' => 'location',
            'target_location_id' => $location->id,
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseMissing('not_full_box_requests', [
            'box_number' => '91000008',
        ]);
    }

    public function test_not_full_approval_is_rejected_after_delivery_enters_active_picking(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create([
            'part_number' => 'P-NF-ACTIVE-PICK',
            'qty_box' => 100,
        ]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Active Pick Guard',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'status' => 'processing',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-ACTIVE-PICK',
            'quantity' => 80,
            'fulfilled_quantity' => 0,
        ]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-NF-ACTIVE-PICK']);
        \App\Models\StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'NF-ACTIVE-PICK',
            'stored_at' => now(),
        ]);
        $notFullRequest = NotFullBoxRequest::create([
            'box_number' => '91000009',
            'part_number' => 'P-NF-ACTIVE-PICK',
            'pcs_quantity' => 80,
            'fixed_qty' => 100,
            'reason' => 'Request dibuat sebelum picking',
            'request_type' => 'supplement',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'target_pallet_id' => $pallet->id,
            'status' => 'pending',
        ]);
        DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($supervisi)
            ->post(route('box-not-full.approve', $notFullRequest->id));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('not_full_box_requests', [
            'id' => $notFullRequest->id,
            'status' => 'pending',
            'box_id' => null,
        ]);
        $this->assertDatabaseMissing('boxes', [
            'box_number' => '91000009',
        ]);
    }

    public function test_stock_input_not_full_must_use_supervisor_approval_flow(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust NF',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
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
            'barcode' => '91000004',
        ]);
        $scanBox->assertOk()->assertJson(['success' => true]);

        $directNotFull = $this->actingAs($operator)->postJson(route('stock-input.scan-part'), [
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
        ]);
        $directNotFull->assertStatus(422)->assertJson([
            'success' => false,
            'requires_not_full_approval' => true,
        ]);
        $this->assertStringContainsString('approval Supervisi', (string) $directNotFull->json('message'));
        $this->assertSame(route('box-not-full.create'), $directNotFull->json('not_full_request_url'));

        $this->actingAs($operator)->get(route('box-not-full.create'))->assertOk();

        $requestResponse = $this->actingAs($operator)->post(route('box-not-full.store'), [
            'box_number' => '91000004',
            'part_number' => 'P-NF-01',
            'pcs_quantity' => 90,
            'delivery_order_id' => $order->id,
            'reason' => 'Kurang dari standar',
            'request_type' => 'supplement',
            'target_type' => 'location',
            'target_location_id' => MasterLocation::where('code', 'A1')->value('id'),
        ]);
        $requestResponse->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('not_full_box_requests', [
            'box_number' => '91000004',
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('boxes', ['box_number' => '91000004']);
        $this->assertDatabaseMissing('stock_inputs', ['pallet_id' => $activePallet->id]);
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
            'box_number' => '91000005',
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
            'box_number' => '91000005',
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

        $stockInputId = (int) \App\Models\StockInput::query()
            ->where('pallet_id', $targetPallet->id)
            ->latest('id')
            ->value('id');
        $this->assertDatabaseHas('stock_input_boxes', [
            'stock_input_id' => $stockInputId,
            'box_id' => $boxId,
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $orderItem->id,
            'quantity' => 130,
        ]);
    }
}
