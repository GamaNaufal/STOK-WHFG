<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockViewBoxEditAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_warehouse_can_update_box_and_audit_is_logged(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-BOX-EDIT-01']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => 'BOX-EDIT-01',
            'part_number' => 'P-OLD',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-EDIT-01|P-OLD|100',
            'user_id' => $adminWarehouse->id,
        ]);
        $pallet->boxes()->attach($box->id);

        $response = $this->actingAs($adminWarehouse)->postJson(route('stock-view.box-update', $box->id), [
            'part_number' => 'P-NEW',
            'pcs_quantity' => 120,
            'stored_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'reason' => 'Koreksi data masuk',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'part_number' => 'P-NEW',
            'pcs_quantity' => 120,
        ]);

        $audit = AuditLog::where('type', 'other')
            ->where('model', 'Box')
            ->where('action', 'box_updated_by_admin_warehouse')
            ->where('model_id', $box->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('box_updated_by_admin_warehouse', $audit->action);
        $this->assertSame($adminWarehouse->id, (int) $audit->user_id);

        $oldValues = $audit->getOldValuesArray();
        $newValues = $audit->getNewValuesArray();

        $this->assertSame('P-OLD', $oldValues['part_number'] ?? null);
        $this->assertSame('P-NEW', $newValues['part_number'] ?? null);
        $this->assertSame(120, (int) ($newValues['pcs_quantity'] ?? 0));
        $this->assertSame('Koreksi data masuk', $newValues['reason'] ?? null);
    }

    public function test_non_admin_warehouse_cannot_update_box(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $owner = User::factory()->create(['role' => 'admin_warehouse']);

        $box = Box::create([
            'box_number' => 'BOX-EDIT-02',
            'part_number' => 'P-LOCK',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-EDIT-02|P-LOCK|100',
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($operator)->postJson(route('stock-view.box-update', $box->id), [
            'part_number' => 'P-LOCK-2',
            'pcs_quantity' => 120,
            'stored_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'Unauthorized try',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'part_number' => 'P-LOCK',
            'pcs_quantity' => 100,
        ]);
    }

    public function test_box_history_endpoint_returns_box_update_logs(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);

        $box = Box::create([
            'box_number' => 'BOX-HIST-01',
            'part_number' => 'P-HIST',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'qr_code' => 'BOX-HIST-01|P-HIST|100',
            'user_id' => $adminWarehouse->id,
        ]);

        AuditLog::create([
            'type' => 'other',
            'action' => 'box_updated_by_admin_warehouse',
            'model' => 'Box',
            'model_id' => $box->id,
            'old_values' => json_encode(['part_number' => 'P-HIST', 'pcs_quantity' => 100, 'stored_at' => now()->subDay()->format('Y-m-d H:i:s')]),
            'new_values' => json_encode(['part_number' => 'P-HIST-NEW', 'pcs_quantity' => 90, 'stored_at' => now()->format('Y-m-d H:i:s'), 'reason' => 'Penyesuaian']),
            'description' => 'Edit detail box oleh admin warehouse. Alasan: Penyesuaian',
            'user_id' => $adminWarehouse->id,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($supervisi)->getJson(route('stock-view.box-history', $box->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'box_id',
            'box_number',
            'history' => [
                ['id', 'action', 'description', 'old_values', 'new_values', 'user_name', 'created_at'],
            ],
        ]);
        $response->assertJsonFragment([
            'box_number' => 'BOX-HIST-01',
            'action' => 'box_updated_by_admin_warehouse',
        ]);
    }

    public function test_box_edit_keeps_not_full_flag_and_syncs_pallet_items(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-NF-01']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A2',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => 'BOX-NF-01',
            'part_number' => 'P-NF-OLD',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'is_not_full' => true,
            'not_full_reason' => 'Sisa pemenuhan',
            'qr_code' => 'BOX-NF-01|P-NF-OLD|100',
            'user_id' => $adminWarehouse->id,
        ]);
        $pallet->boxes()->attach($box->id);

        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-NF-OLD',
            'box_quantity' => 1,
            'pcs_quantity' => 100,
        ]);

        $response = $this->actingAs($adminWarehouse)->postJson(route('stock-view.box-update', $box->id), [
            'part_number' => 'P-NF-NEW',
            'pcs_quantity' => 80,
            'stored_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
            'reason' => 'Koreksi untuk rekomendasi',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'part_number' => 'P-NF-NEW',
            'pcs_quantity' => 80,
            'is_not_full' => 1,
            'not_full_reason' => 'Sisa pemenuhan',
        ]);

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => 'P-NF-OLD',
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => 'P-NF-NEW',
            'box_quantity' => 1,
            'pcs_quantity' => 80,
        ]);
    }

    public function test_box_edit_changes_pre_fulfillment_schedule_recommendation(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-FIFO-01']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'B1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => 'BOX-FIFO-01',
            'part_number' => 'P-FIFO-01',
            'pcs_quantity' => 100,
            'qty_box' => 1,
            'is_not_full' => false,
            'qr_code' => 'BOX-FIFO-01|P-FIFO-01|100',
            'user_id' => $adminWarehouse->id,
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);
        $pallet->boxes()->attach($box->id);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'FIFO Impact Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-FIFO-01',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $before = $this->actingAs($ppc)->get(route('delivery.index'));
        $before->assertOk();
        $before->assertSee('100 / 100', false);

        $update = $this->actingAs($adminWarehouse)->postJson(route('stock-view.box-update', $box->id), [
            'part_number' => 'P-FIFO-01',
            'pcs_quantity' => 60,
            'stored_at' => now()->subHours(4)->format('Y-m-d H:i:s'),
            'reason' => 'Penyesuaian sebelum pemenuhan',
        ]);
        $update->assertOk();

        $after = $this->actingAs($ppc)->get(route('delivery.index'));
        $after->assertOk();
        $after->assertSee('60 / 100', false);
    }
}
