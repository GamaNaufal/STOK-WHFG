<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Services\LocationAssignmentService;
use App\Services\PalletDeliveryLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovePalletController extends Controller
{
    public function __construct(
        private readonly LocationAssignmentService $locationAssignmentService,
        private readonly PalletDeliveryLockService $palletDeliveryLockService
    ) {
    }

    private function activeBoxes(Pallet $pallet)
    {
        return $pallet->boxes()
            ->where('boxes.is_withdrawn', false)
            ->where(function ($query) {
                \App\Models\Box::applyUsableExpiryFilter($query, 'boxes');
            });
    }

    private function validatePalletCanMove(Pallet $pallet, bool $lockRows = false): array
    {
        $stockLocation = $pallet->stockLocation;
        $masterLocation = $stockLocation?->masterLocation;

        if (! $stockLocation || ! $masterLocation) {
            throw new \RuntimeException('Pallet tidak memiliki lokasi master yang valid.');
        }

        if (! $masterLocation->is_occupied || (int) $masterLocation->current_pallet_id !== (int) $pallet->id) {
            throw new \RuntimeException('Lokasi pallet tidak lagi berstatus terisi. Muat ulang data pallet.');
        }

        $activeBoxQuery = $this->activeBoxes($pallet);
        $activeBoxes = ($lockRows ? $activeBoxQuery->lockForUpdate() : $activeBoxQuery)->get();
        if ($activeBoxes->isEmpty()) {
            throw new \RuntimeException('Pallet tidak memiliki box aktif dan tidak dapat dipindahkan.');
        }

        $lockedBox = $this->palletDeliveryLockService->blockingBoxForPallets([(int) $pallet->id], $lockRows);
        if ($lockedBox) {
            throw new \RuntimeException(
                "Box {$lockedBox->box_number} sedang digunakan dalam proses delivery aktif dan pallet tidak dapat dipindahkan."
            );
        }

        return [$stockLocation, $masterLocation, $activeBoxes];
    }

    public function index()
    {
        $activeBoxFilter = function ($query) {
            $query->where('boxes.is_withdrawn', false)
                ->where(function ($query) {
                    \App\Models\Box::applyUsableExpiryFilter($query, 'boxes');
                });
        };

        $lockedStatuses = PalletDeliveryLockService::BLOCKING_SESSION_STATUSES;
        $pallets = Pallet::query()
            ->whereHas('stockLocation.masterLocation', function ($query) {
                $query->where('is_occupied', true);
            })
            ->whereHas('boxes', $activeBoxFilter)
            ->whereDoesntHave('boxes', function ($query) use ($lockedStatuses) {
                $query->whereHas('deliveryPickItems', function ($query) use ($lockedStatuses) {
                    $query->whereHas('session', function ($query) use ($lockedStatuses) {
                        $query->whereIn('status', $lockedStatuses);
                    });
                });
            })
            ->with(['stockLocation', 'boxes' => $activeBoxFilter])
            ->withCount(['boxes' => $activeBoxFilter])
            ->orderBy('pallet_number')
            ->limit(100)
            ->get();
        $availableLocations = MasterLocation::query()
            ->where('is_occupied', false)
            ->orderBy('code')
            ->limit(200)
            ->get(['id', 'code']);

        $moveHistory = AuditLog::where('type', 'pallet_location_moved')
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        return view('operator.move-pallet.index', compact('pallets', 'availableLocations', 'moveHistory'));
    }

    public function searchPallet(Request $request)
    {
        $code = trim((string) $request->query('code'));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Nomor pallet wajib diisi.'], 422);
        }

        $pallet = Pallet::with(['stockLocation.masterLocation'])
            ->where('pallet_number', $code)
            ->first();

        if (! $pallet) {
            return response()->json(['success' => false, 'message' => 'Pallet tidak ditemukan.'], 404);
        }

        try {
            [$stockLocation, $masterLocation, $activeBoxes] = $this->validatePalletCanMove($pallet);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pallet' => [
                'id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'location' => $stockLocation->warehouse_location,
                'location_id' => $masterLocation->id,
                'total_box' => $activeBoxes->count(),
                'total_pcs' => $activeBoxes->sum('pcs_quantity'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'location_id' => ['required', 'integer', 'exists:master_locations,id,scope,domestic'],
        ]);

        try {
            $result = DB::transaction(function () use ($data, $request) {
                $pallet = Pallet::withTrashed()
                    ->with(['stockLocation.masterLocation'])
                    ->whereKey($data['pallet_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $pallet || $pallet->trashed()) {
                    throw new \RuntimeException('Pallet tidak ditemukan atau sudah tidak aktif.');
                }

                [$stockLocation, $sourceLocation, $activeBoxes] = $this->validatePalletCanMove($pallet, true);
                $targetLocation = MasterLocation::whereKey($data['location_id'])->first();

                if (! $targetLocation) {
                    throw new \RuntimeException('Lokasi tujuan tidak ditemukan di Master Location.');
                }

                if ((int) $targetLocation->id === (int) $sourceLocation->id) {
                    throw new \RuntimeException('Lokasi tujuan sama dengan lokasi pallet saat ini.');
                }

                // Lock both location rows in a stable order before claiming the target.
                $locationIds = [(int) $sourceLocation->id, (int) $targetLocation->id];
                sort($locationIds);
                foreach ($locationIds as $locationId) {
                    MasterLocation::whereKey($locationId)->lockForUpdate()->first();
                }

                $targetLocation = MasterLocation::whereKey($targetLocation->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $sourceLocation->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                    'updated_at' => now(),
                ]);

                $this->locationAssignmentService->assign((int) $targetLocation->id, $pallet, $stockLocation->stored_at);

                StockLocation::where('pallet_id', $pallet->id)
                    ->update([
                        'master_location_id' => $targetLocation->id,
                        'warehouse_location' => $targetLocation->code,
                        'stored_at' => now(),
                        'updated_at' => now(),
                    ]);

                AuditLog::create([
                    'type' => 'pallet_location_moved',
                    'action' => 'moved',
                    'model' => 'Pallet',
                    'model_id' => $pallet->id,
                    'description' => "Pallet {$pallet->pallet_number} dipindahkan dari {$sourceLocation->code} ke {$targetLocation->code}",
                    'old_values' => json_encode([
                        'location_id' => $sourceLocation->id,
                        'location' => $sourceLocation->code,
                        'box_count' => $activeBoxes->count(),
                        'pcs_quantity' => $activeBoxes->sum('pcs_quantity'),
                    ]),
                    'new_values' => json_encode([
                        'pallet_number' => $pallet->pallet_number,
                        'location_id' => $targetLocation->id,
                        'location' => $targetLocation->code,
                    ]),
                    'user_id' => $request->user()?->id,
                    'ip_address' => $request->ip(),
                ]);

                return [
                    'pallet_number' => $pallet->pallet_number,
                    'from' => $sourceLocation->code,
                    'to' => $targetLocation->code,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Pallet {$result['pallet_number']} berhasil dipindahkan dari {$result['from']} ke {$result['to']}.",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan pallet. Silakan coba lagi atau hubungi admin.',
            ], 500);
        }
    }
}
