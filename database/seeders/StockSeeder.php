<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockInput;
use App\Models\StockInputBox;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockSeeder extends Seeder
{
    /**
     * Seed sample stock data for warehouse flow.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $operator = User::where('email', 'andi@warehouse.local')->first()
                ?? User::where('role', 'warehouse_operator')->first()
                ?? User::first();

            if (!$operator) {
                return;
            }

            // Ensure part settings exist even when this seeder runs standalone.
            $partMap = [
                'PN-1001' => ['qty_box' => 20, 'part_name' => 'Bracket Assy A'],
                'PN-1002' => ['qty_box' => 25, 'part_name' => 'Bracket Assy B'],
                'PN-1003' => ['qty_box' => 30, 'part_name' => 'Cover Unit C'],
            ];

            foreach ($partMap as $partNumber => $config) {
                PartSetting::updateOrCreate(
                    ['part_number' => $partNumber],
                    ['qty_box' => $config['qty_box']]
                );
            }

            $palletA = Pallet::firstOrCreate(['pallet_number' => 'PLT-SEED-001']);
            $palletB = Pallet::firstOrCreate(['pallet_number' => 'PLT-SEED-002']);

            $locationA = MasterLocation::updateOrCreate(
                ['code' => 'A-1-1'],
                ['is_occupied' => true, 'current_pallet_id' => $palletA->id]
            );

            $locationB = MasterLocation::updateOrCreate(
                ['code' => 'A-1-2'],
                ['is_occupied' => true, 'current_pallet_id' => $palletB->id]
            );

            StockLocation::updateOrCreate(
                ['pallet_id' => $palletA->id],
                [
                    'master_location_id' => $locationA->id,
                    'warehouse_location' => $locationA->code,
                    'stored_at' => now()->subDays(2),
                ]
            );

            StockLocation::updateOrCreate(
                ['pallet_id' => $palletB->id],
                [
                    'master_location_id' => $locationB->id,
                    'warehouse_location' => $locationB->code,
                    'stored_at' => now()->subDay(),
                ]
            );

            $palletItemA1 = PalletItem::updateOrCreate(
                ['pallet_id' => $palletA->id, 'part_number' => 'PN-1001'],
                ['box_quantity' => 2, 'pcs_quantity' => 40]
            );

            $palletItemA2 = PalletItem::updateOrCreate(
                ['pallet_id' => $palletA->id, 'part_number' => 'PN-1002'],
                ['box_quantity' => 1, 'pcs_quantity' => 25]
            );

            $palletItemB1 = PalletItem::updateOrCreate(
                ['pallet_id' => $palletB->id, 'part_number' => 'PN-1003'],
                ['box_quantity' => 2, 'pcs_quantity' => 60]
            );

            $boxes = [
                ['box_number' => 'BX-SEED-0001', 'part_number' => 'PN-1001', 'part_name' => $partMap['PN-1001']['part_name'], 'pcs_quantity' => 20, 'qty_box' => 20, 'pallet' => $palletA],
                ['box_number' => 'BX-SEED-0002', 'part_number' => 'PN-1001', 'part_name' => $partMap['PN-1001']['part_name'], 'pcs_quantity' => 20, 'qty_box' => 20, 'pallet' => $palletA],
                ['box_number' => 'BX-SEED-0003', 'part_number' => 'PN-1002', 'part_name' => $partMap['PN-1002']['part_name'], 'pcs_quantity' => 25, 'qty_box' => 25, 'pallet' => $palletA],
                ['box_number' => 'BX-SEED-0004', 'part_number' => 'PN-1003', 'part_name' => $partMap['PN-1003']['part_name'], 'pcs_quantity' => 30, 'qty_box' => 30, 'pallet' => $palletB],
                ['box_number' => 'BX-SEED-0005', 'part_number' => 'PN-1003', 'part_name' => $partMap['PN-1003']['part_name'], 'pcs_quantity' => 30, 'qty_box' => 30, 'pallet' => $palletB],
            ];

            $boxByNumber = [];
            foreach ($boxes as $boxData) {
                $box = Box::updateOrCreate(
                    ['box_number' => $boxData['box_number']],
                    [
                        'part_number' => $boxData['part_number'],
                        'part_name' => $boxData['part_name'],
                        'pcs_quantity' => $boxData['pcs_quantity'],
                        'qty_box' => $boxData['qty_box'],
                        'type_box' => 'FG',
                        'wk_transfer' => 'WK20',
                        'lot01' => 'LOT-A',
                        'lot02' => 'LOT-B',
                        'lot03' => 'LOT-C',
                        'qr_code' => json_encode([
                            'box_number' => $boxData['box_number'],
                            'part_number' => $boxData['part_number'],
                        ], JSON_UNESCAPED_SLASHES),
                        'user_id' => $operator->id,
                        'is_withdrawn' => false,
                        'is_not_full' => false,
                        'expired_status' => 'active',
                    ]
                );

                $boxData['pallet']->boxes()->syncWithoutDetaching([$box->id]);
                $boxByNumber[$box->box_number] = $box;
            }

            $palletItemIdByPart = [
                'PN-1001' => $palletItemA1->id,
                'PN-1002' => $palletItemA2->id,
                'PN-1003' => $palletItemB1->id,
            ];

            $locationByPalletId = [
                $palletA->id => $locationA->code,
                $palletB->id => $locationB->code,
            ];

            $seededBoxes = [
                'BX-SEED-0001',
                'BX-SEED-0002',
                'BX-SEED-0003',
                'BX-SEED-0004',
                'BX-SEED-0005',
            ];

            foreach ($seededBoxes as $index => $boxNumber) {
                $box = $boxByNumber[$boxNumber] ?? null;
                if (!$box) {
                    continue;
                }

                $palletId = $box->pallets()->pluck('pallets.id')->first();
                if (!$palletId || !isset($locationByPalletId[$palletId])) {
                    continue;
                }

                $storedAt = now()->subDays(2)->addMinutes($index + 1);
                if ((int) $palletId === (int) $palletB->id) {
                    $storedAt = now()->subDay()->addMinutes($index + 1);
                }

                $stockInput = StockInput::updateOrCreate(
                    [
                        'pallet_id' => $palletId,
                        'pallet_item_id' => $palletItemIdByPart[$box->part_number] ?? null,
                        'user_id' => $operator->id,
                        'warehouse_location' => $locationByPalletId[$palletId],
                        'stored_at' => $storedAt,
                    ],
                    [
                        'pcs_quantity' => $box->pcs_quantity,
                        'box_quantity' => 1,
                        'part_numbers' => [$box->part_number],
                    ]
                );

                StockInputBox::updateOrCreate(
                    ['stock_input_id' => $stockInput->id, 'box_id' => $box->id],
                    []
                );
            }
        });
    }
}
