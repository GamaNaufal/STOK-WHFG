<?php

namespace App\Services;

use App\Models\MasterLocation;
use App\Models\Pallet;
use Illuminate\Support\Facades\DB;

class LocationManagementService
{
    /**
     * Auto vacate empty pallets
     * Dijalankan setelah setiap withdrawal/picking untuk update status lokasi
     */
    public static function autoVacateEmptyPallets()
    {
        // Get all occupied locations
        $occupiedLocations = MasterLocation::where('is_occupied', true)
            ->with('currentPallet.items')
            ->get();

        foreach ($occupiedLocations as $location) {
            $location->autoVacateIfEmpty();
        }
    }

    /**
     * Update single location
     */
    public static function updateLocationStatus(Pallet $pallet)
    {
        $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->first();
        if ($masterLocation) {
            $masterLocation->autoVacateIfEmpty();
        }
    }

    /**
     * Occupy location dengan pallet
     */
    public static function occupyLocation(MasterLocation $location, Pallet $pallet)
    {
        DB::beginTransaction();
        try {
            // Vacate previous pallet dari location lain jika ada
            MasterLocation::where('current_pallet_id', $pallet->id)
                ->where('id', '!=', $location->id)
                ->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                ]);

            // Occupy new location
            $location->occupyWithPallet($pallet->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Vacate location
     */
    public static function vacateLocation(MasterLocation $location)
    {
        $location->vacate();
    }

    /**
     * Get available locations count
     */
    public static function getAvailableLocationsCount()
    {
        return MasterLocation::available()->count();
    }

    /**
     * Get occupied locations count
     */
    public static function getOccupiedLocationsCount()
    {
        return MasterLocation::occupied()->count();
    }

    /**
     * Get location utilization percentage
     */
    public static function getUtilizationPercentage()
    {
        $total = MasterLocation::count();
        if ($total === 0) {
            return 0;
        }

        $occupied = self::getOccupiedLocationsCount();
        return round(($occupied / $total) * 100, 2);
    }
}
