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

    public function test_export_history_reserves_identity_after_permanent_removal_and_domestic_insert_cannot_reuse_it(): void
    {
        $data = $this->exportData('00123456');
        $box = ExportStockBox::create($data);
        $box->forceDelete();
        $this->assertDatabaseHas('box_identities', ['box_number' => '00123456', 'origin_scope' => 'export']);
        $this->expectException(\RuntimeException::class);
        Box::create(['box_number' => '00123456', 'part_number' => 'GLOBAL', 'pcs_quantity' => 10, 'qr_code' => 'GLOBAL', 'user_id' => $data['received_by']]);
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
