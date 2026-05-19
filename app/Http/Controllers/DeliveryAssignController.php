<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\StockInput;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryAssignController extends Controller
{
    private const ACTIVE_LOCK_STATUSES = ['scanning', 'blocked'];

    private function resolveAndClaimMasterLocation(int $masterLocationId, Pallet $pallet): string
    {
        $masterLocation = MasterLocation::query()->find($masterLocationId);
        if (!$masterLocation) {
            throw new \RuntimeException('Lokasi yang dipilih tidak ditemukan.');
        }

        $claimed = MasterLocation::query()
            ->whereKey($masterLocation->id)
            ->where('is_occupied', false)
            ->update([
                'is_occupied' => true,
                'current_pallet_id' => $pallet->id,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            throw new \RuntimeException('Lokasi yang dipilih sudah terisi.');
        }

        return (string) $masterLocation->code;
    }

    private function syncPalletItemsWithActiveBoxes(Pallet $pallet): void
    {
        $activeByPart = DB::table('pallet_boxes')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->where('pallet_boxes.pallet_id', $pallet->id)
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('boxes.expired_status')
                    ->orWhereNotIn('boxes.expired_status', ['handled', 'expired']);
            })
            ->select(
                'boxes.part_number',
                DB::raw('COUNT(*) as box_quantity'),
                DB::raw('SUM(boxes.pcs_quantity) as pcs_quantity')
            )
            ->groupBy('boxes.part_number')
            ->get();

        $pallet->items()->delete();

        foreach ($activeByPart as $row) {
            if (empty($row->part_number)) {
                continue;
            }

            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => (string) $row->part_number,
                'box_quantity' => (int) $row->box_quantity,
                'pcs_quantity' => (int) $row->pcs_quantity,
            ]);
        }
    }

    private function createStockInputRecord(Pallet $pallet, array $attachedBoxIds, string $locationCode): StockInput
    {
        $attachedBoxes = Box::query()
            ->whereIn('id', $attachedBoxIds)
            ->get(['id', 'part_number', 'pcs_quantity']);

        if ($attachedBoxes->isEmpty()) {
            throw new \RuntimeException('Tidak ada box valid untuk membuat stock input.');
        }

        $totalPcs = (int) $attachedBoxes->sum('pcs_quantity');
        $partNumbers = $attachedBoxes
            ->pluck('part_number')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $palletItem = !empty($partNumbers)
            ? $pallet->items()->whereIn('part_number', $partNumbers)->orderBy('id')->first()
            : null;

        return StockInput::create([
            'pallet_id' => $pallet->id,
            'pallet_item_id' => $palletItem?->id,
            'user_id' => Auth::id(),
            'warehouse_location' => $locationCode,
            'pcs_quantity' => $totalPcs,
            'box_quantity' => $attachedBoxes->count(),
            'stored_at' => now(),
            'part_numbers' => $partNumbers,
        ]);
    }

    private function createStockInputBoxRecords(StockInput $stockInput, array $boxIds): void
    {
        $rows = collect($boxIds)
            ->map(fn ($boxId) => (int) $boxId)
            ->filter(fn ($boxId) => $boxId > 0)
            ->unique()
            ->values()
            ->map(fn ($boxId) => [
                'stock_input_id' => $stockInput->id,
                'box_id' => $boxId,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if (!empty($rows)) {
            DB::table('stock_input_boxes')->insert($rows);
        }
    }

    private function isApprovedDeliveryOrder(int $deliveryOrderId): bool
    {
        return DeliveryOrder::query()
            ->whereNull('deleted_at')
            ->whereKey($deliveryOrderId)
            ->where('status', 'approved')
            ->exists();
    }

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

    private function getDeliveryOrderPartAvailability(int $deliveryOrderId): array
    {
        $requestedRows = DB::table('delivery_order_items')
            ->where('delivery_order_id', $deliveryOrderId)
            ->select('part_number', DB::raw('SUM(quantity) as requested_quantity'))
            ->groupBy('part_number')
            ->get();

        $assignedRows = DB::table('boxes')
            ->where('assigned_delivery_order_id', $deliveryOrderId)
            ->whereNull('deleted_at')
            ->where('is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('expired_status')
                    ->orWhereNotIn('expired_status', ['handled', 'expired']);
            })
            ->select('part_number', DB::raw('SUM(pcs_quantity) as assigned_quantity'))
            ->groupBy('part_number')
            ->get();

        $assignedLookup = [];
        foreach ($assignedRows as $row) {
            $assignedLookup[(string) $row->part_number] = (int) $row->assigned_quantity;
        }

        $availability = [];
        foreach ($requestedRows as $row) {
            $partNumber = (string) $row->part_number;
            $requestedQuantity = (int) $row->requested_quantity;
            $assignedQuantity = (int) ($assignedLookup[$partNumber] ?? 0);
            $remainingQuantity = max(0, $requestedQuantity - $assignedQuantity);

            $availability[$partNumber] = [
                'part_number' => $partNumber,
                'requested_quantity' => $requestedQuantity,
                'assigned_quantity' => $assignedQuantity,
                'remaining_quantity' => $remainingQuantity,
            ];
        }

        return $availability;
    }

    private function getDeliveryOrderAvailablePartNumbers(int $deliveryOrderId): array
    {
        $availability = $this->getDeliveryOrderPartAvailability($deliveryOrderId);

        return array_values(array_keys(array_filter(
            $availability,
            fn ($entry) => (int) ($entry['remaining_quantity'] ?? 0) > 0
        )));
    }

    private function validateDeliveryOrderPartCoverage(int $deliveryOrderId, array $boxes, array $newBoxes): array
    {
        $availability = $this->getDeliveryOrderPartAvailability($deliveryOrderId);
        $selectedTotals = [];

        foreach ($boxes as $box) {
            if (!$box) {
                continue;
            }

            $partNumber = (string) $box->part_number;
            $selectedTotals[$partNumber] = ($selectedTotals[$partNumber] ?? 0) + (int) $box->pcs_quantity;
        }

        foreach ($newBoxes as $entry) {
            $partNumber = (string) ($entry['part_number'] ?? '');
            if ($partNumber === '') {
                continue;
            }

            $selectedTotals[$partNumber] = ($selectedTotals[$partNumber] ?? 0) + (int) ($entry['pcs_quantity'] ?? 0);
        }

        $errors = [];
        $overages = [];
        foreach ($selectedTotals as $partNumber => $selectedQuantity) {
            if (!isset($availability[$partNumber])) {
                $errors[] = "Part {$partNumber} tidak ada di delivery order ini.";
                continue;
            }

            $requestedQuantity = (int) ($availability[$partNumber]['requested_quantity'] ?? 0);
            $assignedQuantity = (int) ($availability[$partNumber]['assigned_quantity'] ?? 0);
            $remainingQuantity = (int) ($availability[$partNumber]['remaining_quantity'] ?? 0);
            if ($selectedQuantity > $remainingQuantity) {
                $overageQuantity = $selectedQuantity - $remainingQuantity;
                $overages[] = [
                    'part_number' => $partNumber,
                    'requested_quantity' => $requestedQuantity,
                    'assigned_quantity' => $assignedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'selected_quantity' => $selectedQuantity,
                    'overage_quantity' => $overageQuantity,
                    'new_requested_quantity' => $requestedQuantity + $overageQuantity,
                ];
            }
        }

        return [
            'errors' => $errors,
            'overages' => $overages,
        ];
    }

    private function applyOverageAdjustments(int $deliveryOrderId, array $overages): array
    {
        if (empty($overages)) {
            return [];
        }

        $adjustments = [];

        foreach ($overages as $overage) {
            $partNumber = (string) ($overage['part_number'] ?? '');
            $newRequestedQuantity = (int) ($overage['new_requested_quantity'] ?? 0);
            if ($partNumber === '' || $newRequestedQuantity <= 0) {
                continue;
            }

            $item = DeliveryOrderItem::query()
                ->where('delivery_order_id', $deliveryOrderId)
                ->where('part_number', $partNumber)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$item) {
                continue;
            }

            $oldQuantity = (int) $item->quantity;
            if ($newRequestedQuantity <= $oldQuantity) {
                continue;
            }

            $item->quantity = $newRequestedQuantity;
            $item->save();

            $adjustments[] = [
                'part_number' => $partNumber,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newRequestedQuantity,
                'increased_by' => $newRequestedQuantity - $oldQuantity,
            ];
        }

        return $adjustments;
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

    private function addNewBoxError(array &$errors, int $index, ?string $boxNumber, ?string $partNumber, string $reason): void
    {
        $errors[] = [
            'index' => $index,
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'reason' => $reason,
        ];
    }

    private function validateNewBoxes(array $newBoxes, int $deliveryOrderId): array
    {
        $errors = [];
        $prepared = [];
        $invalidIndexes = [];

        foreach ($newBoxes as $index => $entry) {
            $boxNumber = trim((string) ($entry['box_number'] ?? ''));
            $partNumber = trim((string) ($entry['part_number'] ?? ''));
            $pcsQuantityRaw = $entry['pcs_quantity'] ?? null;
            $pcsQuantity = is_numeric($pcsQuantityRaw) ? (int) $pcsQuantityRaw : null;

            if ($boxNumber === '' || !ctype_digit($boxNumber)) {
                $this->addNewBoxError($errors, $index, $boxNumber, $partNumber, 'ID Box harus berupa angka.');
                $invalidIndexes[$index] = true;
                continue;
            }

            if ($partNumber === '') {
                $this->addNewBoxError($errors, $index, $boxNumber, $partNumber, 'No Part wajib diisi.');
                $invalidIndexes[$index] = true;
                continue;
            }

            if ($pcsQuantity === null || $pcsQuantity <= 0) {
                $this->addNewBoxError($errors, $index, $boxNumber, $partNumber, 'Qty PCS harus lebih dari 0.');
                $invalidIndexes[$index] = true;
                continue;
            }

            $prepared[$index] = [
                'box_number' => $boxNumber,
                'part_number' => $partNumber,
                'pcs_quantity' => $pcsQuantity,
            ];
        }

        if (empty($prepared)) {
            return [[], $errors];
        }

        $boxNumbers = array_values(array_unique(array_column($prepared, 'box_number')));
        $partNumbers = array_values(array_unique(array_column($prepared, 'part_number')));

        $boxNumberCounts = array_count_values(array_column($prepared, 'box_number'));
        foreach ($prepared as $index => $entry) {
            if (($boxNumberCounts[$entry['box_number']] ?? 0) > 1) {
                $this->addNewBoxError($errors, $index, $entry['box_number'], $entry['part_number'], 'ID Box duplikat dalam input.');
                $invalidIndexes[$index] = true;
            }
        }

        $existingBoxNumbers = Box::query()
            ->whereIn('box_number', $boxNumbers)
            ->pluck('box_number')
            ->map(fn ($value) => (string) $value)
            ->all();
        $existingLookup = array_fill_keys($existingBoxNumbers, true);

        $partSettings = PartSetting::query()
            ->whereIn('part_number', $partNumbers)
            ->get()
            ->keyBy('part_number');

        $orderPartNumbers = DB::table('delivery_order_items')
            ->where('delivery_order_id', $deliveryOrderId)
            ->pluck('part_number')
            ->map(fn ($partNumber) => (string) $partNumber)
            ->all();
        $orderPartLookup = array_fill_keys($orderPartNumbers, true);

        $validEntries = [];
        foreach ($prepared as $index => $entry) {
            if (!empty($invalidIndexes[$index])) {
                continue;
            }

            if (!empty($existingLookup[$entry['box_number']])) {
                $this->addNewBoxError($errors, $index, $entry['box_number'], $entry['part_number'], 'ID Box sudah ada di sistem.');
                $invalidIndexes[$index] = true;
                continue;
            }

            $partSetting = $partSettings->get($entry['part_number']);
            if (!$partSetting) {
                $this->addNewBoxError($errors, $index, $entry['box_number'], $entry['part_number'], 'No Part tidak ditemukan di Master Part.');
                $invalidIndexes[$index] = true;
                continue;
            }

            if (empty($orderPartLookup[$entry['part_number']])) {
                $this->addNewBoxError($errors, $index, $entry['box_number'], $entry['part_number'], 'No Part tidak ada dalam delivery order yang dipilih.');
                $invalidIndexes[$index] = true;
                continue;
            }

            $validEntries[] = array_merge($entry, [
                'qty_box' => (int) ($partSetting->qty_box ?? 0) > 0 ? (int) $partSetting->qty_box : null,
            ]);
        }

        return [$validEntries, $errors];
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
            ->whereNull('deleted_at')
            ->where('status', 'approved')
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->get();

        return view('operator.delivery-assign.index', [
            'deliveryOrders' => $deliveryOrders,
        ]);
    }

    public function deliveryOrderParts(Request $request, int $deliveryOrderId)
    {
        if ($deliveryOrderId <= 0 || !$this->isApprovedDeliveryOrder($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery order tidak ditemukan atau belum approved.',
            ], 404);
        }

        $availability = $this->getDeliveryOrderPartAvailability($deliveryOrderId);

        return response()->json([
            'delivery_order_id' => $deliveryOrderId,
            'parts' => array_values(array_filter(
                $availability,
                fn ($entry) => (int) ($entry['remaining_quantity'] ?? 0) > 0
            )),
        ]);
    }

    public function search(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $deliveryOrderId = (int) $request->query('delivery_order_id', 0);

        if ($deliveryOrderId <= 0 || !$this->isApprovedDeliveryOrder($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery order harus dipilih dan status approved.',
                'boxes' => [],
                'pallets' => [],
            ], 422);
        }

        $availableParts = $this->getDeliveryOrderAvailablePartNumbers($deliveryOrderId);
        if (empty($availableParts)) {
            return response()->json([
                'boxes' => [],
                'pallets' => [],
            ]);
        }

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
            ->whereIn('b.part_number', $availableParts)
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
            ->whereIn('b.part_number', $availableParts)
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

    public function palletBoxes(Request $request, int $palletId)
    {
        $deliveryOrderId = (int) $request->query('delivery_order_id', 0);
        if ($deliveryOrderId <= 0 || !$this->isApprovedDeliveryOrder($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery order harus dipilih dan status approved.',
            ], 422);
        }

        $availableParts = $this->getDeliveryOrderAvailablePartNumbers($deliveryOrderId);
        if (empty($availableParts)) {
            return response()->json([
                'pallet' => null,
                'total' => 0,
                'limit' => 0,
                'boxes' => [],
            ]);
        }

        $limit = (int) $request->query('limit', 60);
        if ($limit <= 0) {
            $limit = 60;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $pallet = DB::table('pallets as p')
            ->join('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->where('p.id', $palletId)
            ->where('sl.warehouse_location', '!=', 'Unknown')
            ->select(
                'p.id',
                'p.pallet_number',
                'sl.warehouse_location as location'
            )
            ->first();

        if (!$pallet) {
            return response()->json([
                'message' => 'Pallet tidak ditemukan.',
            ], 404);
        }

        $lockedBoxIds = $this->getActiveLockedBoxIds();

        $baseQuery = DB::table('boxes as b')
            ->join('pallet_boxes as pb', 'pb.box_id', '=', 'b.id')
            ->where('pb.pallet_id', $palletId)
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->whereNull('b.assigned_delivery_order_id')
            ->whereIn('b.part_number', $availableParts)
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('b.id', $lockedBoxIds);
            });

        $total = (clone $baseQuery)->distinct()->count('b.id');

        $boxes = $baseQuery
            ->distinct()
            ->select(
                'b.id',
                'b.box_number',
                'b.part_number',
                'b.pcs_quantity',
                'b.is_not_full'
            )
            ->orderBy('b.box_number')
            ->limit($limit)
            ->get();

        return response()->json([
            'pallet' => $pallet,
            'total' => $total,
            'limit' => $limit,
            'boxes' => $boxes,
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
            'new_boxes' => ['array'],
            'new_boxes.*.box_number' => ['required', 'string'],
            'new_boxes.*.part_number' => ['required', 'string'],
            'new_boxes.*.pcs_quantity' => ['required', 'integer', 'min:1'],
            'new_boxes_pallet_mode' => ['nullable', 'string', 'in:new,existing'],
            'new_boxes_pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'new_boxes_pallet_number' => ['nullable', 'string', 'max:255'],
            'new_boxes_location_id' => ['nullable', 'integer', 'exists:master_locations,id'],
            'confirm_overage' => ['nullable', 'boolean'],
        ]);

        $deliveryOrderId = (int) $validated['delivery_order_id'];
        $confirmOverage = (bool) ($validated['confirm_overage'] ?? false);

        if (!$this->isApprovedDeliveryOrder($deliveryOrderId)) {
            return response()->json([
                'message' => 'Hanya delivery order dengan status approved yang dapat di-assign.',
            ], 422);
        }

        if ($this->hasActivePickSession($deliveryOrderId)) {
            return response()->json([
                'message' => 'Delivery sedang dalam proses picking aktif. Selesaikan sesi tersebut terlebih dahulu.',
            ], 409);
        }

        $boxIds = $validated['box_ids'] ?? [];
        $palletIds = $validated['pallet_ids'] ?? [];
        $newBoxes = $validated['new_boxes'] ?? [];

        $newBoxesPalletMode = $validated['new_boxes_pallet_mode'] ?? null;
        $newBoxesPalletId = isset($validated['new_boxes_pallet_id']) ? (int) $validated['new_boxes_pallet_id'] : 0;
        $newBoxesPalletNumber = trim((string) ($validated['new_boxes_pallet_number'] ?? ''));
        $newBoxesLocationId = isset($validated['new_boxes_location_id']) ? (int) $validated['new_boxes_location_id'] : 0;

        [$validNewBoxes, $newBoxErrors] = $this->validateNewBoxes($newBoxes, $deliveryOrderId);
        if (!empty($newBoxErrors)) {
            return response()->json([
                'message' => 'Box baru tidak valid. Periksa kembali input scan.',
                'new_box_errors' => $newBoxErrors,
            ], 422);
        }

        if (!empty($validNewBoxes)) {
            if (!$newBoxesPalletMode) {
                return response()->json([
                    'message' => 'Pilih pallet untuk input box baru terlebih dahulu.',
                ], 422);
            }

            if ($newBoxesPalletMode === 'existing') {
                if ($newBoxesPalletId <= 0) {
                    return response()->json([
                        'message' => 'Pilih pallet existing untuk input box baru.',
                    ], 422);
                }

                $pallet = Pallet::query()->with('stockLocation')->find($newBoxesPalletId);
                $locationCode = $pallet?->stockLocation?->warehouse_location;
                if (!$pallet || !$locationCode || $locationCode === 'Unknown') {
                    return response()->json([
                        'message' => 'Pallet existing belum memiliki lokasi tersimpan. Gunakan pallet baru dan pilih lokasi.',
                    ], 422);
                }
            } else {
                if ($newBoxesPalletNumber === '') {
                    return response()->json([
                        'message' => 'Nomor pallet wajib diisi untuk pallet baru.',
                    ], 422);
                }

                if ($newBoxesLocationId <= 0) {
                    return response()->json([
                        'message' => 'Pilih lokasi untuk pallet baru.',
                    ], 422);
                }

                $alreadyExists = Pallet::query()->where('pallet_number', $newBoxesPalletNumber)->exists();
                if ($alreadyExists) {
                    return response()->json([
                        'message' => 'Nomor pallet sudah ada. Pilih mode pallet existing.',
                    ], 422);
                }

                $available = MasterLocation::query()
                    ->whereKey($newBoxesLocationId)
                    ->where('is_occupied', false)
                    ->exists();
                if (!$available) {
                    return response()->json([
                        'message' => 'Lokasi yang dipilih sudah terisi. Pilih lokasi lain.',
                    ], 422);
                }
            }
        }

        $expandedBoxIds = $this->resolveBoxIdsFromPallets($palletIds);
        $allBoxIds = array_values(array_unique(array_merge($boxIds, $expandedBoxIds)));

        if (empty($allBoxIds) && empty($validNewBoxes)) {
            return response()->json([
                'message' => 'Pilih minimal satu box/pallet atau scan box baru.',
            ], 422);
        }

        $boxes = Box::query()
            ->with(['pallets.stockLocation'])
            ->whereIn('id', $allBoxIds)
            ->get();

        $boxesById = $boxes->keyBy('id');

        $skipped = [];
        $eligibleBoxes = [];

        $lockedBoxIds = $this->getActiveLockedBoxIds();
        $lockedLookup = array_fill_keys($lockedBoxIds, true);

        foreach ($allBoxIds as $boxId) {
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

        if (empty($eligibleBoxes) && empty($validNewBoxes)) {
            return response()->json([
                'assigned_existing_count' => 0,
                'assigned_existing_box_ids' => [],
                'created_new_count' => 0,
                'created_new_box_ids' => [],
                'skipped_count' => count($skipped),
                'skipped' => $skipped,
                'pick_session_id' => null,
            ]);
        }

        $partCoverage = $this->validateDeliveryOrderPartCoverage($deliveryOrderId, $eligibleBoxes, $validNewBoxes);
        $partCoverageErrors = $partCoverage['errors'] ?? [];
        $partOverages = $partCoverage['overages'] ?? [];

        if (!empty($partCoverageErrors)) {
            return response()->json([
                'message' => $partCoverageErrors[0],
                'part_coverage_errors' => $partCoverageErrors,
            ], 422);
        }

        if (!empty($partOverages) && !$confirmOverage) {
            $firstOverage = $partOverages[0];

            return response()->json([
                'message' => 'Qty assign melebihi sisa request delivery. Konfirmasi untuk menambah qty delivery order.',
                'requires_overage_confirmation' => true,
                'part_overages' => $partOverages,
                'part_coverage_errors' => [
                    sprintf(
                        'Part %s melebihi sisa request (%d/%d). Jika dilanjutkan, qty request akan dinaikkan.',
                        (string) ($firstOverage['part_number'] ?? '-'),
                        (int) ($firstOverage['selected_quantity'] ?? 0),
                        (int) ($firstOverage['remaining_quantity'] ?? 0)
                    ),
                ],
            ], 409);
        }

        $assignedExistingBoxIds = [];
        $createdNewBoxIds = [];
        $createdStockInputIdForNewBoxes = null;
        $sessionId = null;
        $overageAdjustments = [];
        $userId = (int) Auth::id();

        DB::transaction(function () use (
            $deliveryOrderId,
            $eligibleBoxes,
            $validNewBoxes,
            $newBoxesPalletMode,
            $newBoxesPalletId,
            $newBoxesPalletNumber,
            $newBoxesLocationId,
            $userId,
            &$assignedExistingBoxIds,
            &$createdNewBoxIds,
            &$createdStockInputIdForNewBoxes,
            &$sessionId,
            &$overageAdjustments,
            $partOverages
        ) {
            if (!empty($partOverages)) {
                $overageAdjustments = $this->applyOverageAdjustments($deliveryOrderId, $partOverages);
            }

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

            if (!empty($eligibleBoxes)) {
                $assignedExistingBoxIds = collect($eligibleBoxes)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                Box::whereIn('id', $assignedExistingBoxIds)
                    ->update([
                        'assigned_delivery_order_id' => $deliveryOrderId,
                        'updated_at' => now(),
                    ]);

                $existingBoxIds = DeliveryPickItem::query()
                    ->where('pick_session_id', $session->id)
                    ->whereIn('box_id', $assignedExistingBoxIds)
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
            }

            if (!empty($validNewBoxes)) {
                $palletForNewBoxes = null;
                $locationCodeForNewBoxes = null;

                if ($newBoxesPalletMode === 'existing') {
                    $palletForNewBoxes = Pallet::query()
                        ->with(['stockLocation', 'items'])
                        ->lockForUpdate()
                        ->findOrFail($newBoxesPalletId);

                    $locationCodeForNewBoxes = (string) ($palletForNewBoxes->stockLocation?->warehouse_location ?? '');
                    if ($locationCodeForNewBoxes === '' || $locationCodeForNewBoxes === 'Unknown') {
                        throw new \RuntimeException('Pallet existing belum memiliki lokasi tersimpan.');
                    }
                } else {
                    $palletForNewBoxes = Pallet::create([
                        'pallet_number' => $newBoxesPalletNumber,
                    ]);

                    $locationCodeForNewBoxes = $this->resolveAndClaimMasterLocation($newBoxesLocationId, $palletForNewBoxes);

                    StockLocation::create([
                        'pallet_id' => $palletForNewBoxes->id,
                        'master_location_id' => $newBoxesLocationId,
                        'warehouse_location' => $locationCodeForNewBoxes,
                        'stored_at' => now(),
                    ]);
                }

                $now = now();
                $newBoxRows = [];
                foreach ($validNewBoxes as $entry) {
                    $newBoxRows[] = [
                        'box_number' => $entry['box_number'],
                        'part_number' => $entry['part_number'],
                        'pcs_quantity' => $entry['pcs_quantity'],
                        'qr_code' => $entry['box_number'] . '|' . $entry['part_number'] . '|' . $entry['pcs_quantity'],
                        'user_id' => $userId,
                        'qty_box' => $entry['qty_box'],
                        'is_not_full' => $entry['qty_box'] ? $entry['pcs_quantity'] < $entry['qty_box'] : false,
                        'not_full_reason' => $entry['qty_box'] && $entry['pcs_quantity'] < $entry['qty_box'] ? 'Direct delivery input' : null,
                        'assigned_delivery_order_id' => $deliveryOrderId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach ($newBoxRows as $row) {
                    $box = Box::create($row);
                    $createdNewBoxIds[] = (int) $box->id;

                    $palletForNewBoxes->boxes()->syncWithoutDetaching([$box->id]);
                }

                $this->syncPalletItemsWithActiveBoxes($palletForNewBoxes);
                $stockInput = $this->createStockInputRecord($palletForNewBoxes, $createdNewBoxIds, $locationCodeForNewBoxes);
                $this->createStockInputBoxRecords($stockInput, $createdNewBoxIds);
                $createdStockInputIdForNewBoxes = (int) $stockInput->id;

                if (!empty($createdNewBoxIds)) {
                    $pickRows = [];
                    foreach ($createdNewBoxIds as $index => $boxId) {
                        $entry = $validNewBoxes[$index] ?? null;
                        if (!$entry) {
                            continue;
                        }

                        $pickRows[] = [
                            'pick_session_id' => $session->id,
                            'box_id' => $boxId,
                            'part_number' => $entry['part_number'],
                            'pcs_quantity' => (int) $entry['pcs_quantity'],
                            'status' => 'pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($pickRows)) {
                        DB::table('delivery_pick_items')->insert($pickRows);
                    }
                }
            }
        });

        if (!empty($assignedExistingBoxIds) || !empty($createdNewBoxIds)) {
            AuditService::log(
                'delivery_assignment',
                'created',
                'DeliveryOrder',
                $deliveryOrderId,
                [],
                [
                    'assigned_existing_box_ids' => $assignedExistingBoxIds,
                    'created_new_box_ids' => $createdNewBoxIds,
                    'pick_session_id' => $sessionId,
                    'overage_adjustments' => $overageAdjustments,
                ],
                'Assign manual stock + direct input box ke delivery order'
            );
        }

        return response()->json([
            'assigned_existing_count' => count($assignedExistingBoxIds),
            'assigned_existing_box_ids' => $assignedExistingBoxIds,
            'created_new_count' => count($createdNewBoxIds),
            'created_new_box_ids' => $createdNewBoxIds,
            'created_stock_input_id' => $createdStockInputIdForNewBoxes,
            'overage_adjustments' => $overageAdjustments,
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
            'pick_session_id' => $sessionId,
        ]);
    }

    public function searchPalletsForNewBoxInput(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        $pallets = Pallet::query()
            ->with(['stockLocation'])
            ->withCount('activeBoxes')
            ->whereHas('stockLocation', function ($q) {
                $q->where('warehouse_location', '!=', 'Unknown');
            })
            ->when($query !== '', function ($q) use ($query) {
                $q->where('pallet_number', 'like', '%' . $query . '%');
            })
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (Pallet $pallet) {
                return [
                    'id' => $pallet->id,
                    'pallet_number' => $pallet->pallet_number,
                    'warehouse_location' => $pallet->stockLocation?->warehouse_location,
                    'total_boxes' => (int) $pallet->active_boxes_count,
                ];
            })
            ->values();

        return response()->json([
            'pallets' => $pallets,
        ]);
    }

    public function assignFromStockInput(Request $request)
    {
        $validated = $request->validate([
            'delivery_order_id' => ['required', 'integer', 'exists:delivery_orders,id'],
            'stock_input_id' => ['required', 'integer', 'exists:stock_inputs,id'],
        ]);

        $deliveryOrderId = (int) $validated['delivery_order_id'];

        if (!$this->isApprovedDeliveryOrder($deliveryOrderId)) {
            return response()->json([
                'message' => 'Hanya delivery order dengan status approved yang dapat di-assign.',
            ], 422);
        }

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

