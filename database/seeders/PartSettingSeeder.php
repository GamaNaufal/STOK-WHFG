<?php

namespace Database\Seeders;

use App\Models\PartSetting;
use Illuminate\Database\Seeder;

class PartSettingSeeder extends Seeder
{
    /**
     * Seed master no part (part settings).
     */
    public function run(): void
    {
        $parts = [
            ['part_number' => 'PN-1001', 'qty_box' => 20],
            ['part_number' => 'PN-1002', 'qty_box' => 25],
            ['part_number' => 'PN-1003', 'qty_box' => 30],
            ['part_number' => 'PN-2001', 'qty_box' => 15],
            ['part_number' => 'PN-2002', 'qty_box' => 10],
        ];

        foreach ($parts as $part) {
            PartSetting::updateOrCreate(
                ['part_number' => $part['part_number']],
                ['qty_box' => $part['qty_box']]
            );
        }
    }
}
