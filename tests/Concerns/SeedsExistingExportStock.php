<?php

namespace Tests\Concerns;

use App\Models\ExportMasterLocation;
use App\Models\ExportStockMovement;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeedsExistingExportStock
{
    /** Seed inventory already spread across locations before compulsory placement. */
    private function storeExistingExportBoxes(Collection $boxes, User $actor, ExportMasterLocation|ExportStockPallet $destination): ExportStockPallet
    {
        return DB::transaction(function () use ($boxes, $actor, $destination) {
            $pallet = $destination instanceof ExportStockPallet ? $destination : ExportStockPallet::create([
                'master_location_id' => $destination->id, 'initial_location_id' => $destination->id,
                'location_code' => $destination->code, 'created_by' => $actor->id,
            ]);
            $pallet->location->update(['is_occupied' => true]);
            foreach ($boxes as $box) {
                $box->refresh();
                ExportStockMovement::create(['export_stock_box_id' => $box->id, 'request_token' => (string) Str::uuid(),
                    'request_hash' => hash('sha256', 'existing-stock-fixture-'.$box->id),
                    'to_pallet_id' => $pallet->id, 'to_location_code' => $pallet->location_code,
                    'placed_by' => $actor->id, 'placed_at' => now()]);
                $box->update(['state' => 'stored', 'current_pallet_id' => $pallet->id, 'revision' => $box->revision + 1,
                    'stored_at' => now(), 'stored_by' => $actor->id]);
            }

            return $pallet;
        });
    }
}
