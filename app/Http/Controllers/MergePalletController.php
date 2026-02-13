<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\Box;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MergePalletController extends Controller
{
    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'pallet_ids' => 'required|array|min:2',
            'pallet_ids.*' => 'exists:pallets,id',
            'warehouse_location' => 'required|string',
        ]);
    }

    private function generateNewPallet(): Pallet
    {
        $maxNumber = Pallet::query()
            ->where('pallet_number', 'like', 'PLT-%')
            ->pluck('pallet_number')
            ->map(function ($palletNumber) {
                preg_match('/-?(\d+)$/', (string) $palletNumber, $matches);
                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        $nextNumber = $maxNumber + 1;

        $palletNumber = 'PLT-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return Pallet::create([
            'pallet_number' => $palletNumber,
        ]);
    }

    private function collectSourcePallets(array $palletIds): array
    {
        $palletNumbers = [];
        $boxOrigins = [];
        $allBoxes = [];
        $sourcePallets = [];

        foreach ($palletIds as $id) {
            $sourcePallet = Pallet::with('boxes', 'stockLocation', 'items')->find($id);
            if (!$sourcePallet) {
                continue;
            }

            $sourcePallets[] = $sourcePallet;
            $palletNumbers[] = $sourcePallet->pallet_number;
            $activeBoxes = $sourcePallet->boxes
                ->where('is_withdrawn', false)
                ->whereNotIn('expired_status', ['handled', 'expired']);
            $allBoxes = array_merge($allBoxes, $activeBoxes->values()->toArray());

            foreach ($activeBoxes as $box) {
                $boxOrigins[$box->id] = $sourcePallet->pallet_number;
            }
        }

        return [$sourcePallets, $palletNumbers, $allBoxes, $boxOrigins];
    }

    private function attachBoxesAndCreateItems(Pallet $newPallet, array $allBoxes, array $boxOrigins, Request $request): void
    {
        $allBoxIds = array_column($allBoxes, 'id');
        if (empty($allBoxIds)) {
            return;
        }

        $newPallet->boxes()->attach($allBoxIds);

        foreach ($allBoxIds as $boxId) {
            $fromPallet = $boxOrigins[$boxId] ?? null;
            if (!$fromPallet) {
                continue;
            }

            AuditLog::create([
                'type' => 'box_pallet_moved',
                'action' => 'moved',
                'model' => 'Box',
                'model_id' => $boxId,
                'description' => 'Box dipindahkan dari ' . $fromPallet . ' ke ' . $newPallet->pallet_number,
                'old_values' => json_encode(['from_pallet' => $fromPallet]),
                'new_values' => json_encode(['to_pallet' => $newPallet->pallet_number]),
                'user_id' => $request->user()?->id,
                'ip_address' => request()->ip(),
            ]);
        }

        $itemsByPart = [];
        foreach ($allBoxes as $box) {
            $partNumber = $box['part_number'];
            $timestamp = isset($box['created_at']) ? strtotime($box['created_at']) : time();

            if (!isset($itemsByPart[$partNumber])) {
                $itemsByPart[$partNumber] = [
                    'part_number' => $partNumber,
                    'box_quantity' => 0,
                    'pcs_quantity' => 0,
                    'created_at' => $timestamp,
                ];
            }

            $itemsByPart[$partNumber]['box_quantity']++;
            $itemsByPart[$partNumber]['pcs_quantity'] += $box['pcs_quantity'];

            if ($timestamp < $itemsByPart[$partNumber]['created_at']) {
                $itemsByPart[$partNumber]['created_at'] = $timestamp;
            }
        }

        foreach ($itemsByPart as $item) {
            PalletItem::create([
                'pallet_id' => $newPallet->id,
                'part_number' => $item['part_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
                'created_at' => date('Y-m-d H:i:s', $item['created_at']),
                'updated_at' => now(),
            ]);
        }
    }

    private function cleanupSourcePallets(array $sourcePallets, Pallet $newPallet): void
    {
        foreach ($sourcePallets as $sourcePallet) {
            \App\Models\StockInput::where('pallet_id', $sourcePallet->id)
                ->update(['pallet_id' => $newPallet->id]);

            $sourcePallet->boxes()->detach();
            $sourcePallet->items()->delete();

            if ($sourcePallet->stockLocation) {
                $oldLocation = \App\Models\MasterLocation::where('code', $sourcePallet->stockLocation->warehouse_location)->first();
                if ($oldLocation) {
                    $oldLocation->update([
                        'is_occupied' => false,
                        'current_pallet_id' => null
                    ]);
                }
                $sourcePallet->stockLocation->delete();
            }

            $sourcePallet->delete();
        }
    }

    private function assignNewLocation(Request $request, Pallet $newPallet): ?string
    {
        $locationId = $request->input('location_id');
        $locationCode = $request->input('warehouse_location');

        if ($locationId) {
            $masterLocation = \App\Models\MasterLocation::find($locationId);
            if (!$masterLocation) {
                throw new \Exception('Lokasi tujuan tidak ditemukan');
            }

            if ($masterLocation->is_occupied) {
                throw new \Exception('Lokasi tujuan sudah terisi');
            }

            $masterLocation->update([
                'is_occupied' => true,
                'current_pallet_id' => $newPallet->id
            ]);
            $locationCode = $masterLocation->code;
        }

        StockLocation::create([
            'pallet_id' => $newPallet->id,
            'warehouse_location' => $locationCode ?? 'Unknown',
            'stored_at' => now(),
        ]);

        return $locationCode;
    }

    private function createMergeAudit(Pallet $newPallet, array $palletNumbers, Request $request): void
    {
        AuditLog::create([
            'type' => 'pallet_merged',
            'action' => 'merged',
            'model' => 'Pallet',
            'model_id' => $newPallet->id,
            'description' => 'Merge dari ' . count($palletNumbers) . ' pallet: ' . implode(', ', $palletNumbers),
            'user_id' => $request->user()?->id,
            'ip_address' => request()->ip(),
        ]);
    }
    public function index()
    {
        // Get all pallets with active boxes only
        $allPallets = Pallet::whereHas('boxes', function($query) {
                $query->where('is_withdrawn', false);
            })
            ->with(['stockLocation.masterLocation', 'boxes' => function($query) {
                $query->where('is_withdrawn', false); // Only load active boxes
            }])
            ->withCount(['boxes as active_boxes_count' => function($query) {
                $query->where('is_withdrawn', false);
            }])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        // Filter: exclude pallets yang kosong atau lokasi sudah tidak occupied
        $pallets = $allPallets->filter(function($pallet) {
            // Exclude jika tidak ada active boxes
            if ($pallet->boxes->isEmpty()) {
                return false;
            }
            
            // Jika pallet tidak punya stockLocation, include
            if (!$pallet->stockLocation) {
                return true;
            }
            
            // Jika punya stockLocation, check master_location
            $masterLocation = $pallet->stockLocation->masterLocation;
            if (!$masterLocation) {
                return true;
            }
            
            // Exclude jika master_location is_occupied = false (lokasi sudah kosong)
            return $masterLocation->is_occupied === true;
        });

        // Get merge history from audit logs
        $mergeHistory = AuditLog::where('type', 'pallet_merged')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('operator.merge.index', compact('pallets', 'mergeHistory'));
    }

    public function searchPallet(Request $request)
    {
        $code = $request->query('code'); // Detect pallet number only
        
        // Find by Pallet Number - force fresh data
        $pallet = Pallet::where('pallet_number', $code)
            ->with(['boxes' => function($query) {
                $query->where('is_withdrawn', false); // Only load active boxes
            }, 'stockLocation.masterLocation'])
            ->first();

        if (!$pallet) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet tidak ditemukan'
            ], 404);
        }

        // Check if pallet has any active boxes
        if ($pallet->boxes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet tidak memiliki box aktif (semua box sudah withdrawn)'
            ], 404);
        }

        // Check if location is occupied (jika ada stockLocation)
        if ($pallet->stockLocation) {
            $masterLocation = $pallet->stockLocation->masterLocation;
            if ($masterLocation && $masterLocation->is_occupied === false) {
                // Lokasi sudah kosong, jangan boleh merge
                return response()->json([
                    'success' => false,
                    'message' => 'Pallet tidak dapat dimerge - lokasi sudah kosong'
                ], 404);
            }
        }

        // Calculate stats (only active boxes - already filtered in query)
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

    public function store(Request $request)
    {
        $this->validateStoreRequest($request);

        DB::beginTransaction();

        try {
            // 1. Generate New Pallet (Auto-generated Number) - Format: PLT-001, PLT-002, etc.
            // Extract the last number (after last hyphen) and sort numerically - only new format
            $newPallet = $this->generateNewPallet();

            $palletIds = $request->pallet_ids;
            [$sourcePallets, $palletNumbers, $allBoxes, $boxOrigins] = $this->collectSourcePallets($palletIds);

            if (empty($allBoxes)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada box aktif untuk dimerge (semua sudah withdrawn)'
                ], 422);
            }

            // 3. Attach all boxes to new pallet and group items by part_number
            $this->attachBoxesAndCreateItems($newPallet, $allBoxes, $boxOrigins, $request);

            // 4. Clean up source pallets
            $this->cleanupSourcePallets($sourcePallets, $newPallet);

            // 3. Handle New Location Assignment
            $this->assignNewLocation($request, $newPallet);

            // Mark new pallet as merged by adding a special note (store merge info in first stock input)
            $this->createMergeAudit($newPallet, $palletNumbers, $request);

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
