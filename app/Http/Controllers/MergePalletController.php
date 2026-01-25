<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\Box;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MergePalletController extends Controller
{
    public function index()
    {
        $pallets = Pallet::has('boxes')
            ->with(['stockLocation', 'boxes'])
            ->withCount('boxes')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return view('warehouse.merge.index', compact('pallets'));
    }

    public function searchPallet(Request $request)
    {
        $code = $request->query('code'); // Detect pallet number only
        
        // Find by Pallet Number
        $pallet = Pallet::where('pallet_number', $code)->with(['boxes', 'stockLocation'])->first();

        if ($pallet) {
            // Calculate stats
            $totalBox = $pallet->boxes->count();
            $totalPcs = $pallet->boxes->sum('pcs_quantity');
            $location = $pallet->stockLocation->warehouse_location ?? 'Not Stored';

            return response()->json([
                'success' => true,
                'pallet' => [
                    'id' => $pallet->id,
                    'pallet_number' => $pallet->pallet_number,
                    'total_box' => $totalBox,
                    'total_pcs' => $totalPcs,
                    'location' => $location,
                    'items' => $pallet->boxes->map(function($box) {
                        return [
                            'box_number' => $box->box_number,
                            'part_number' => $box->part_number,
                            'pcs_quantity' => $box->pcs_quantity
                        ];
                    })
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Pallet tidak ditemukan'
        ], 404);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pallet_ids' => 'required|array|min:2',
            'pallet_ids.*' => 'exists:pallets,id',
            'warehouse_location' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            // 1. Generate New Pallet (Auto-generated Number) - Format: PLT-001, PLT-002, etc.
            // Extract the last number (after last hyphen) and sort numerically - only new format
            $lastPallet = Pallet::where('pallet_number', 'like', 'PLT-0%')
                ->orderByRaw("CAST(SUBSTRING_INDEX(pallet_number, '-', -1) AS UNSIGNED) DESC")
                ->first();

            $nextNumber = 1;
            if ($lastPallet) {
                // Extract only the last number (after the last hyphen)
                preg_match('/-?(\d+)$/', $lastPallet->pallet_number, $matches);
                $lastNumber = isset($matches[1]) ? (int) $matches[1] : 1;
                $nextNumber = $lastNumber + 1;
            }
            $palletNumber = 'PLT-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $newPallet = Pallet::create([
                'pallet_number' => $palletNumber,
            ]);

            $palletIds = $request->pallet_ids;

            // 2. Iterate Selected Pallets
            foreach ($palletIds as $id) {
                $sourcePallet = Pallet::with('boxes', 'stockLocation', 'items')->find($id);
                
                if (!$sourcePallet) continue;

                $boxIds = $sourcePallet->boxes->pluck('id')->toArray();
                
                if (!empty($boxIds)) {
                     // Attach boxes to NEW pallet
                     $newPallet->boxes()->attach($boxIds);

                     // Handle PalletItems (History + Timestamps)
                     // Instead of creating new fresh items, let's replicate them with original timestamps FROM THE BOX
                     foreach ($sourcePallet->boxes as $box) {
                        // We use the BOX created_at as the source of truth for FIFO
                        $timestamp = $box->created_at ?? now();
                        
                        PalletItem::create([
                            'pallet_id' => $newPallet->id,
                            'part_number' => $box->part_number,
                            'box_quantity' => 1,
                            'pcs_quantity' => $box->pcs_quantity,
                            'created_at' => $timestamp, 
                            'updated_at' => now(), 
                        ]);
                    }

                    // Detach from OLD pallet
                    $sourcePallet->boxes()->detach();
                }

                // Delete source items history
                $sourcePallet->items()->delete();

                // Free Location if exists
                if ($sourcePallet->stockLocation) {
                    $sourcePallet->stockLocation->delete(); 
                    // Observer handles MasterLocation update
                }

                // Delete OLD Pallet
                $sourcePallet->delete();
            }

            // 3. Handle New Location Assignment
            $locationId = $request->input('location_id');
            $locationCode = $request->input('warehouse_location');

            if ($locationId) {
                $masterLocation = \App\Models\MasterLocation::find($locationId);
                if ($masterLocation && !$masterLocation->is_occupied) {
                     $masterLocation->update([
                         'is_occupied' => true,
                         'current_pallet_id' => $newPallet->id
                     ]);
                     $locationCode = $masterLocation->code;
                }
            }

            StockLocation::create([
                'pallet_id' => $newPallet->id,
                'warehouse_location' => $locationCode ?? 'Unknown',
                'stored_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Pallet berhasil digabungkan menjadi Pallet Baru: ' . $newPallet->pallet_number,
                'new_pallet_number' => $newPallet->pallet_number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menggabungkan: ' . $e->getMessage()
            ], 500);
        }
    }
}
