<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\NotFullBoxRequest;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFlowConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_pick_uses_box_created_at_even_if_stock_input_stored_at_is_old(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust FIFO CreatedAt',
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-FIFO-CREATED-AT',
            'quantity' => 50,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-FIFO-OLD-STOCKINPUT',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $palletItem = PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-FIFO-CREATED-AT',
            'box_quantity' => 1,
            'pcs_quantity' => 50,
        ]);

        StockInput::create([
            'pallet_id' => $pallet->id,
            'pallet_item_id' => $palletItem->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'A1',
            'pcs_quantity' => 50,
            'box_quantity' => 1,
            'stored_at' => now()->subMonths(13),
            'part_numbers' => ['P-FIFO-CREATED-AT'],
        ]);

        $box = Box::create([
            'box_number' => 'BOX-FIFO-CREATED-AT-01',
            'part_number' => 'P-FIFO-CREATED-AT',
            'part_name' => 'Part FIFO Date Source',
            'pcs_quantity' => 50,
            'qty_box' => 1,
            'qr_code' => 'BOX-FIFO-CREATED-AT-01|P-FIFO-CREATED-AT|50',
            'user_id' => $operator->id,
            'created_at' => now()->subMonths(1),
            'updated_at' => now()->subMonths(1),
        ]);
        $pallet->boxes()->attach($box->id);

        $response = $this->actingAs($operator)
            ->postJson(route('delivery.pick.start', $order->id));

        $response->assertOk()->assertJsonStructure([
            'session_id',
            'pdf_url',
            'scan_url',
        ]);

        $sessionId = (int) $response->json('session_id');

        $this->assertDatabaseHas('delivery_pick_items', [
            'pick_session_id' => $sessionId,
            'box_id' => $box->id,
            'part_number' => 'P-FIFO-CREATED-AT',
            'pcs_quantity' => 50,
            'status' => 'pending',
        ]);
    }

    public function test_schedule_stock_check_skips_oversized_oldest_box_and_uses_next_fifo_box(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Oversized FIFO',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-OVERSIZE-FIFO',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-OVERSIZE-FIFO',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $oversizedOldest = Box::create([
            'box_number' => 'BOX-OVERSIZE-OLD',
            'part_number' => 'P-OVERSIZE-FIFO',
            'part_name' => 'Part Oversize',
            'pcs_quantity' => 200,
            'qty_box' => 1,
            'is_not_full' => false,
            'qr_code' => 'BOX-OVERSIZE-OLD|P-OVERSIZE-FIFO|200',
            'user_id' => $ppc->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $fitNext = Box::create([
            'box_number' => 'BOX-OVERSIZE-NEXT',
            'part_number' => 'P-OVERSIZE-FIFO',
            'part_name' => 'Part Oversize',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'is_not_full' => false,
            'qr_code' => 'BOX-OVERSIZE-NEXT|P-OVERSIZE-FIFO|100',
            'user_id' => $ppc->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $pallet->boxes()->attach([$oversizedOldest->id, $fitNext->id]);

        $response = $this->actingAs($ppc)->get(route('delivery.index'));

        $response->assertOk();

        $approvedOrders = $response->viewData('approvedOrders');
        $this->assertNotNull($approvedOrders);

        $renderedOrder = $approvedOrders->firstWhere('id', $order->id);
        $this->assertNotNull($renderedOrder);

        $renderedItem = $renderedOrder->items->firstWhere('part_number', 'P-OVERSIZE-FIFO');
        $this->assertNotNull($renderedItem);
        $this->assertSame(100, (int) $renderedItem->display_fulfilled);
        $this->assertTrue((bool) $renderedItem->is_fulfillable);
    }

    public function test_admin_warehouse_can_approve_pending_issue_and_unblock_session(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Approve Issue',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'blocked',
            'started_at' => now(),
        ]);

        $issue = DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'BOX-X',
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.issue.approve', $issue->id), [
                'notes' => 'Lanjutkan scan',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('delivery_issues', [
            'id' => $issue->id,
            'status' => 'approved',
            'resolved_by' => $adminWarehouse->id,
            'notes' => 'Lanjutkan scan',
        ]);

        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $session->id,
            'status' => 'scanning',
            'approved_by' => $adminWarehouse->id,
            'approval_notes' => 'Lanjutkan scan',
        ]);
    }

    public function test_non_admin_cannot_approve_issue(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Unauthorized Approve',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'blocked',
            'started_at' => now(),
        ]);

        $issue = DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'BOX-Y',
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($sales)
            ->post(route('delivery.pick.issue.approve', $issue->id), [
                'notes' => 'force',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('delivery_issues', [
            'id' => $issue->id,
            'status' => 'pending',
            'resolved_by' => null,
        ]);
    }

    public function test_cannot_approve_issue_that_is_not_pending(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Non Pending Issue',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'blocked',
            'started_at' => now(),
        ]);

        $issue = DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'BOX-Z',
            'issue_type' => 'scan_mismatch',
            'status' => 'approved',
            'resolved_by' => $adminWarehouse->id,
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.issue.approve', $issue->id), [
                'notes' => 'should-not-overwrite',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('delivery_issues', [
            'id' => $issue->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseMissing('delivery_pick_sessions', [
            'id' => $session->id,
            'status' => 'scanning',
            'approval_notes' => 'should-not-overwrite',
        ]);
    }

    public function test_final_scan_mismatch_blocks_session_and_creates_issue(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust A',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-001',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $boxExpected = Box::create([
            'box_number' => 'BOX-EXPECTED',
            'part_number' => 'P-001',
            'part_name' => 'Part A',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-EXPECTED|P-001|100',
            'user_id' => $operator->id,
        ]);

        $boxMismatch = Box::create([
            'box_number' => 'BOX-MISMATCH',
            'part_number' => 'P-001',
            'part_name' => 'Part A',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-MISMATCH|P-001|100',
            'user_id' => $operator->id,
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $boxExpected->id,
            'part_number' => $boxExpected->part_number,
            'pcs_quantity' => $boxExpected->pcs_quantity,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)
            ->postJson(route('delivery.pick.scan.submit', $session->id), [
                'box_number' => $boxMismatch->box_number,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $session->id,
            'status' => 'blocked',
        ]);

        $this->assertDatabaseHas('delivery_issues', [
            'pick_session_id' => $session->id,
            'scanned_code' => $boxMismatch->box_number,
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);
    }

    public function test_complete_then_redo_restores_withdrawal_box_and_pallet_item_consistently(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust B',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $orderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-100',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-999',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $palletItem = PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-100',
            'box_quantity' => 1,
            'pcs_quantity' => 100,
        ]);

        $box = Box::create([
            'box_number' => 'BOX-REDO-1',
            'part_number' => 'P-100',
            'part_name' => 'Part Redo',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-REDO-1|P-100|100',
            'user_id' => $operator->id,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $pallet->boxes()->attach($box->id);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => $box->pcs_quantity,
            'status' => 'scanned',
            'scanned_at' => now(),
            'scanned_by' => $operator->id,
        ]);

        $complete = $this->actingAs($operator)
            ->postJson(route('delivery.pick.complete', $session->id));

        $complete->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('stock_withdrawals', [
            'box_id' => $box->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'is_withdrawn' => 1,
        ]);

        $this->assertDatabaseHas('pallet_items', [
            'id' => $palletItem->id,
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $orderItem->id,
            'fulfilled_quantity' => 100,
        ]);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $session->id,
            'status' => 'completed',
            'completion_status' => 'completed',
        ]);

        $redo = $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.redo', $session->id));

        $redo->assertRedirect();

        $this->assertDatabaseHas('stock_withdrawals', [
            'box_id' => $box->id,
            'status' => 'reversed',
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'is_withdrawn' => 0,
            'assigned_delivery_order_id' => null,
        ]);

        $this->assertDatabaseHas('pallet_items', [
            'id' => $palletItem->id,
            'box_quantity' => 1,
            'pcs_quantity' => 100,
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $orderItem->id,
            'fulfilled_quantity' => 0,
        ]);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $session->id,
            'completion_status' => 'redone',
        ]);

        $this->assertEquals(0, DeliveryIssue::where('pick_session_id', $session->id)->count());
        $this->assertEquals(1, StockWithdrawal::where('box_id', $box->id)->where('status', 'reversed')->count());
    }

    public function test_start_verification_is_blocked_when_additional_not_full_request_pending(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Pending Approval Gate',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        NotFullBoxRequest::create([
            'box_number' => 'NF-PENDING-START-001',
            'part_number' => 'P-GATE-START',
            'pcs_quantity' => 10,
            'fixed_qty' => 20,
            'reason' => 'Need additional pending approval',
            'request_type' => 'additional',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)
            ->postJson(route('delivery.pick.start-verification', $order->id));

        $response->assertStatus(423)
            ->assertJson([
                'message' => 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.',
            ]);
    }

    public function test_verify_scan_is_blocked_when_additional_not_full_request_pending(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Pending Verify Gate',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        NotFullBoxRequest::create([
            'box_number' => 'NF-PENDING-VERIFY-001',
            'part_number' => 'P-GATE-VERIFY',
            'pcs_quantity' => 10,
            'fixed_qty' => 20,
            'reason' => 'Need additional pending approval',
            'request_type' => 'additional',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)
            ->postJson(route('delivery.pick.verify.scan', $session->id), [
                'box_number' => 'BOX-ANY',
            ]);

        $response->assertStatus(423)
            ->assertJson([
                'success' => false,
                'message' => 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.',
            ]);
    }

    public function test_legacy_fulfill_and_confirm_are_blocked_when_additional_not_full_request_pending(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Pending Legacy Gate',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        NotFullBoxRequest::create([
            'box_number' => 'NF-PENDING-LEGACY-001',
            'part_number' => 'P-GATE-LEGACY',
            'pcs_quantity' => 5,
            'fixed_qty' => 10,
            'reason' => 'Need additional pending approval',
            'request_type' => 'additional',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);

        $fulfillResponse = $this->actingAs($operator)
            ->get(route('delivery.fulfill', $order->id));

        $fulfillResponse->assertRedirect(route('delivery.index'));
        $fulfillResponse->assertSessionHas('error', 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.');

        $confirmResponse = $this->actingAs($operator)
            ->postJson(route('stock-withdrawal.confirm'), [
                'part_number' => 'P-GATE-LEGACY',
                'pcs_quantity' => 1,
                'delivery_order_id' => $order->id,
            ]);

        $confirmResponse->assertStatus(423)
            ->assertJson([
                'success' => false,
                'message' => 'Terjadi kesalahan: Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.',
            ]);
    }

    public function test_delivery_schedule_marks_order_not_ready_when_additional_not_full_request_pending(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Schedule Pending Gate',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SCHEDULE-GATE',
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ]);

        NotFullBoxRequest::create([
            'box_number' => 'NF-PENDING-SCHEDULE-001',
            'part_number' => 'P-SCHEDULE-GATE',
            'pcs_quantity' => 5,
            'fixed_qty' => 10,
            'reason' => 'Need additional pending approval',
            'request_type' => 'additional',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)->get(route('delivery.index'));

        $response->assertOk();
        $approvedOrders = $response->viewData('approvedOrders');
        $this->assertNotNull($approvedOrders);

        $renderedOrder = $approvedOrders->firstWhere('id', $order->id);
        $this->assertNotNull($renderedOrder);
        $this->assertTrue((bool) ($renderedOrder->has_pending_additional_approval ?? false));
        $this->assertFalse((bool) ($renderedOrder->has_sufficient_stock ?? true));
        $this->assertSame('Pending approval not full tambahan', (string) ($renderedOrder->readiness_reason ?? ''));
    }

    public function test_fulfill_data_marks_order_blocked_when_additional_not_full_request_pending(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Cust Fulfill Data Pending Gate',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-FULFILL-DATA-GATE',
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ]);

        NotFullBoxRequest::create([
            'box_number' => 'NF-PENDING-FULFILL-DATA-001',
            'part_number' => 'P-FULFILL-DATA-GATE',
            'pcs_quantity' => 5,
            'fixed_qty' => 10,
            'reason' => 'Need additional pending approval',
            'request_type' => 'additional',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)
            ->getJson(route('delivery.fulfill-data', $order->id));

        $response->assertOk()
            ->assertJson([
                'order_id' => $order->id,
                'is_blocked' => true,
                'blocked_reason' => 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.',
            ]);
    }
}
