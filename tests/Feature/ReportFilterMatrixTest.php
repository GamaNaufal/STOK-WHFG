<?php

namespace Tests\Feature;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\Box;
use App\Models\StockInput;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'part_number' => 'LEGACY-A',
            'box_quantity' => 1,
            'pcs_quantity' => 50,
        ]);

        $itemB = PalletItem::create([
            'pallet_id' => $palletB->id,
            'part_number' => 'LEGACY-B',
            'box_quantity' => 1,
            'pcs_quantity' => 60,
        ]);

        $boxTarget = Box::create([
            'box_number' => 'RPT-MAP-001',
            'part_number' => 'P-SI-RPT-01',
            'pcs_quantity' => 50,
            'qr_code' => 'RPT-MAP-001|P-SI-RPT-01|50',
            'user_id' => $operator->id,
        ]);

        $boxOther = Box::create([
            'box_number' => 'RPT-MAP-002',
            'part_number' => 'P-SI-RPT-02',
            'pcs_quantity' => 60,
            'qr_code' => 'RPT-MAP-002|P-SI-RPT-02|60',
            'user_id' => $operator->id,
        ]);

        $boxTargetOutOfRange = Box::create([
            'box_number' => 'RPT-MAP-003',
            'part_number' => 'P-SI-RPT-01',
            'pcs_quantity' => 50,
            'qr_code' => 'RPT-MAP-003|P-SI-RPT-01|50',
            'user_id' => $operator->id,
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

        $outOfDateRange = StockInput::create([
            'pallet_id' => $palletA->id,
            'pallet_item_id' => $itemA->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'LOC-R2',
            'pcs_quantity' => 50,
            'box_quantity' => 1,
            'stored_at' => Carbon::parse('2026-02-12 08:00:00'),
            'part_numbers' => ['P-SI-RPT-01'],
        ]);

        $differentPart = StockInput::create([
            'pallet_id' => $palletB->id,
            'pallet_item_id' => $itemB->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'LOC-R1',
            'pcs_quantity' => 60,
            'box_quantity' => 1,
            'stored_at' => Carbon::parse('2026-02-10 09:00:00'),
            'part_numbers' => ['P-SI-RPT-02'],
        ]);

        DB::table('stock_input_boxes')->insert([
            [
                'stock_input_id' => $target->id,
                'box_id' => $boxTarget->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'stock_input_id' => $outOfDateRange->id,
                'box_id' => $boxTargetOutOfRange->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'stock_input_id' => $differentPart->id,
                'box_id' => $boxOther->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
