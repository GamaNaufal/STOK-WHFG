<?php

namespace App\Http\Controllers;

use App\Exports\StockViewByPalletExport;
use App\Exports\StockViewByPartExport;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StockViewController extends Controller
{
    private function canDeleteStock(?object $user): bool
    {
        return $user && in_array($user->role, ['admin_warehouse', 'supervisi', 'admin'], true);
    }

    private function buildStockItems(?string $search = null)
    {
        $palletQuery = Pallet::with(['stockLocation', 'items', 'boxes'])
            ->whereHas('stockLocation', function ($q) {
                $q->where('warehouse_location', '!=', 'Unknown');
            });

        $items = collect();

        $palletQuery->chunkById(200, function ($pallets) use (&$items, $search) {
            foreach ($pallets as $pallet) {
                $location = $pallet->stockLocation->warehouse_location ?? 'Unknown';

                // Prefer active boxes as source of truth
                $activeBoxes = $pallet->boxes
                    ->where('is_withdrawn', false)
                    ->whereNotIn('expired_status', ['handled', 'expired']);

                if ($activeBoxes->isNotEmpty()) {
                    if ($search) {
                        $activeBoxes = $activeBoxes->filter(function ($box) use ($search, $pallet) {
                            return stripos($box->part_number, $search) !== false
                                || stripos($pallet->pallet_number, $search) !== false;
                        });
                    }

                    foreach ($activeBoxes as $box) {
                        $items->push([
                            'box_id' => $box->id,
                            'pallet_id' => $pallet->id,
                            'pallet_number' => $pallet->pallet_number,
                            'location' => $location,
                            'part_number' => $box->part_number,
                            'box_number' => $box->box_number,
                            'box_quantity' => 1,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                            'created_at' => $box->created_at,
                            'is_not_full' => (bool) $box->is_not_full,
                            'not_full_reason' => $box->not_full_reason,
                        ]);
                    }
                } else {
                    // Fallback to pallet items (legacy / no box data)
                    $legacyItems = $pallet->items->filter(function ($item) {
                        return $item->pcs_quantity > 0 || $item->box_quantity > 0;
                    });

                    if ($search) {
                        $legacyItems = $legacyItems->filter(function ($item) use ($search, $pallet) {
                            return stripos($item->part_number, $search) !== false
                                || stripos($pallet->pallet_number, $search) !== false;
                        });
                    }

                    foreach ($legacyItems as $item) {
                        $items->push([
                            'box_id' => null,
                            'pallet_id' => $pallet->id,
                            'pallet_number' => $pallet->pallet_number,
                            'location' => $location,
                            'part_number' => $item->part_number,
                            'box_number' => null,
                            'box_quantity' => (int) $item->box_quantity,
                            'pcs_quantity' => (int) $item->pcs_quantity,
                            'created_at' => $item->created_at,
                            'is_not_full' => false,
                            'not_full_reason' => null,
                        ]);
                    }
                }
            }
        });

        return $items->sortBy('created_at');
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $viewMode = $request->input('view_mode', 'part'); // Default view by part

        $items = $this->buildStockItems($search);
        if ($viewMode === 'not_full') {
            $items = $items->filter(fn ($item) => !empty($item['is_not_full']))->values();
        }
        // Calculate total pallets from the filtered items
        $totalPallets = $items->pluck('pallet_id')->unique()->count();

        // Data for "By Part" view
        $groupedByPart = collect();
        if ($viewMode === 'part') {
            $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');

                return [
                    'part_number' => $itemGroup->first()['part_number'],
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup->sortBy('created_at'),
                ];
            })->sortBy(function ($row) {
                return mb_strtolower((string) ($row['part_number'] ?? ''));
            }, SORT_STRING);
        }

        // Data for "By Pallet" view
        $groupedByPallet = collect();
        if ($viewMode === 'pallet') {
            $palletIds = $items->pluck('pallet_id')->unique()->values();
            $mergedPalletIds = AuditLog::where('model', 'Pallet')
                ->where('type', 'pallet_merged')
                ->whereIn('model_id', $palletIds)
                ->pluck('model_id')
                ->unique()
                ->toArray();

            $groupedByPallet = $items->groupBy('pallet_id')->map(function ($itemGroup) use ($mergedPalletIds) {
                $firstItem = $itemGroup->first();
                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');
                $isMerged = in_array($firstItem['pallet_id'], $mergedPalletIds, true);

                return [
                    'pallet_id' => $firstItem['pallet_id'],
                    'pallet_number' => $firstItem['pallet_number'],
                    'location' => $firstItem['location'] ?? 'Unknown',
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup,
                    'is_merged' => $isMerged,
                ];
            });
        }

        $notFullBoxes = collect();
        if ($viewMode === 'not_full') {
            $notFullBoxes = $items->map(function ($item) {
                return [
                    'box_id' => $item['box_id'] ?? null,
                    'pallet_id' => $item['pallet_id'],
                    'box_number' => $item['box_number'] ?? '-',
                    'part_number' => $item['part_number'],
                    'pcs_quantity' => $item['pcs_quantity'],
                    'location' => $item['location'] ?? 'Unknown',
                    'pallet_number' => $item['pallet_number'],
                    'created_at' => $item['created_at'],
                    'reason' => $item['not_full_reason'] ?? null,
                ];
            })->values();
        }
        
        // Calculate totals for summary cards regardless of view mode
        // We use the raw items collection to calculate these compatible with both views
        $summaryTotalBox = $items->sum('box_quantity');
        $summaryTotalPcs = $items->sum('pcs_quantity');
        $summaryTotalParts = $items->pluck('part_number')->unique()->count();
        $masterPartNumbers = PartSetting::query()
            ->orderBy('part_number')
            ->pluck('part_number')
            ->values();

        return view('shared.stock-view.index', compact(
            'groupedByPart', 
            'groupedByPallet',
            'notFullBoxes',
            'search', 
            'viewMode', 
            'totalPallets',
            'summaryTotalBox',
            'summaryTotalPcs',
            'summaryTotalParts',
            'masterPartNumbers'
        ));
    }

    // API: Get stock grouped by part number
    public function apiGetStockByPart()
    {
        $items = $this->buildStockItems();

        $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
            $totalPcs = $itemGroup->sum('pcs_quantity');
            $totalBox = $itemGroup->sum('box_quantity');

            return [
                'part_number' => $itemGroup->first()['part_number'],
                'total_box' => (int) $totalBox,
                'total_pcs' => $totalPcs,
            ];
        })->sortBy(function ($row) {
            return mb_strtolower((string) ($row['part_number'] ?? ''));
        }, SORT_STRING)->values();

        return response()->json($groupedByPart);
    }

    // API: Get detailed information for a specific part number
    public function apiGetPartDetail($partNumber)
    {
        $items = $this->buildStockItems()
            ->filter(function ($item) use ($partNumber) {
                return $item['part_number'] === $partNumber;
            })
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Part not found'], 404);
        }

        $totalPcs = $items->sum('pcs_quantity');
        $totalBox = $items->sum('box_quantity');

        $palletDetails = $items->map(function ($item) {
            return [
                'box_id' => $item['box_id'] ?? null,
                'pallet_id' => $item['pallet_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'box_number' => $item['box_number'] ?? null,
                'pallet_number' => $item['pallet_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
                'location' => $item['location'] ?? 'Unknown',
                'created_at' => $item['created_at']->format('d M Y H:i'),
                'stored_at_raw' => optional($item['created_at'])->format('Y-m-d H:i:s'),
                'is_not_full' => (bool) ($item['is_not_full'] ?? false),
                'not_full_reason' => $item['not_full_reason'] ?? null,
            ];
        });


        return response()->json([
            'part_number' => $partNumber,
            'total_box' => (int)$totalBox,
            'total_pcs' => $totalPcs,
            'pallet_count' => $items->pluck('pallet_id')->unique()->count(),
            'pallets' => $palletDetails,
        ]);
    }

    // API: Get detailed information for a specific pallet
    public function apiGetPalletDetail($palletId)
    {
        $pallet = Pallet::with(['items', 'boxes', 'stockLocation'])->find($palletId);

        if (!$pallet) {
            return response()->json(['error' => 'Pallet not found'], 404);
        }

        // Priority 1: Use Boxes (Source of Truth for FIFO created_at)
        if ($pallet->boxes->count() > 0) {
            $boxIds = $pallet->boxes
                ->where('is_withdrawn', false)
                ->whereNotIn('expired_status', ['handled', 'expired'])
                ->pluck('id');
            $originLogs = AuditLog::where('type', 'box_pallet_moved')
                ->where('model', 'Box')
                ->whereIn('model_id', $boxIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('model_id')
                ->map(function ($logs) {
                    return $logs->first();
                });

            $items = $pallet->boxes
                ->where('is_withdrawn', false)
                ->whereNotIn('expired_status', ['handled', 'expired'])
                ->map(function ($box) use ($originLogs) {
                $log = $originLogs->get($box->id);
                $origin = $log?->getOldValuesArray()['from_pallet'] ?? null;

                return [
                    'box_id' => $box->id,
                    'part_number' => $box->part_number,
                    'box_number' => $box->box_number,
                    'box_quantity' => 1,
                    'pcs_quantity' => (int)$box->pcs_quantity,
                    'created_at' => $box->created_at->format('d M Y H:i'),
                    'stored_at_raw' => $box->created_at->format('Y-m-d H:i:s'),
                    'origin_pallet' => $origin,
                    'is_not_full' => (bool) $box->is_not_full,
                    'not_full_reason' => $box->not_full_reason,
                ];
            });
        } else {
            // Priority 2: Fallback using PalletItem (Legacy / No Box Data)
            $items = $pallet->items->where(function ($q) {
                 return $q->pcs_quantity > 0 || $q->box_quantity > 0;
            })->map(function ($item) {
                return [
                    'box_id' => null,
                    'part_number' => $item->part_number,
                    'box_number' => '-',
                    'box_quantity' => (int)$item->box_quantity,
                    'pcs_quantity' => (int)$item->pcs_quantity,
                    'created_at' => $item->created_at->format('d M Y H:i'),
                    'stored_at_raw' => $item->created_at->format('Y-m-d H:i:s'),
                    'origin_pallet' => null,
                    'is_not_full' => false,
                    'not_full_reason' => null,
                ];
            });
        }

        return response()->json([
            'pallet_number' => $pallet->pallet_number,
            'location' => $pallet->stockLocation->warehouse_location ?? 'Unknown',
            'items' => $items->values() 
        ]);
    }

    public function updateBox(Request $request, $boxId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'part_number' => 'required|string|max:100|exists:part_settings,part_number',
            'pcs_quantity' => 'required|integer|min:1',
            'stored_at' => 'required|date',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $newPartNumber = trim((string) $validated['part_number']);
        $newPcsQuantity = (int) $validated['pcs_quantity'];
        $newStoredAt = Carbon::parse($validated['stored_at']);

        $box = Box::with('pallets')->findOrFail($boxId);

        if ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Box tidak bisa diedit karena statusnya tidak aktif.'
            ], 422);
        }

        $oldPartNumber = (string) $box->part_number;
        $oldPcsQuantity = (int) $box->pcs_quantity;
        $isSamePart = $oldPartNumber === $newPartNumber;
        $isSamePcs = $oldPcsQuantity === $newPcsQuantity;
        $isSameStoredAtMinute = optional($box->created_at)->format('Y-m-d H:i') === $newStoredAt->format('Y-m-d H:i');

        if ($isSamePart && $isSamePcs && $isSameStoredAtMinute) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada perubahan data box. Aksi tidak diproses.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $box = Box::whereKey($boxId)->lockForUpdate()->firstOrFail();

            if ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true)) {
                throw new \RuntimeException('Box tidak bisa diedit karena statusnya tidak aktif.');
            }

            $pallets = $box->pallets()->lockForUpdate()->get();

            foreach ($pallets as $pallet) {
                $oldItem = PalletItem::where('pallet_id', $pallet->id)
                    ->where('part_number', $oldPartNumber)
                    ->lockForUpdate()
                    ->first();

                if ($oldPartNumber === $newPartNumber) {
                    if ($oldItem) {
                        $oldItem->pcs_quantity = max(0, (int) $oldItem->pcs_quantity - $oldPcsQuantity + $newPcsQuantity);
                        $oldItem->save();
                    }
                } else {
                    if ($oldItem) {
                        $oldItem->box_quantity = max(0, (int) $oldItem->box_quantity - 1);
                        $oldItem->pcs_quantity = max(0, (int) $oldItem->pcs_quantity - $oldPcsQuantity);
                        $oldItem->save();
                    }

                    $newItem = PalletItem::firstOrCreate(
                        [
                            'pallet_id' => $pallet->id,
                            'part_number' => $newPartNumber,
                        ],
                        [
                            'box_quantity' => 0,
                            'pcs_quantity' => 0,
                        ]
                    );

                    $newItem = PalletItem::whereKey($newItem->id)->lockForUpdate()->firstOrFail();

                    $newItem->box_quantity = (int) $newItem->box_quantity + 1;
                    $newItem->pcs_quantity = (int) $newItem->pcs_quantity + $newPcsQuantity;
                    $newItem->save();
                }
            }

            $oldValues = [
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
            ];

            $box->part_number = $newPartNumber;
            $box->pcs_quantity = $newPcsQuantity;
            $box->created_at = $newStoredAt;
            $box->save();

            $newValues = [
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
                'reason' => $validated['reason'],
            ];

            AuditService::log(
                'other',
                'box_updated_by_admin_warehouse',
                'Box',
                $box->id,
                $oldValues,
                $newValues,
                $validated['reason']
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui box: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail box berhasil diperbarui.',
            'box' => [
                'id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('d M Y H:i'),
            ],
        ]);
    }

    public function boxHistory($boxId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['warehouse_operator', 'ppc', 'admin_warehouse', 'supervisi', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $box = Box::findOrFail($boxId);

        $logs = AuditLog::query()
            ->with('user')
            ->where('type', 'other')
            ->where('model', 'Box')
            ->where('action', 'box_updated_by_admin_warehouse')
            ->where('model_id', $box->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
                'user_name' => $log->user?->name ?? 'System',
                'created_at' => optional($log->created_at)->format('d M Y H:i:s'),
            ])
            ->values();

        return response()->json([
            'box_id' => $box->id,
            'box_number' => $box->box_number,
            'history' => $logs,
        ]);
    }

    public function deleteBox(Request $request, $boxId)
    {
        $user = Auth::user();
        if (!$this->canDeleteStock($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $box = Box::whereKey($boxId)->lockForUpdate()->firstOrFail();
            $pallets = $box->pallets()->lockForUpdate()->get();
            $affectedPalletIds = $pallets->pluck('id')->map(fn ($id) => (int) $id)->values();

            $oldValues = [
                'box_id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
                'pallets' => $pallets->map(fn ($p) => [
                    'id' => $p->id,
                    'pallet_number' => $p->pallet_number,
                ])->values()->all(),
            ];

            foreach ($pallets as $pallet) {
                $item = PalletItem::where('pallet_id', $pallet->id)
                    ->where('part_number', $box->part_number)
                    ->lockForUpdate()
                    ->first();

                if ($item) {
                    $item->box_quantity = max(0, (int) $item->box_quantity - 1);
                    $item->pcs_quantity = max(0, (int) $item->pcs_quantity - (int) $box->pcs_quantity);

                    if ((int) $item->box_quantity === 0 && (int) $item->pcs_quantity === 0) {
                        $item->delete();
                    } else {
                        $item->save();
                    }
                }
            }

            $box->forceDelete();

            $autoDeletedPallets = collect();

            foreach ($affectedPalletIds as $palletId) {
                $pallet = Pallet::whereKey($palletId)->lockForUpdate()->first();
                if (!$pallet) {
                    continue;
                }

                $remainingActiveBoxes = (int) DB::table('pallet_boxes')
                    ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                    ->where('pallet_boxes.pallet_id', $palletId)
                    ->whereNull('boxes.deleted_at')
                    ->where('boxes.is_withdrawn', false)
                    ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
                    ->lockForUpdate()
                    ->count();

                PalletItem::where('pallet_id', $palletId)->delete();

                if ($remainingActiveBoxes === 0) {
                    $palletNumber = (string) $pallet->pallet_number;

                    MasterLocation::where('current_pallet_id', $palletId)
                        ->update([
                            'is_occupied' => false,
                            'current_pallet_id' => null,
                            'updated_at' => now(),
                        ]);

                    $pallet->delete();
                    $autoDeletedPallets->push([
                        'id' => $palletId,
                        'pallet_number' => $palletNumber,
                    ]);

                    AuditService::log(
                        'other',
                        'pallet_deleted_by_stock_view',
                        'Pallet',
                        $palletId,
                        [
                            'pallet_id' => $palletId,
                            'pallet_number' => $palletNumber,
                            'reason' => 'auto_deleted_after_last_box_removed',
                        ],
                        [
                            'deleted' => true,
                            'deleted_by_role' => $user->role,
                            'trigger' => 'delete_box',
                        ],
                        sprintf('Pallet %s terhapus otomatis karena box terakhir dihapus', $palletNumber)
                    );
                    continue;
                }

                $remainingBoxes = DB::table('pallet_boxes')
                    ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                    ->where('pallet_boxes.pallet_id', $palletId)
                    ->whereNull('boxes.deleted_at')
                    ->where('boxes.is_withdrawn', false)
                    ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
                    ->select('boxes.part_number', DB::raw('COUNT(*) as box_count'), DB::raw('SUM(boxes.pcs_quantity) as pcs_total'))
                    ->groupBy('boxes.part_number')
                    ->get();

                foreach ($remainingBoxes as $row) {
                    PalletItem::create([
                        'pallet_id' => $palletId,
                        'part_number' => (string) $row->part_number,
                        'box_quantity' => (int) $row->box_count,
                        'pcs_quantity' => (int) $row->pcs_total,
                    ]);
                }
            }

            AuditService::log(
                'other',
                'box_deleted_by_stock_view',
                'Box',
                (int) $boxId,
                $oldValues,
                [
                    'deleted' => true,
                    'deleted_by_role' => $user->role,
                    'auto_deleted_pallets' => $autoDeletedPallets->all(),
                ],
                sprintf('Box %s dihapus permanen dari stock view', $oldValues['box_number'] ?? (string) $boxId)
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus box: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Box berhasil dihapus permanen dan stok telah diperbarui.',
        ]);
    }

    public function deletePallet(Request $request, $palletId)
    {
        $user = Auth::user();
        if (!$this->canDeleteStock($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $pallet = Pallet::with(['stockLocation', 'boxes', 'items'])->whereKey($palletId)->lockForUpdate()->firstOrFail();
            $attachedBoxes = $pallet->boxes;

            $oldValues = [
                'pallet_id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'location' => $pallet->stockLocation->warehouse_location ?? 'Unknown',
                'items' => $attachedBoxes->map(fn ($box) => [
                    'box_id' => $box->id,
                    'box_number' => $box->box_number,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int) $box->pcs_quantity,
                ])->values()->all(),
            ];

            $hardDeletedBoxCount = 0;
            $detachedSharedBoxCount = 0;

            foreach ($attachedBoxes as $box) {
                $box = Box::whereKey($box->id)->lockForUpdate()->first();
                if (!$box) {
                    continue;
                }

                $linkedPalletCount = (int) DB::table('pallet_boxes')
                    ->where('box_id', $box->id)
                    ->lockForUpdate()
                    ->count();

                if ($linkedPalletCount <= 1) {
                    $box->forceDelete();
                    $hardDeletedBoxCount++;
                } else {
                    DB::table('pallet_boxes')
                        ->where('pallet_id', $pallet->id)
                        ->where('box_id', $box->id)
                        ->delete();
                    $detachedSharedBoxCount++;
                }
            }

            MasterLocation::where('current_pallet_id', $pallet->id)
                ->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                    'updated_at' => now(),
                ]);

            $pallet->delete();

            AuditService::log(
                'other',
                'pallet_deleted_by_stock_view',
                'Pallet',
                (int) $palletId,
                $oldValues,
                [
                    'deleted' => true,
                    'hard_deleted_box_count' => $hardDeletedBoxCount,
                    'detached_shared_box_count' => $detachedSharedBoxCount,
                    'deleted_by_role' => $user->role,
                ],
                sprintf('Pallet %s dihapus permanen dari stock view', $oldValues['pallet_number'] ?? (string) $palletId)
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pallet: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pallet berhasil dihapus permanen.',
        ]);
    }

    /**
     * Export stock view by part to Excel
     */
    public function exportByPart(Request $request)
    {
        $search = $request->query('search');
        $stocks = $this->buildStockItems($search);
        
        // Group by part number and sum quantities
        $groupedByPart = $stocks->groupBy('part_number')->map(function ($items) {
            return [
                'part_number' => $items->first()['part_number'],
                'box_quantity' => $items->sum('box_quantity'),
                'pcs_quantity' => $items->sum('pcs_quantity'),
            ];
        })->values();

        return Excel::download(
            new StockViewByPartExport($groupedByPart),
            'stock_by_part_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export stock view by pallet to Excel
     */
    public function exportByPallet(Request $request)
    {
        $search = $request->query('search');
        $stocks = $this->buildStockItems($search);
        
        // Group by pallet and sum quantities
        $groupedByPallet = $stocks->groupBy('pallet_number')->map(function ($items) {
            return [
                'pallet_number' => $items->first()['pallet_number'],
                'location' => $items->first()['location'],
                'box_quantity' => $items->sum('box_quantity'),
                'pcs_quantity' => $items->sum('pcs_quantity'),
            ];
        })->values();

        return Excel::download(
            new StockViewByPalletExport($groupedByPallet),
            'stock_by_pallet_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
