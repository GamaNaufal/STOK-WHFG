<?php

namespace Tests\Feature;

use App\Models\{ExportDeliveryPlan, ExportPartSetting, ExportStockBox, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportOperatorUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pallet_list_sorts_by_creation_time_instead_of_deadline_and_keeps_search(): void
    {
        $this->withoutVite();
        $actor = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($actor);
        foreach (['Earlier', 'Later'] as $i => $name) {
            ExportDeliveryPlan::create(['plan_code' => 'UI-'.$name, 'customer_name' => $name,
                'delivery_deadline' => now()->addDays($i + 1), 'source_type' => 'manual', 'revision' => 1,
                'status' => 'draft', 'total_pcs' => 0, 'total_boxes' => 0,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
                'created_at' => now()->subDays(2 - $i)]);
        }
        $this->get(route('export.pallet-plans.index'))->assertOk()
            ->assertViewHas('deliveries', fn ($rows) => $rows[0]->customer_name === 'Later');
        $this->get(route('export.pallet-plans.index', ['sort' => 'oldest']))->assertOk()
            ->assertViewHas('deliveries', fn ($rows) => $rows[0]->customer_name === 'Earlier');
        $this->get(route('export.pallet-plans.index', ['sort' => 'oldest', 'q' => 'Later']))->assertOk()
            ->assertViewHas('deliveries', fn ($rows) => $rows->total() === 1 && $rows[0]->customer_name === 'Later');
    }

    public function test_placement_queue_uses_creation_time_even_when_fifo_age_is_opposite(): void
    {
        $this->withoutVite();
        $actor = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($actor);
        $part = ExportPartSetting::create(['part_number' => 'UI-QUEUE', 'qty_box' => 10]);
        foreach (['11110001', '11110002'] as $i => $number) {
            ExportStockBox::create(['box_number' => $number, 'part_setting_id' => $part->id,
                'part_number' => $part->part_number, 'pcs_quantity' => 10, 'state' => 'received', 'revision' => 1,
                'received_by' => $actor->id, 'receive_token' => (string) Str::uuid(),
                'first_received_at' => now()->subDays($i + 1), 'created_at' => now()->subDays(2 - $i)]);
        }
        $newer = ExportStockBox::where('box_number', '11110002')->sole();
        $older = ExportStockBox::where('box_number', '11110001')->sole();
        $this->get(route('export.receiving.index'))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => (int) $rows[0]->transaction_id === $newer->id);
        $this->get(route('export.receiving.index', ['pending_sort' => 'oldest']))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => (int) $rows[0]->transaction_id === $older->id);
        $this->assertNull($newer->fresh()->fulfillment);
        $this->assertSame('received', $older->fresh()->state);
    }
}
