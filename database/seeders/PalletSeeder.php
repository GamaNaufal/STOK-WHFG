<?php

namespace Database\Seeders;

use App\Models\Pallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pallet::create([
            'pallet_number' => 'PLT-001-2026',
            'part_number' => 'PN-A001',
            'box_quantity' => 20,
            'pcs_quantity' => 200,
        ]);

        Pallet::create([
            'pallet_number' => 'PLT-002-2026',
            'part_number' => 'PN-A001',
            'box_quantity' => 15,
            'pcs_quantity' => 300,
        ]);

        Pallet::create([
            'pallet_number' => 'PLT-003-2026',
            'part_number' => 'PN-B002',
            'box_quantity' => 25,
            'pcs_quantity' => 500,
        ]);

        Pallet::create([
            'pallet_number' => 'PLT-004-2026',
            'part_number' => 'PN-C003',
            'box_quantity' => 18,
            'pcs_quantity' => 450,
        ]);

        Pallet::create([
            'pallet_number' => 'PLT-005-2026',
            'part_number' => 'PN-D004',
            'box_quantity' => 22,
            'pcs_quantity' => 550,
        ]);
    }
}
