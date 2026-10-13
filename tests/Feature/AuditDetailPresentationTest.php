<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDetailPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function detail(array $old, array $new, array $attributes = []): array
    {
        return AuditLogPresenter::present(new AuditLog(array_merge([
            'old_values' => json_encode($old), 'new_values' => json_encode($new),
        ], $attributes)));
    }

    public function test_only_actual_changes_are_compared_and_zero_is_preserved(): void
    {
        $detail = $this->detail(
            ['pcs_quantity' => 10, 'part_number' => '001234', 'location_id' => 1],
            ['pcs_quantity' => 0, 'part_number' => '001234', 'location_id' => 2, 'reason' => 'Koreksi']
        );
        $this->assertSame([['label' => 'Jumlah PCS', 'before' => '10', 'after' => '0']], $detail['changes']);
        $this->assertContains(['label' => 'Nomor part', 'value' => '001234'], $detail['facts']);
        $this->assertStringNotContainsString('location_id', json_encode($detail));
    }

    public function test_deletion_snapshot_is_not_misrepresented_as_cleared_fields(): void
    {
        $detail = $this->detail(
            ['box_number' => '00000012', 'pcs_quantity' => 20],
            ['deleted' => true, 'soft_deleted_box_count' => 0, 'deleted_by_role' => 'admin']
        );
        $this->assertSame([], $detail['changes']);
        $this->assertContains(['label' => 'Nomor box (sebelum aksi)', 'value' => '00000012'], $detail['facts']);
        $this->assertContains(['label' => 'Dihapus dari stok aktif', 'value' => 'Ya'], $detail['facts']);
        $this->assertContains(['label' => 'Box dihapus dari stok aktif', 'value' => '0'], $detail['facts']);
    }

    public function test_moves_compare_pallets_and_partial_location_snapshots(): void
    {
        $detail = $this->detail(['from_pallet' => 'P-01'], ['to_pallet' => 'P-02']);
        $this->assertSame([['label' => 'Pallet', 'before' => 'P-01', 'after' => 'P-02']], $detail['changes']);
        $detail = $this->detail(['location' => 'A-01', 'box_count' => 4], ['location' => 'B-01']);
        $this->assertCount(1, $detail['changes']);
        $this->assertContains(['label' => 'Jumlah box (sebelum aksi)', 'value' => '4'], $detail['facts']);
    }

    public function test_nested_data_is_curated_without_presenting_internal_id_as_box_number(): void
    {
        $detail = $this->detail([], [
            'pick_session_id' => 999, 'assigned_existing_box_ids' => [8, 9],
            'boxes' => [['box_id' => 12345, 'part_number' => 'P-1', 'pcs_quantity' => 1000, 'warehouse_location' => 'A-01']],
            'overage_adjustments' => [['part_number' => 'P-1', 'old_quantity' => 900, 'new_quantity' => 1000, 'increased_by' => 100]],
        ], ['model' => 'DeliveryOrder', 'model_id' => 24]);
        $this->assertContains(['label' => 'Box stok dialokasikan', 'value' => '2'], $detail['facts']);
        $this->assertContains(['label' => 'Delivery order', 'value' => 'DO #24'], $detail['facts']);
        $this->assertSame(['Tidak tercatat', 'P-1', '1.000', 'A-01'], $detail['tables'][0]['rows'][0]);
        $this->assertSame(['P-1', '900', '1.000', '100'], $detail['tables'][1]['rows'][0]);
        $this->assertStringNotContainsString('12345', json_encode($detail));
        $this->assertStringNotContainsString('999', json_encode($detail));
    }

    public function test_missing_or_malformed_legacy_payloads_are_safe(): void
    {
        foreach ([null, 'broken json', 'null', '123', '"legacy"'] as $payload) {
            $detail = AuditLogPresenter::present(new AuditLog(['old_values' => $payload, 'new_values' => $payload]));
            $this->assertSame([], $detail['changes']);
            $this->assertSame([], $detail['facts']);
            $this->assertSame([], $detail['tables']);
        }
    }

    public function test_audit_page_renders_readable_details_and_description_only_events(): void
    {
        $user = User::factory()->create(['role' => 'supervisi']);
        $edit = AuditLog::create([
            'type' => 'other', 'action' => 'box_updated_by_admin_warehouse', 'user_id' => $user->id,
            'description' => 'Koreksi <script>alert(1)</script>',
            'old_values' => json_encode(['pcs_quantity' => 10]),
            'new_values' => json_encode(['pcs_quantity' => 0, 'pick_session_id' => 777]),
            'ip_address' => '192.0.2.123',
        ]);
        $redo = AuditLog::create(['type' => 'delivery_redo', 'action' => 'redo', 'description' => 'Ulang pengiriman']);
        $this->actingAs($user)->get(route('audit.index'))
            ->assertOk()->assertSee('Lihat rincian')->assertSee('Alasan perubahan')
            ->assertSee('Data yang berubah')->assertSee('Jumlah PCS')
            ->assertSee('data-bs-target="#detailModal'.$edit->id.'"', false)
            ->assertSee('id="detailModal'.$redo->id.'"', false)
            ->assertSee('Rincian barang atau perubahan tidak tercatat.')
            ->assertSee('Koreksi &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('pick_session_id')->assertDontSee('192.0.2.123');
    }
}
