<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Services\LocationAssignmentService;
use App\Services\PalletNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MergePalletController extends Controller
{
    public function __construct(
        private readonly LocationAssignmentService $locationAssignmentService,
        private readonly PalletNumberService $palletNumberService
    ) {
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) ($e->getCode() ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }

    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'pallet_ids' => 'required|array|min:2',
            'pallet_ids.*' => 'exists:pallets,id',
            'location_id' => 'nullable|integer|exists:master_locations,id',
            'warehouse_location' => 'nullable|string',
        ]);
    }

    private function generateNewPallet(): Pallet
    {
        return $this->palletNumberService->create();
    }

    private function collectSourcePallets(array $palletIds): array
    {
        $normalizedIds = collect($palletIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedIds)) {
            return [[], [], [], []];
        }

        $palletMap = Pallet::with(['stockLocation', 'items'])
            ->whereIn('id', $normalizedIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $palletNumbers = [];
        $boxOrigins = [];
        $allBoxes = [];
        $sourcePallets = [];

        foreach ($normalizedIds as $id) {
            $sourcePallet = $palletMap->get($id);
            if (! $sourcePallet) {
                continue;
            }

            $sourcePallets[] = $sourcePallet;
            $palletNumbers[] = $sourcePallet->pallet_number;
            $activeBoxes = $sourcePallet->boxes()
                ->whereNull('boxes.deleted_at')
                ->where('boxes.is_withdrawn', false)
                ->where(function ($query) {
                    $query->whereNull('boxes.expired_status')
                        ->orWhereNotIn('boxes.expired_status', ['handled', 'expired']);
                })
                ->lockForUpdate()
                ->get();

            $assignedBox = $activeBoxes->first(
                fn ($box) => $box->assigned_delivery_order_id !== null
            );
            if ($assignedBox) {
                throw new \RuntimeException(
                    "Box {$assignedBox->box_number} sudah di-assign ke delivery dan pallet tidak dapat dimerge."
                );
            }

            $allBoxes = array_merge($allBoxes, $activeBoxes->values()->toArray());

            foreach ($activeBoxes as $box) {
                $boxOrigins[$box->id] = $sourcePallet->pallet_number;
            }
        }

        $allBoxIds = collect($allBoxes)->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($allBoxIds->isNotEmpty()) {
            $pickLockedBoxId = DB::table('delivery_pick_items')
                ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
                ->whereIn('delivery_pick_items.box_id', $allBoxIds->all())
                ->whereIn('delivery_pick_sessions.status', ['pending', 'scanning', 'blocked', 'approved'])
                ->lockForUpdate()
                ->value('delivery_pick_items.box_id');

            if ($pickLockedBoxId) {
                $lockedBox = collect($allBoxes)->firstWhere('id', (int) $pickLockedBoxId);
                $boxNumber = $lockedBox['box_number'] ?? $pickLockedBoxId;
                throw new \RuntimeException(
                    "Box {$boxNumber} sedang digunakan dalam sesi picking dan pallet tidak dapat dimerge."
                );
            }
        }

        return [$sourcePallets, $palletNumbers, $allBoxes, $boxOrigins];
    }

    private function normalizePartNumber($partNumber): ?string
    {
        $normalized = strtoupper(trim((string) $partNumber));

        return $normalized !== '' ? $normalized : null;
    }

    private function attachBoxesAndCreateItems(Pallet $newPallet, array $allBoxes, array $boxOrigins, Request $request, array $sourcePallets): void
    {
        $allBoxIds = array_values(array_unique(array_map('intval', array_column($allBoxes, 'id'))));
        if (empty($allBoxIds)) {
            return;
        }

        $newPallet->boxes()->attach($allBoxIds);

        foreach ($allBoxIds as $boxId) {
            $fromPallet = $boxOrigins[$boxId] ?? null;
            if (! $fromPallet) {
                continue;
            }

            AuditLog::create([
                'type' => 'box_pallet_moved',
                'action' => 'moved',
                'model' => 'Box',
                'model_id' => $boxId,
                'description' => 'Box dipindahkan dari '.$fromPallet.' ke '.$newPallet->pallet_number,
                'old_values' => json_encode(['from_pallet' => $fromPallet]),
                'new_values' => json_encode(['to_pallet' => $newPallet->pallet_number]),
                'user_id' => $request->user()?->id,
                'ip_address' => request()->ip(),
            ]);
        }

        $itemsByPart = [];
        $uniqueBoxes = collect($allBoxes)->unique('id');

        foreach ($uniqueBoxes as $box) {
            $partNumber = $this->normalizePartNumber($box['part_number'] ?? null);
            if ($partNumber === null) {
                continue;
            }

            if (! isset($itemsByPart[$partNumber])) {
                $itemsByPart[$partNumber] = [
                    'part_number' => $partNumber,
                    'box_quantity' => 0,
                    'pcs_quantity' => 0,
                    'created_at' => time(),
                ];
            }

            $itemsByPart[$partNumber]['box_quantity']++;
            $itemsByPart[$partNumber]['pcs_quantity'] += (int) ($box['pcs_quantity'] ?? 0);

            $timestamp = ! empty($box['created_at'])
                ? strtotime((string) $box['created_at'])
                : time();
            if ($timestamp < $itemsByPart[$partNumber]['created_at']) {
                $itemsByPart[$partNumber]['created_at'] = $timestamp;
            }
        }

        $now = now();
        $payload = [];

        foreach ($itemsByPart as $item) {
            $payload[] = [
                'pallet_id' => $newPallet->id,
                'part_number' => $item['part_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
                'created_at' => date('Y-m-d H:i:s', $item['created_at']),
                'updated_at' => $now,
            ];
        }

        if (! empty($payload)) {
            DB::table('pallet_items')->upsert(
                $payload,
                ['pallet_id', 'part_number'],
                ['box_quantity', 'pcs_quantity', 'created_at', 'updated_at']
            );
        }
    }

    /**
     * Collect master_location_ids owned by source pallets so we can
     * distinguish "target = source location" from "target = third-party".
     *
     * @return array<int> master_location_id values owned by source pallets
     */
    private function collectSourceLocationIds(array $sourcePallets): array
    {
        $ids = [];
        foreach ($sourcePallets as $sourcePallet) {
            if ($sourcePallet->stockLocation && $sourcePallet->stockLocation->master_location_id) {
                $ids[] = (int) $sourcePallet->stockLocation->master_location_id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function cleanupSourcePallets(array $sourcePallets, Pallet $newPallet, array $movedBoxes): void
    {
        $movedBoxIds = collect($movedBoxes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $locationCodes = collect($sourcePallets)
            ->map(fn ($sourcePallet) => $sourcePallet?->stockLocation?->warehouse_location)
            ->filter()
            ->unique()
            ->values();

        $oldLocationsByCode = $locationCodes->isEmpty()
            ? collect()
            : MasterLocation::whereIn('code', $locationCodes)
                ->get()
                ->keyBy('code');

        foreach ($sourcePallets as $sourcePallet) {
            StockInput::where('pallet_id', $sourcePallet->id)
                ->update(['pallet_id' => $newPallet->id]);

            $sourcePallet->boxes()->detach($movedBoxIds);
            $sourcePallet->items()->delete();

            if ($sourcePallet->stockLocation) {
                if ($sourcePallet->stockLocation->master_location_id) {
                    MasterLocation::where('id', $sourcePallet->stockLocation->master_location_id)
                        ->update([
                            'is_occupied' => false,
                            'current_pallet_id' => null,
                        ]);
                }

                $oldLocation = $oldLocationsByCode->get($sourcePallet->stockLocation->warehouse_location);
                if ($oldLocation) {
                    $oldLocation->update([
                        'is_occupied' => false,
                        'current_pallet_id' => null,
                    ]);
                }
                $sourcePallet->stockLocation->delete();
            }

            MasterLocation::where('current_pallet_id', $sourcePallet->id)
                ->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                ]);

            $sourcePallet->delete();
        }
    }

    /**
     * Resolve and lock the target master location.
     *
     * Validates that the location exists in master_locations and that it is
     * either free, or belongs to one of the source pallets being merged.
     * Locations held by unrelated pallets are rejected.
     *
     * @param  array<int>  $sourceLocationIds  master_location_ids owned by source pallets
     */
    private function resolveTargetLocation(Request $request, array $sourceLocationIds): MasterLocation
    {
        $locationId = $request->input('location_id');
        $masterLocation = ! empty($locationId) ? MasterLocation::find($locationId) : null;

        if (! $masterLocation && $request->filled('warehouse_location')) {
            $locationCode = trim((string) $request->input('warehouse_location'));
            $masterLocation = MasterLocation::where('code', $locationCode)
                ->orWhereRaw('LOWER(code) = ?', [strtolower($locationCode)])
                ->first();
        }

        if (! $masterLocation) {
            throw new \RuntimeException('Lokasi tujuan tidak ditemukan di Master Location.');
        }

        // Lock the row for the duration of the transaction
        $masterLocation = MasterLocation::whereKey($masterLocation->id)
            ->lockForUpdate()
            ->first();

        // Allow if: unoccupied, OR occupied by one of the source pallets
        $isSourceLocation = in_array((int) $masterLocation->id, $sourceLocationIds, true);

        if ($masterLocation->is_occupied && ! $isSourceLocation) {
            throw new \RuntimeException(
                "Lokasi {$masterLocation->code} sudah digunakan oleh pallet lain. Pilih lokasi yang kosong atau lokasi salah satu pallet sumber."
            );
        }

        return $masterLocation;
    }

    /**
     * Assign the new pallet to the target location.
     *
     * Uses updateOrCreate for the stock_location row to safely handle the case
     * where the target location was previously owned by a source pallet (whose
     * stock_location row was already deleted in cleanupSourcePallets).
     *
     * Also cleans up any stale stock_location rows that may reference the same
     * master_location_id from previous failed attempts.
     */
    private function assignNewLocation(MasterLocation $masterLocation, Pallet $newPallet): string
    {
        return $this->locationAssignmentService
            ->assign((int) $masterLocation->id, $newPallet)
            ->warehouse_location;
    }

    private function createMergeAudit(Pallet $newPallet, array $palletNumbers, Request $request): void
    {
        AuditLog::create([
            'type' => 'pallet_merged',
            'action' => 'merged',
            'model' => 'Pallet',
            'model_id' => $newPallet->id,
            'description' => 'Merge dari '.count($palletNumbers).' pallet: '.implode(', ', $palletNumbers),
            'user_id' => $request->user()?->id,
            'ip_address' => request()->ip(),
        ]);
    }

    public function index()
    {
        // Get all pallets with active boxes only
        $allPallets = Pallet::whereHas('boxes', function ($query) {
            $query->where('is_withdrawn', false)
                ->where(function ($q) {
                    $q->whereNull('expired_status')->orWhereNotIn('expired_status', ['handled', 'expired']);
                });
        })
            ->with(['stockLocation.masterLocation', 'boxes' => function ($query) {
                $query->where('is_withdrawn', false)
                    ->where(function ($q) {
                        $q->whereNull('expired_status')->orWhereNotIn('expired_status', ['handled', 'expired']);
                    }); // Only load active boxes
            }])
            ->withCount(['boxes as active_boxes_count' => function ($query) {
                $query->where('is_withdrawn', false)
                    ->where(function ($q) {
                        $q->whereNull('expired_status')->orWhereNotIn('expired_status', ['handled', 'expired']);
                    });
            }])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        // Filter: exclude pallets yang kosong atau lokasi sudah tidak occupied
        $pallets = $allPallets->filter(function ($pallet) {
            // Exclude jika tidak ada active boxes
            if ($pallet->boxes->isEmpty()) {
                return false;
            }

            // Jika pallet tidak punya stockLocation, include
            if (! $pallet->stockLocation) {
                return true;
            }

            // Jika punya stockLocation, check master_location
            $masterLocation = $pallet->stockLocation->masterLocation;
            if (! $masterLocation) {
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
            ->with(['boxes' => function ($query) {
                $query->where('is_withdrawn', false)
                    ->where(function ($q) {
                        $q->whereNull('expired_status')->orWhereNotIn('expired_status', ['handled', 'expired']);
                    }); // Only load active boxes
            }, 'stockLocation.masterLocation'])
            ->first();

        if (! $pallet) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet tidak ditemukan',
            ], 404);
        }

        // Check if pallet has any active boxes
        if ($pallet->boxes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet tidak memiliki box aktif (semua box sudah withdrawn)',
            ], 404);
        }

        // Check if location is occupied (jika ada stockLocation)
        if ($pallet->stockLocation) {
            $masterLocation = $pallet->stockLocation->masterLocation;
            if ($masterLocation && $masterLocation->is_occupied === false) {
                // Lokasi sudah kosong, jangan boleh merge
                return response()->json([
                    'success' => false,
                    'message' => 'Pallet tidak dapat dimerge - lokasi sudah kosong',
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
                'location_id' => $pallet->stockLocation?->master_location_id,
                'items' => $pallet->boxes->map(function ($box) {
                    return [
                        'box_number' => $box->box_number,
                        'part_number' => $box->part_number,
                        'pcs_quantity' => $box->pcs_quantity,
                    ];
                }),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validateStoreRequest($request);

        DB::beginTransaction();

        try {
            $palletIds = $request->pallet_ids;

            // 1. Lock and collect source pallets, boxes, and validate eligibility
            [$sourcePallets, $palletNumbers, $allBoxes, $boxOrigins] = $this->collectSourcePallets($palletIds);

            if (count($sourcePallets) < 2) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Pallet sumber tidak valid atau sudah berubah. Pilih ulang pallet untuk merge.',
                ], 422);
            }

            if (empty($allBoxes)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada box aktif untuk dimerge (semua sudah withdrawn)',
                ], 422);
            }

            // 2. Resolve and validate target location BEFORE any mutations
            $sourceLocationIds = $this->collectSourceLocationIds($sourcePallets);
            $targetMasterLocation = $this->resolveTargetLocation($request, $sourceLocationIds);

            // 3. Generate new pallet
            $newPallet = $this->generateNewPallet();

            // 4. Attach all boxes to new pallet and build pallet_items
            $this->attachBoxesAndCreateItems($newPallet, $allBoxes, $boxOrigins, $request, $sourcePallets);

            // 5. Clean up source pallets FIRST (deletes their stock_locations → frees master_location_id)
            $this->cleanupSourcePallets($sourcePallets, $newPallet, $allBoxes);

            // 6. NOW assign the new pallet to the target location (safe: source rows already gone)
            $this->assignNewLocation($targetMasterLocation, $newPallet);

            // 7. Create audit log
            $this->createMergeAudit($newPallet, $palletNumbers, $request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pallet berhasil digabungkan menjadi Pallet Baru: '.$newPallet->pallet_number,
                'new_pallet_number' => $newPallet->pallet_number,
            ]);

        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan pallet. Silakan coba lagi atau hubungi admin.',
            ], 500);
        }
    }
}
