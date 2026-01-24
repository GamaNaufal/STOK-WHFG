<?php

namespace App\Observers;

use App\Models\StockLocation;

class StockLocationObserver
{
    /**
     * Handle the StockLocation "created" event.
     */
    public function created(StockLocation $stockLocation): void
    {
        //
    }

    /**
     * Handle the StockLocation "updated" event.
     */
    public function updated(StockLocation $stockLocation): void
    {
        //
    }

    /**
     * Handle the StockLocation "deleted" event.
     */
    public function deleted(StockLocation $stockLocation): void
    {
        // Cari MasterLocation berdasarkan kode
        $masterLocation = \App\Models\MasterLocation::where('code', $stockLocation->warehouse_location)->first();

        if ($masterLocation) {
             // Cek apakah lokasi ini masih dipakai oleh pallet lain? (Seharusnya 1:1, tapi untuk safety)
             // Jika relasinya 1 lokasi 1 pallet, maka langsung set false.
             if ($masterLocation->current_pallet_id == $stockLocation->pallet_id) {
                 $masterLocation->update([
                     'is_occupied' => false,
                     'current_pallet_id' => null
                 ]);
             }
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
