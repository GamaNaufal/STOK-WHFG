<?php

namespace Tests\Unit;

use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterLocationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupy_and_vacate_flow_updates_fields(): void
    {
        $pallet = Pallet::create(['pallet_number' => 'PLT-777']);
        $location = MasterLocation::create([
            'code' => 'X1',
            'is_occupied' => false,
        ]);

        $location->occupyWithPallet($pallet->id);

        $location->refresh();
        $this->assertTrue((bool) $location->is_occupied);
        $this->assertSame((int) $pallet->id, (int) $location->current_pallet_id);

        $location->vacate();
        $location->refresh();

        $this->assertFalse((bool) $location->is_occupied);
        $this->assertNull($location->current_pallet_id);
    }

    public function test_auto_vacate_if_empty_works_for_empty_and_non_empty_pallet(): void
    {
        $pallet = Pallet::create(['pallet_number' => 'PLT-778']);

        $location = MasterLocation::create([
            'code' => 'X2',
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);

        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-LOC-1',
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ]);

        $location->autoVacateIfEmpty();
        $location->refresh();
        $this->assertTrue((bool) $location->is_occupied);

        $pallet->items()->update([
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);

        $location->autoVacateIfEmpty();
        $location->refresh();
        $this->assertFalse((bool) $location->is_occupied);
        $this->assertNull($location->current_pallet_id);
    }
}
