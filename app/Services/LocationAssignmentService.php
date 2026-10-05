<?php

namespace App\Services;

use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LocationAssignmentService
{
    /**
     * Lock and claim a master location for a pallet.
     *
     * The caller must run this method inside a database transaction.
     */
    public function claim(int $masterLocationId, int $palletId): MasterLocation
    {
        $location = MasterLocation::query()
            ->whereKey($masterLocationId)
            ->lockForUpdate()
            ->first();

        if (! $location) {
            throw (new ModelNotFoundException)->setModel(MasterLocation::class, [$masterLocationId]);
        }

        $existingStockLocation = StockLocation::query()
            ->where('master_location_id', $location->id)
            ->where('pallet_id', '!=', $palletId)
            ->lockForUpdate()
            ->first();

        if ($existingStockLocation) {
            $existingPallet = Pallet::withTrashed()
                ->withCount('activeBoxes')
                ->find($existingStockLocation->pallet_id);

            if ($existingPallet && ! $existingPallet->trashed() && $existingPallet->active_boxes_count > 0) {
                if (
                    ! $location->is_occupied
                    || (int) $location->current_pallet_id !== (int) $existingPallet->id
                ) {
                    $location->update([
                        'is_occupied' => true,
                        'current_pallet_id' => $existingPallet->id,
                        'updated_at' => now(),
                    ]);
                }

                throw new \RuntimeException("Lokasi {$location->code} sudah terisi oleh palet aktif {$existingPallet->pallet_number}!");
            }

            $existingStockLocation->delete();
        }

        if (
            $location->is_occupied
            && (int) $location->current_pallet_id !== $palletId
        ) {
            throw new \RuntimeException("Lokasi {$location->code} sudah ditempati pallet lain.");
        }

        $location->update([
            'is_occupied' => true,
            'current_pallet_id' => $palletId,
            'updated_at' => now(),
        ]);

        return $location->refresh();
    }

    /**
     * Claim a location and persist the pallet's stock location.
     *
     * The caller must run this method inside a database transaction.
     */
    public function assign(int $masterLocationId, Pallet $pallet, ?\DateTimeInterface $storedAt = null): StockLocation
    {
        $location = $this->claim($masterLocationId, (int) $pallet->id);

        return StockLocation::updateOrCreate(
            ['pallet_id' => $pallet->id],
            [
                'master_location_id' => $location->id,
                'warehouse_location' => $location->code,
                'stored_at' => $storedAt ?? now(),
            ]
        );
    }
}
