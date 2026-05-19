<?php

namespace Database\Seeders;

use App\Models\MasterLocation;
use Illuminate\Database\Seeder;

class MasterLocationSeeder extends Seeder
{
    /**
     * Seed master locations.
     */
    public function run(): void
    {
        $zones = [
            'A' => 10,
            'B' => 10,
            'C' => 10,
            'D' => 10,
        ];

        foreach ($zones as $prefix => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $code = $prefix . $i;

                MasterLocation::updateOrCreate(
                    ['code' => $code],
                    [
                        'is_occupied' => false,
                        'current_pallet_id' => null,
                    ]
                );
            }
        }
    }
}
