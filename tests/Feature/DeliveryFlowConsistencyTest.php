<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFlowConsistencyTest extends TestCase
{
    use RefreshDatabase;

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
}
