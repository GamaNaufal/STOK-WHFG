<?php

namespace Tests\Feature;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockInput;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportFilterMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_report_filter_matrix_applies_date_status_part_and_user(): void
    {
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $operatorA = User::factory()->create(['role' => 'warehouse_operator']);
        $operatorB = User::factory()->create(['role' => 'warehouse_operator']);

        $target = StockWithdrawal::create([
            'user_id' => $operatorA->id,
            'part_number' => 'P-RPT-01',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'warehouse_location' => 'R1',
            'status' => 'completed',
            'withdrawn_at' => Carbon::parse('2026-02-10 08:00:00'),
        ]);

        StockWithdrawal::create([
            'user_id' => $operatorA->id,
            'part_number' => 'P-RPT-01',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'warehouse_location' => 'R1',
            'status' => 'reversed',
            'withdrawn_at' => Carbon::parse('2026-02-10 09:00:00'),
        ]);

        StockWithdrawal::create([
            'user_id' => $operatorB->id,
            'part_number' => 'P-RPT-02',
            'pcs_quantity' => 120,
            'box_quantity' => 1,
            'warehouse_location' => 'R2',
            'status' => 'completed',
            'withdrawn_at' => Carbon::parse('2026-02-12 08:00:00'),
        ]);

        $response = $this->actingAs($supervisi)->get(route('reports.withdrawal', [
            'start_date' => '2026-02-09',
            'end_date' => '2026-02-11',
            'status' => 'completed',
            'part_number' => 'P-RPT-01',
            'user_id' => $operatorA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('withdrawals', function ($withdrawals) use ($target) {
            return $withdrawals->total() === 1
                && (int) $withdrawals->first()->id === (int) $target->id;
        });

        $response->assertViewHas('filters', function ($filters) use ($operatorA) {
            return $filters['start_date'] === '2026-02-09'
                && $filters['end_date'] === '2026-02-11'
                && $filters['status'] === 'completed'
                && $filters['part_number'] === 'P-RPT-01'
                && (int) $filters['user_id'] === (int) $operatorA->id;
        });
    }

    public function test_stock_input_report_filter_matrix_applies_date_part_and_location(): void
    {
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $palletA = Pallet::create(['pallet_number' => 'PLT-RPT-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-RPT-B']);

        $itemA = PalletItem::create([
            'pallet_id' => $palletA->id,
            'part_number' => 'P-SI-RPT-01',
            'box_quantity' => 1,
            'pcs_quantity' => 50,
        ]);

        $itemB = PalletItem::create([
            'pallet_id' => $palletB->id,
            'part_number' => 'P-SI-RPT-02',
            'box_quantity' => 1,
            'pcs_quantity' => 60,
        ]);

        $target = StockInput::create([
            'pallet_id' => $palletA->id,
            'pallet_item_id' => $itemA->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'LOC-R1',
            'pcs_quantity' => 50,
            'box_quantity' => 1,
            'stored_at' => Carbon::parse('2026-02-10 08:00:00'),
            'part_numbers' => ['P-SI-RPT-01'],
        ]);

        StockInput::create([
            'pallet_id' => $palletA->id,
            'pallet_item_id' => $itemA->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'LOC-R2',
            'pcs_quantity' => 50,
            'box_quantity' => 1,
            'stored_at' => Carbon::parse('2026-02-12 08:00:00'),
            'part_numbers' => ['P-SI-RPT-01'],
        ]);

        StockInput::create([
            'pallet_id' => $palletB->id,
            'pallet_item_id' => $itemB->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'LOC-R1',
            'pcs_quantity' => 60,
            'box_quantity' => 1,
            'stored_at' => Carbon::parse('2026-02-10 09:00:00'),
            'part_numbers' => ['P-SI-RPT-02'],
        ]);

        $response = $this->actingAs($supervisi)->get(route('reports.stock-input', [
            'start_date' => '2026-02-09',
            'end_date' => '2026-02-11',
            'part_number' => 'P-SI-RPT-01',
            'warehouse_location' => 'LOC-R1',
        ]));

        $response->assertOk();
        $response->assertViewHas('stockInputs', function ($stockInputs) use ($target) {
            return $stockInputs->total() === 1
                && (int) $stockInputs->first()->id === (int) $target->id;
        });

        $response->assertViewHas('filters', function ($filters) {
            return $filters['start_date'] === '2026-02-09'
                && $filters['end_date'] === '2026-02-11'
                && $filters['part_number'] === 'P-SI-RPT-01'
                && $filters['warehouse_location'] === 'LOC-R1';
        });
    }
}
