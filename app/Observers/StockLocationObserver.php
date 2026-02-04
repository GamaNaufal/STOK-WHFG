<?php

namespace App\Observers;

use App\Models\MasterLocation;
use App\Models\StockLocation;

class StockLocationObserver
{
    /**
     * Handle the StockLocation "created" event.
     */
    public function created(StockLocation $stockLocation): void
    {
        $masterLocation = MasterLocation::where('code', $stockLocation->warehouse_location)->first();

        if ($masterLocation) {
            $masterLocation->update([
                'is_occupied' => true,
                'current_pallet_id' => $stockLocation->pallet_id,
            ]);
        }
    }

    /**
     * Handle the StockLocation "updated" event.
     */
    public function updated(StockLocation $stockLocation): void
    {
        $originalCode = $stockLocation->getOriginal('warehouse_location');
        $originalPalletId = $stockLocation->getOriginal('pallet_id');

        if ($originalCode && $originalCode !== $stockLocation->warehouse_location) {
            $oldLocation = MasterLocation::where('code', $originalCode)->first();

            if ($oldLocation && $oldLocation->current_pallet_id == $originalPalletId) {
                $oldLocation->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                ]);
            }
        }

        $newCode = $stockLocation->warehouse_location;
        if ($newCode) {
            $newLocation = MasterLocation::where('code', $newCode)->first();

            if ($newLocation) {
                $newLocation->update([
                    'is_occupied' => true,
                    'current_pallet_id' => $stockLocation->pallet_id,
                ]);
            }
        }
    }

    /**
     * Handle the StockLocation "deleted" event.
     */
    public function deleted(StockLocation $stockLocation): void
    {
        $masterLocation = MasterLocation::where('code', $stockLocation->warehouse_location)->first();

        if ($masterLocation && $masterLocation->current_pallet_id == $stockLocation->pallet_id) {
            $masterLocation->update([
                'is_occupied' => false,
                'current_pallet_id' => null,
            ]);
        }
    }

    /**
     * Handle the StockLocation "restored" event.
     */
    public function restored(StockLocation $stockLocation): void
    {
        //
    }

    /**
     * Handle the StockLocation "force deleted" event.
     */
    public function forceDeleted(StockLocation $stockLocation): void
    {
        //
    }
}
