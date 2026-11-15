<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\ExportStockBox;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalBoxIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function exportData(string $number): array
    {
        $part = PartSetting::firstOrCreate(['part_number' => 'GLOBAL'], ['qty_box' => 10]);

        return ['box_number' => $number, 'part_setting_id' => $part->id, 'part_number' => 'GLOBAL', 'pcs_quantity' => 10,
            'receive_token' => (string) Str::uuid(), 'first_received_at' => now(), 'received_by' => User::factory()->create()->id];
    }

    public function test_permanently_removed_export_number_can_start_a_new_domestic_use(): void
    {
        $data = $this->exportData('00123456');
        $box = ExportStockBox::create($data);
        $box->forceDelete();
        $this->assertDatabaseHas('box_identities', ['box_number' => '00123456', 'origin_scope' => 'export']);
        $replacement = Box::create(['box_number' => '00123456', 'part_number' => 'OTHER', 'pcs_quantity' => 20, 'qr_code' => 'OTHER', 'user_id' => $data['received_by']]);
        $this->assertSame(2, (int) $replacement->identity_cycle);
        $this->assertDatabaseHas('box_identities', ['box_number' => '00123456', 'origin_scope' => 'domestic', 'last_cycle' => 2]);
    }

    public function test_failed_export_creation_rolls_back_identity_and_retains_the_original_receive_token(): void
    {
        $data = $this->exportData('00123456');
        ExportStockBox::create($data);
        try {
            ExportStockBox::create(array_replace($data, ['box_number' => '00123457']));
            $this->fail('Duplicate token must fail.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00123457']);
        $this->assertDatabaseCount('export_stock_boxes', 1);
    }

    public function test_the_model_claim_rejects_a_duplicate_active_number_and_rolls_back_its_cycle(): void
    {
        $data = $this->exportData('00123456');
        $old = ExportStockBox::create($data);
        $old->delete();
        $current = ExportStockBox::create(array_replace($data, ['receive_token' => (string) Str::uuid()]));
        try {
            Box::create(['box_number' => $current->box_number, 'part_number' => 'OTHER', 'pcs_quantity' => 20,
                'qr_code' => 'OTHER', 'user_id' => $data['received_by']]);
            $this->fail('Only one active use may own a label across both scopes.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('masih digunakan', $error->getMessage());
        }
        $this->assertDatabaseCount('boxes', 0);
        $this->assertDatabaseHas('box_identities', ['box_number' => $current->box_number, 'last_cycle' => 2]);
        $this->assertSame($current->id, ExportStockBox::sole()->id);
    }

    public function test_registered_identity_is_immutable_and_regular_updates_do_not_register_again(): void
    {
        $box = ExportStockBox::create($this->exportData('00123456'));
        $box->update(['revision' => 2]);
        $this->assertDatabaseCount('box_identities', 1);
        try {
            $box->update(['box_number' => '00123457']);
            $this->fail('Identity must remain immutable.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('box_number', $error->errors());
        }
        $this->assertDatabaseHas('export_stock_boxes', ['id' => $box->id, 'box_number' => '00123456', 'revision' => 2]);
        $this->assertDatabaseMissing('box_identities', ['box_number' => '00123457']);
    }
}
