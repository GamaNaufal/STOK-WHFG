<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartNumberAndBoxIdValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_setting_allows_letters_in_part_number(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);

        $response = $this->actingAs($adminWarehouse)->post(route('part-settings.store'), [
            'part_number' => 'ABC-123-X',
            'qty_box' => 50,
        ]);

        $response->assertRedirect(route('part-settings.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('part_settings', [
            'part_number' => 'ABC-123-X',
            'qty_box' => 50,
        ]);
    }

    public function test_stock_input_scan_barcode_rejects_letter_box_id(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $response = $this->actingAs($operator)->postJson(route('stock-input.scan-barcode'), [
            'barcode' => 'BOX123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barcode']);
    }

    public function test_stock_input_scan_qr_rejects_letter_box_id(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $response = $this->actingAs($operator)->postJson(route('stock-input.scan-box'), [
            'qr_data' => 'BOX123|ABC-123-X|50',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'ID Box hanya boleh berisi angka.',
        ]);
    }
}
