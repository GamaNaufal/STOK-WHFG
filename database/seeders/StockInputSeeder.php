<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockInput;
use App\Models\User;
use App\Models\MasterLocation;
use App\Models\StockLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StockInputSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get or create user
        $user = User::first() ?? User::factory()->create();
        
        // 2. Ensure PartSettings exists
        if (PartSetting::count() === 0) {
            $this->call(PartSettingSeeder::class);
        }
        $parts = PartSetting::all();

        // 3. Ensure MasterLocations exists
        if (MasterLocation::count() === 0) {
            $this->call(MasterLocationSeeder::class);
        }

        // Ambil lokasi-lokasi yang masih kosong
        $availableLocations = MasterLocation::where('is_occupied', false)->get();

        // Tentukan jumlah stock input yang mau dibuat
        $numberOfInputs = min(30, $availableLocations->count()); // Buat sampai 30, atau sebanyak lokasi yang ada
        
        $now = Carbon::now();
        $boxNumberCounter = mt_rand(10000000, 99000000);
        $startIdx = Pallet::max('id') ?? 0;

        for ($i = 0; $i < $numberOfInputs; $i++) {
            $part = $parts->random();
            $location = $availableLocations->pop(); // Ambil satu lokasi dan keluarkan dari koleksi
            
            // Randomize jumlah box untuk pallet ini (misal 1 sampai 5 box)
            $numberOfBoxes = rand(1, 5);

            // 4. Create a pallet
            $pallet = Pallet::create([
                'pallet_number' => sprintf('PLT-%03d', $startIdx + $i + 1),
            ]);

            // 5. Create pallet item
            $palletItem = PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $part->part_number,
                'box_quantity' => $numberOfBoxes,
                'pcs_quantity' => $part->qty_box * $numberOfBoxes,
            ]);

            // 6. Create boxes
            $boxes = [];
            for ($b = 0; $b < $numberOfBoxes; $b++) {
                $boxNum = (string)$boxNumberCounter++;
                
                $qrData = [
                    'box_number' => $boxNum,
                    'part_number' => $part->part_number,
                ];
                
                $box = Box::create([
                    'box_number' => $qrData['box_number'],
                    'part_number' => $part->part_number,
                    'pcs_quantity' => $part->qty_box,
                    'qty_box' => $part->qty_box,
                    'qr_code' => $qrData['box_number'] . '|' . $qrData['part_number'] . '|' . $part->qty_box,
                    'user_id' => $user->id,
                    'is_withdrawn' => false,
                    'is_not_full' => false,
                    'created_at' => $now->copy()->subDays(rand(0, 10))->subHours(rand(0, 23)), // Randomize tanggal masuk
                ]);
                
                // Attach box to pallet
                $pallet->boxes()->attach($box->id);
                $boxes[] = $box->id;
            }

            // 7. Update master location
            if ($location) {
                $location->update([
                    'is_occupied' => true,
                    'current_pallet_id' => $pallet->id,
                ]);
            }

            // 7.5 Create stock location
            StockLocation::create([
                'pallet_id' => $pallet->id,
                'master_location_id' => $location->id,
                'warehouse_location' => $location->code,
                'stored_at' => isset($boxes[0]) ? Box::find($boxes[0])->created_at : $now,
            ]);

            // 8. Create stock input
            $stockInput = StockInput::create([
                'pallet_id' => $pallet->id,
                'pallet_item_id' => $palletItem->id,
                'user_id' => $user->id,
                'warehouse_location' => $location->code,
                'pcs_quantity' => $part->qty_box * $numberOfBoxes,
                'box_quantity' => $numberOfBoxes,
                'part_numbers' => [$part->part_number],
                'stored_at' => isset($boxes[0]) ? Box::find($boxes[0])->created_at : $now,
            ]);

            // 9. Attach boxes to stock input
            $stockInput->boxes()->attach($boxes);
        }
    }
}
