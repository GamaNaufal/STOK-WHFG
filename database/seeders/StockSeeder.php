<?php

namespace Database\Seeders;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * Menambahkan 5 box stok dengan data lengkap (Pallet, PalletItem, StockLocation)
     */
    public function run(): void
    {
        // Define 5 boxes data dengan berbagai part number
        $stockData = [
            [
                'pallet_number' => 'PLT-SKD-001',
                'part_number' => 'PN-MOTOR-2024-A',
                'box_quantity' => 1,
                'pcs_quantity' => 50,
                'warehouse_location' => 'A-1-1',
            ],
            [
                'pallet_number' => 'PLT-SKD-002',
                'part_number' => 'PN-GEAR-2024-B',
                'box_quantity' => 1,
                'pcs_quantity' => 75,
                'warehouse_location' => 'A-1-2',
            ],
            [
                'pallet_number' => 'PLT-SKD-003',
                'part_number' => 'PN-BEARING-2024-C',
                'box_quantity' => 1,
                'pcs_quantity' => 100,
                'warehouse_location' => 'A-2-1',
            ],
            [
                'pallet_number' => 'PLT-SKD-004',
                'part_number' => 'PN-SHAFT-2024-D',
                'box_quantity' => 1,
                'pcs_quantity' => 60,
                'warehouse_location' => 'A-2-2',
            ],
            [
                'pallet_number' => 'PLT-SKD-005',
                'part_number' => 'PN-SEAL-2024-E',
                'box_quantity' => 1,
                'pcs_quantity' => 80,
                'warehouse_location' => 'B-1-1',
            ],
        ];

        // Create 5 pallets dengan items dan locations
        foreach ($stockData as $data) {
            // Create Pallet (hanya dengan pallet_number)
            $pallet = Pallet::create([
                'pallet_number' => $data['pallet_number'],
            ]);

            // Create PalletItem (dengan quantity details)
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $data['part_number'],
                'box_quantity' => $data['box_quantity'],
                'pcs_quantity' => $data['pcs_quantity'],
            ]);

            // Create StockLocation
            StockLocation::create([
                'pallet_id' => $pallet->id,
                'warehouse_location' => $data['warehouse_location'],
                'stored_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        $this->command->info('✅ 5 box stok berhasil ditambahkan!');
        $this->command->info('📦 Pallet: PLT-SKD-001 s/d PLT-SKD-005');
        $this->command->info('📍 Lokasi: A-1-1 s/d B-1-1');
    }
}
