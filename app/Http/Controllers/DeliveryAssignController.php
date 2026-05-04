<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\StockInput;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryAssignController extends Controller
{
    private const ACTIVE_LOCK_STATUSES = ['scanning', 'blocked'];

    private function applyStoredLocationExistsFilter($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('pallet_boxes')
                ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
                ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
                ->whereColumn('pallet_boxes.box_id', 'boxes.id')
                ->where('stock_locations.warehouse_location', '!=', 'Unknown');
        });
    }

    private function getActiveLockedBoxIds(): array
    {
        return DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->whereIn('delivery_pick_sessions.status', self::ACTIVE_LOCK_STATUSES)
            ->pluck('delivery_pick_items.box_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function buildCanonicalPalletSubquery()
    {
        return DB::table('pallet_boxes as pb')
            ->join('pallets as p', 'p.id', '=', 'pb.pallet_id')
            ->join('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->join('boxes as b', 'b.id', '=', 'pb.box_id')
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->where('sl.warehouse_location', '!=', 'Unknown')
            ->groupBy('pb.box_id')
            ->select('pb.box_id', DB::raw('MIN(pb.pallet_id) as canonical_pallet_id'));
    }

    private function hasActivePickSession(int $deliveryOrderId): bool
    {
        return DeliveryPickSession::query()
            ->where('delivery_order_id', $deliveryOrderId)
            ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
            ->exists();
    }

    private function resolveBoxIdsFromPallets(array $palletIds): array
    {
        if (empty($palletIds)) {
            return [];
        }

        $lockedBoxIds = $this->getActiveLockedBoxIds();

        return DB::table('boxes')
            ->join('pallet_boxes', 'pallet_boxes.box_id', '=', 'boxes.id')
            ->whereIn('pallet_boxes.pallet_id', $palletIds)
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNull('boxes.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('boxes.id', $lockedBoxIds);
            })
            ->select('boxes.id')
            ->pluck('boxes.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function buildSkipEntry(?Box $box, string $reason): array
    {
        return [
            'box_id' => $box?->id,
            'box_number' => $box?->box_number,
            'part_number' => $box?->part_number,
            'reason' => $reason,
        ];
    }

    private function assignBoxesToDelivery(int $deliveryOrderId, array $boxIds): array
    {
        $uniqueBoxIds = collect($boxIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($uniqueBoxIds)) {
            return [
                'assigned_box_ids' => [],
                'skipped' => [],
                'session_id' => null,
            ];
        }

        $lockedBoxIds = $this->getActiveLockedBoxIds();
        $lockedLookup = array_fill_keys($lockedBoxIds, true);

        $boxes = Box::query()
            ->with(['pallets.stockLocation'])
            ->whereIn('id', $uniqueBoxIds)
            ->get();

        $boxesById = $boxes->keyBy('id');

        $skipped = [];
        $eligibleBoxes = [];

        foreach ($uniqueBoxIds as $boxId) {
            $box = $boxesById->get($boxId);
            if (!$box) {
                $skipped[] = $this->buildSkipEntry(null, 'Box tidak ditemukan');
                continue;
            }

            if (!empty($lockedLookup[$boxId])) {
                $skipped[] = $this->buildSkipEntry($box, 'Box terkunci di sesi picking aktif');
                continue;
            }

            if ($box->is_withdrawn) {
                $skipped[] = $this->buildSkipEntry($box, 'Box sudah diwithdraw');
                continue;
            }

            if ($box->expired_status && in_array($box->expired_status, ['handled', 'expired'], true)) {
                $skipped[] = $this->buildSkipEntry($box, 'Box expired atau sudah ditangani');
                continue;
            }

            $assignedOrderId = $box->assigned_delivery_order_id ? (int) $box->assigned_delivery_order_id : null;
            if ($assignedOrderId && $assignedOrderId !== $deliveryOrderId) {
                $skipped[] = $this->buildSkipEntry($box, 'Box sudah ter-assign ke delivery lain');
                continue;
            }

            if ($assignedOrderId === $deliveryOrderId) {
                $skipped[] = $this->buildSkipEntry($box, 'Box sudah ter-assign ke delivery ini');
                continue;
            }

            $hasStoredLocation = $box->pallets->contains(function ($pallet) {
                return $pallet->stockLocation
                    && $pallet->stockLocation->warehouse_location !== 'Unknown';
            });

            if (!$hasStoredLocation) {
                $skipped[] = $this->buildSkipEntry($box, 'Box belum memiliki lokasi tersimpan');
                continue;
            }

            $eligibleBoxes[] = $box;
        }

        if (empty($eligibleBoxes)) {
            return [
                'assigned_box_ids' => [],
                'skipped' => $skipped,
                'session_id' => null,
            ];
        }

        $assignedBoxIds = [];
        $sessionId = null;
        $userId = (int) Auth::id();

        DB::transaction(function () use (
            $deliveryOrderId,
            $eligibleBoxes,
            $userId,
            &$assignedBoxIds,
            &$sessionId
        ) {
            $session = DeliveryPickSession::query()
                ->where('delivery_order_id', $deliveryOrderId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if (!$session) {
                $session = DeliveryPickSession::create([
                    'delivery_order_id' => $deliveryOrderId,
                    'created_by' => $userId,
                    'status' => 'pending',
                ]);
            }

            $sessionId = $session->id;
            $assignedBoxIds = collect($eligibleBoxes)->pluck('id')->map(fn ($id) => (int) $id)->all();

            Box::whereIn('id', $assignedBoxIds)
                ->update([
                    'assigned_delivery_order_id' => $deliveryOrderId,
                    'updated_at' => now(),
                ]);

            $existingBoxIds = DeliveryPickItem::query()
                ->where('pick_session_id', $session->id)
                ->whereIn('box_id', $assignedBoxIds)
                ->pluck('box_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $existingLookup = array_fill_keys($existingBoxIds, true);
            $now = now();
            $rows = [];

            foreach ($eligibleBoxes as $box) {
                if (!empty($existingLookup[$box->id])) {
                    continue;
                }

                $rows[] = [
                    'pick_session_id' => $session->id,
                    'box_id' => $box->id,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int) $box->pcs_quantity,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('delivery_pick_items')->insert($rows);
            }
        });

        if (!empty($assignedBoxIds)) {
            AuditService::log(
                'delivery_assignment',
                'created',
                'DeliveryOrder',
                $deliveryOrderId,
                [],
                [
                    'assigned_box_ids' => $assignedBoxIds,
                    'pick_session_id' => $sessionId,
                ],
                'Assign manual box ke delivery order'
            );
        }

        return [
            'assigned_box_ids' => $assignedBoxIds,
            'skipped' => $skipped,
            'session_id' => $sessionId,
        ];
    }

    public function index()
    {
        $deliveryOrders = DeliveryOrder::query()
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->get();

        return view('operator.delivery-assign.index', [
            'deliveryOrders' => $deliveryOrders,
        ]);
    }

    public function search(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $lockedBoxIds = $this->getActiveLockedBoxIds();

        $canonicalSubquery = $this->buildCanonicalPalletSubquery();

        $boxQuery = DB::table('boxes as b')
            ->joinSub($canonicalSubquery, 'canon', function ($join) {
                $join->on('canon.box_id', '=', 'b.id');
            })
            ->join('pallets as p', 'p.id', '=', 'canon.canonical_pallet_id')
            ->join('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->whereNull('b.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('b.id', $lockedBoxIds);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('b.box_number', 'like', '%' . $search . '%')
                        ->orWhere('b.part_number', 'like', '%' . $search . '%')
                        ->orWhere('p.pallet_number', 'like', '%' . $search . '%')
                        ->orWhere('sl.warehouse_location', 'like', '%' . $search . '%');

                    if (is_numeric($search)) {
                        $inner->orWhere('b.id', (int) $search);
                    }
                });
            })
            ->select(
                'b.id',
                'b.box_number',
                'b.part_number',
                'b.pcs_quantity',
                'b.is_not_full',
                'p.id as pallet_id',
                'p.pallet_number',
                'sl.warehouse_location as location'
            )
            ->orderByDesc('b.created_at')
            ->limit(80);

        $boxes = $boxQuery->get();

        $palletQuery = DB::table('pallets as p')
            ->join('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->join('pallet_boxes as pb', 'pb.pallet_id', '=', 'p.id')
            ->join('boxes as b', 'b.id', '=', 'pb.box_id')
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->where('sl.warehouse_location', '!=', 'Unknown')
            ->whereNull('b.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('b.id', $lockedBoxIds);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('p.pallet_number', 'like', '%' . $search . '%')
                        ->orWhere('sl.warehouse_location', 'like', '%' . $search . '%')
                        ->orWhere('b.part_number', 'like', '%' . $search . '%')
                        ->orWhere('b.box_number', 'like', '%' . $search . '%');
                });
            })
            ->select(
                'p.id',
                'p.pallet_number',
                'sl.warehouse_location as location',
                DB::raw('COUNT(DISTINCT b.id) as eligible_boxes')
            )
            ->groupBy('p.id', 'p.pallet_number', 'sl.warehouse_location')
            ->orderBy('p.pallet_number')
            ->limit(40);

        $pallets = $palletQuery->get();

        return response()->json([
            'boxes' => $boxes,
            'pallets' => $pallets,
        ]);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'delivery_order_id' => ['required', 'integer', 'exists:delivery_orders,id'],
            'box_ids' => ['array'],
            'box_ids.*' => ['integer'],
            'pallet_ids' => ['array'],
            'pallet_ids.*' => ['integer'],
        ]);

        $deliveryOrderId = (int) $validated['delivery_order_id'];

        if ($this->hasActivePickSession($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery sedang dalam proses picking aktif. Selesaikan sesi tersebut terlebih dahulu.',
            ], 409);
        }

        $boxIds = $validated['box_ids'] ?? [];
        $palletIds = $validated['pallet_ids'] ?? [];

        $expandedBoxIds = $this->resolveBoxIdsFromPallets($palletIds);
        $allBoxIds = array_values(array_unique(array_merge($boxIds, $expandedBoxIds)));

        if (empty($allBoxIds)) {
            return response()->json([
                'message' => 'Pilih minimal satu box atau pallet untuk di-assign.',
            ], 422);
        }

        $result = $this->assignBoxesToDelivery($deliveryOrderId, $allBoxIds);

        return response()->json([
            'assigned_count' => count($result['assigned_box_ids']),
            'assigned_box_ids' => $result['assigned_box_ids'],
            'skipped_count' => count($result['skipped']),
            'skipped' => $result['skipped'],
            'pick_session_id' => $result['session_id'],
        ]);
    }

    public function assignFromStockInput(Request $request)
    {
        $validated = $request->validate([
            'delivery_order_id' => ['required', 'integer', 'exists:delivery_orders,id'],
            'stock_input_id' => ['required', 'integer', 'exists:stock_inputs,id'],
        ]);

        $deliveryOrderId = (int) $validated['delivery_order_id'];

        if ($this->hasActivePickSession($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery sedang dalam proses picking aktif. Selesaikan sesi tersebut terlebih dahulu.',
            ], 409);
        }

        $stockInput = StockInput::with('boxes')->findOrFail((int) $validated['stock_input_id']);
        $boxIds = $stockInput->boxes->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($boxIds)) {
            return response()->json([
                'message' => 'Tidak ada box pada stock input ini untuk di-assign.',
            ], 422);
        }

        $result = $this->assignBoxesToDelivery($deliveryOrderId, $boxIds);

        return response()->json([
            'assigned_count' => count($result['assigned_box_ids']),
            'assigned_box_ids' => $result['assigned_box_ids'],
            'skipped_count' => count($result['skipped']),
            'skipped' => $result['skipped'],
            'pick_session_id' => $result['session_id'],
        ]);
    }
}
