<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\MasterLocation;
use App\Models\NotFullBoxRequest;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockWithdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StockWithdrawalController extends Controller
{
    private const DELIVERY_APPROVAL_PENDING_MESSAGE = 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.';

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

    private function getStoredLocationPivotSubquery()
    {
        return DB::table('pallet_boxes as pb')
            ->join('pallets', 'pallets.id', '=', 'pb.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->select('pb.box_id', DB::raw('MAX(pb.id) as pivot_id'))
            ->groupBy('pb.box_id');
    }

    private function selectBoxesForWithdrawal(string $partNumber, int $requestedQty, ?int $deliveryOrderId = null)
    {
        $selected = collect();
        $remaining = $requestedQty;

        if ($deliveryOrderId) {
            $reservedBoxes = $this->getReservedBoxesForOrder($deliveryOrderId, $partNumber);
            foreach ($reservedBoxes as $box) {
                if ($remaining <= 0) {
                    break;
                }

                if ((int) $box->pcs_quantity > $remaining) {
                    continue;
                }

                $selected->push($box);
                $remaining -= (int) $box->pcs_quantity;
            }
        }

        if ($remaining <= 0) {
            return $selected;
        }

        $fifoQuery = Box::query()
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->whereNotIn('boxes.id', function ($q) {
                $q->select('box_id')
                    ->from('delivery_pick_items')
                    ->whereIn('pick_session_id', function ($q2) {
                        $q2->select('id')
                            ->from('delivery_pick_sessions')
                            ->whereIn('status', ['scanning', 'blocked', 'approved']);
                    });
            })
            ->whereNull('boxes.assigned_delivery_order_id')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.*');

        $fifoQuery = $this->applyStoredLocationExistsFilter($fifoQuery);

        $fifoBoxes = $fifoQuery->get();
        foreach ($fifoBoxes as $box) {
            if ($remaining <= 0) {
                break;
            }

            if ((int) $box->pcs_quantity > $remaining) {
                continue;
            }

            $selected->push($box);
            $remaining -= (int) $box->pcs_quantity;
        }

        return $selected;
    }

    private function hasPendingAdditionalNotFullRequestForOrder(int $orderId, bool $lockRows = false): bool
    {
        $query = NotFullBoxRequest::where('delivery_order_id', $orderId)
            ->where('request_type', 'additional')
            ->where('status', 'pending');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function findInvalidMasterPartNumber(array $items): ?string
    {
        $partNumbers = collect($items)
            ->map(fn ($item) => trim((string) ($item['part_number'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($partNumbers->isEmpty()) {
            return null;
        }

        $partSettings = PartSetting::query()
            ->whereIn('part_number', $partNumbers->all())
            ->get()
            ->keyBy('part_number');

        foreach ($partNumbers as $partNumber) {
            $partSetting = $partSettings->get($partNumber);
            if (!$partSetting || (string) $partSetting->part_number !== $partNumber) {
                return $partNumber;
            }
        }

        return null;
    }

    /**
     * Show the fulfillment page for a delivery order
     */
    public function fulfillOrder($id)
    {
        $order = DeliveryOrder::with('items')->findOrFail($id);

        if ($this->hasPendingAdditionalNotFullRequestForOrder((int) $order->id)) {
            return redirect()->route('delivery.index')->with('error', self::DELIVERY_APPROVAL_PENDING_MESSAGE);
        }

        return view('operator.delivery.fulfill', compact('order'));
    }

    /**
     * Search for part numbers
     */
    public function searchParts(Request $request)
    {
        $query = $request->input('q', '');

        $boxPartsQuery = DB::table('boxes')
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->where('boxes.part_number', 'like', '%' . $query . '%')
            ->select('boxes.part_number')
            ->distinct()
            ->limit(20);

        $boxParts = $this->applyStoredLocationExistsFilter($boxPartsQuery)
            ->pluck('boxes.part_number');

        $legacyParts = PalletItem::select('part_number')
            ->distinct()
            ->where('part_number', 'like', '%' . $query . '%')
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->limit(20)
            ->pluck('part_number');

        $parts = $boxParts->merge($legacyParts)->unique()->values();

        if ($parts->isEmpty()) {
            return response()->json(['results' => []]);
        }

        $totals = $this->getTotalsByPartNumbers($parts->all());

        $results = [];
        foreach ($parts as $partNumber) {
            $totalQty = (int) ($totals[$partNumber] ?? 0);
            if ($totalQty > 0) {
                $results[] = [
                    'part_number' => $partNumber,
                    'total_stock' => $totalQty,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Preview locations for withdrawal (FIFO order)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'required|integer|min:1',
            'delivery_order_id' => 'nullable|integer',
            'allow_partial' => 'nullable|boolean',
        ]);

        $partNumber = $request->input('part_number');
        $requestedQty = (int) $request->input('pcs_quantity');
        $orderId = $request->input('delivery_order_id');
        $allowPartial = $request->boolean('allow_partial', false);

        if (!$this->findExactPartSetting($partNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'No Part tidak ditemukan di Master Part.',
            ], 422);
        }

        $reservedLocations = [];
        $reservedTotal = 0;

        if ($orderId) {
            $reservedBoxes = $this->getReservedBoxesForOrder((int) $orderId, $partNumber);
            foreach ($reservedBoxes as $box) {
                $pallet = $box->pallets->first();
                $stockLocation = $pallet?->stockLocation;
                $reservedLocations[] = [
                    'pallet_id' => $pallet?->id,
                    'pallet_number' => $pallet?->pallet_number ?? '-',
                    'warehouse_location' => $stockLocation?->warehouse_location ?? 'Unknown',
                    'stored_date' => $box->created_at ? Carbon::parse($box->created_at)->format('d/m/Y H:i') : '-',
                    'available_pcs' => (int) $box->pcs_quantity,
                    'available_box' => 1,
                    'pcs_per_box' => (int) $box->pcs_quantity,
                    'will_take_pcs' => (int) $box->pcs_quantity,
                    'will_take_box' => 1,
                    'box_number' => $box->box_number,
                    'order' => count($reservedLocations) + 1,
                    'is_not_full' => (bool) $box->is_not_full,
                    'is_reserved' => true,
                ];
                $reservedTotal += (int) $box->pcs_quantity;
            }
        }

        $remainingQty = max(0, $requestedQty - $reservedTotal);
        $plannedQty = $reservedTotal;
        $isPartial = false;

        if (!$orderId) {
            // Get total available stock (exclude reserved boxes)
            $totalStock = $this->getTotalStockForPart($partNumber, true);

            if ($totalStock < $requestedQty) {
                return response()->json([
                    'success' => false,
                    'message' => "Stok tidak cukup! Available: {$totalStock} PCS, Requested: {$requestedQty} PCS",
                    'available' => $totalStock,
                    'requested' => $requestedQty,
                ], 422);
            }

            $locations = $this->getLocationsByFIFO($partNumber, $requestedQty, true, true);
            $plannedQty = (int) collect($locations)->sum('will_take_pcs');
            if ($plannedQty < $requestedQty) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tersedia, namun kombinasi box tidak memenuhi kuantitas exact. Butuh box not full.',
                    'available' => $totalStock,
                    'requested' => $requestedQty,
                ], 422);
            }
        } else {
            // Only take from non-assigned boxes for the remaining qty.
            // Assigned boxes are already included in $reservedLocations.
            $locations = $remainingQty > 0
                ? $this->getLocationsByFIFO($partNumber, $remainingQty, true, true)
                : [];

            $plannedQty += (int) collect($locations)->sum('will_take_pcs');
            $isPartial = $allowPartial && $remainingQty > 0 && $plannedQty < $requestedQty;

            if ($remainingQty > 0 && $plannedQty < $remainingQty && !$allowPartial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak cukup untuk sisa kebutuhan. Butuh box not full.',
                    'available' => $plannedQty,
                    'requested' => $requestedQty,
                ], 422);
            }

            if ($remainingQty > 0 && $plannedQty <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak tersedia untuk pengiriman ini.',
                    'available' => 0,
                    'requested' => $requestedQty,
                ], 422);
            }
        }

        $locations = array_merge($reservedLocations, $locations);

        return response()->json([
            'success' => true,
            'part_number' => $partNumber,
            'requested_qty' => $requestedQty,
            'planned_qty' => $plannedQty,
            'is_partial' => $isPartial,
            'total_available' => $orderId ? ($reservedTotal + $this->getTotalStockForPart($partNumber, true)) : $this->getTotalStockForPart($partNumber, true),
            'locations' => $locations,
        ]);
    }

    /**
     * Confirm and execute withdrawal
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $partNumber = $request->input('part_number');
        $requestedQty = (int) $request->input('pcs_quantity');
        $notes = $request->input('notes');

        if (!$this->findExactPartSetting($partNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'No Part tidak ditemukan di Master Part.',
            ], 422);
        }

        try {
            $withdrawals = DB::transaction(function () use ($partNumber, $requestedQty, $notes, $request) {
                $batchId = Str::uuid();
                $allowPartial = $request->boolean('allow_partial', false);
                $withdrawals = [];
                $deliveryItem = null;
                $deliveryOrderItemId = (int) $request->input('delivery_order_item_id');
                if ($deliveryOrderItemId > 0) {
                    $deliveryItem = DeliveryOrderItem::whereKey($deliveryOrderItemId)
                        ->lockForUpdate()
                        ->first();
                }

                $deliveryOrderId = (int) $request->input('delivery_order_id');
                if ($deliveryOrderId <= 0 && $deliveryItem) {
                    $deliveryOrderId = (int) $deliveryItem->delivery_order_id;
                }
                if ($deliveryOrderId > 0 && $this->hasPendingAdditionalNotFullRequestForOrder($deliveryOrderId, true)) {
                    throw new \RuntimeException(self::DELIVERY_APPROVAL_PENDING_MESSAGE);
                }

                $selectedBoxes = $this->selectBoxesForWithdrawal($partNumber, $requestedQty, $deliveryOrderId > 0 ? $deliveryOrderId : null);

                foreach ($selectedBoxes as $selectedBox) {
                    $box = Box::whereKey($selectedBox->id)->lockForUpdate()->first();
                    if (!$box || $box->is_withdrawn) {
                        continue;
                    }

                    $pallet = $box->pallets()->whereHas('stockLocation')->first();
                    if (!$pallet) {
                        continue;
                    }

                    $palletItem = PalletItem::where('pallet_id', $pallet->id)
                        ->where('part_number', $box->part_number)
                        ->lockForUpdate()
                        ->first();

                    $withdrawal = StockWithdrawal::create([
                        'withdrawal_batch_id' => $batchId,
                        'user_id' => Auth::id(),
                        'pallet_item_id' => $palletItem?->id,
                        'box_id' => $box->id,
                        'part_number' => $box->part_number,
                        'pcs_quantity' => (int) $box->pcs_quantity,
                        'box_quantity' => 1,
                        'warehouse_location' => $pallet->stockLocation?->warehouse_location ?? 'Unknown',
                        'status' => 'completed',
                        'notes' => $notes,
                        'withdrawn_at' => now(),
                    ]);

                    $withdrawals[] = $withdrawal;

                    $box->is_withdrawn = true;
                    $box->withdrawn_at = now();
                    $box->save();

                    if ($palletItem) {
                        $palletItem->pcs_quantity = max(0, (int) $palletItem->pcs_quantity - (int) $box->pcs_quantity);
                        $palletItem->box_quantity = max(0, (int) $palletItem->box_quantity - 1);
                        $palletItem->save();
                    }
                    $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->lockForUpdate()->first();
                    if ($masterLocation) {
                        $masterLocation->autoVacateIfEmpty();
                    }
                }

                if ($deliveryItem) {
                    $deliveryItem->fulfilled_quantity = (int) $deliveryItem->fulfilled_quantity + $requestedQty;
                    $deliveryItem->save();

                    $order = DeliveryOrder::whereKey($deliveryItem->delivery_order_id)
                        ->lockForUpdate()
                        ->first();

                    if ($order) {
                        $orderItems = $order->items()->lockForUpdate()->get();
                        $isComplete = true;
                        foreach ($orderItems as $checkItem) {
                            if ((int) $checkItem->fulfilled_quantity < (int) $checkItem->quantity) {
                                $isComplete = false;
                                break;
                            }
                        }

                        if ($isComplete) {
                            $order->status = 'completed';
                            $order->save();
                        } elseif ($order->status !== 'processing') {
                            $order->status = 'processing';
                            $order->save();
                        }
                    }
                }

                return $withdrawals;
            });

            return response()->json([
                'success' => true,
                'message' => "Pengambilan stok berhasil! {$requestedQty} PCS {$partNumber} telah diambil",
                'withdrawals' => $withdrawals,
            ]);
        } catch (\Throwable $e) {
            $statusCode = str_contains((string) $e->getMessage(), self::DELIVERY_APPROVAL_PENDING_MESSAGE) ? 423 : 500;
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Store withdrawal - process multiple items from cart
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.part_number' => 'required|string',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $invalidPart = $this->findInvalidMasterPartNumber($items);
        if ($invalidPart !== null) {
            return response()->json([
                'success' => false,
                'message' => "No Part {$invalidPart} tidak ditemukan di Master Part.",
            ], 422);
        }
        try {
            DB::transaction(function () use ($items) {
                $batchId = Str::uuid();

                foreach ($items as $cartItem) {
                    $partNumber = (string) $cartItem['part_number'];
                    $requestedQty = (int) $cartItem['pcs_quantity'];

                    $selectedBoxes = $this->selectBoxesForWithdrawal($partNumber, $requestedQty, null);

                    foreach ($selectedBoxes as $selectedBox) {
                        $box = Box::whereKey($selectedBox->id)->lockForUpdate()->first();
                        if (!$box || $box->is_withdrawn) {
                            continue;
                        }

                        $pallet = $box->pallets()->whereHas('stockLocation')->first();
                        if (!$pallet) {
                            continue;
                        }

                        $palletItem = PalletItem::where('pallet_id', $pallet->id)
                            ->where('part_number', $box->part_number)
                            ->lockForUpdate()
                            ->first();

                        StockWithdrawal::create([
                            'withdrawal_batch_id' => $batchId,
                            'user_id' => Auth::id(),
                            'pallet_item_id' => $palletItem?->id,
                            'box_id' => $box->id,
                            'part_number' => $box->part_number,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                            'box_quantity' => 1,
                            'warehouse_location' => $pallet->stockLocation?->warehouse_location ?? 'Unknown',
                            'status' => 'completed',
                            'notes' => null,
                            'withdrawn_at' => now(),
                        ]);

                        $box->is_withdrawn = true;
                        $box->withdrawn_at = now();
                        $box->save();

                        if ($palletItem) {
                            $palletItem->pcs_quantity = max(0, (int) $palletItem->pcs_quantity - (int) $box->pcs_quantity);
                            $palletItem->box_quantity = max(0, (int) $palletItem->box_quantity - 1);
                            $palletItem->save();
                        }

                        $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->lockForUpdate()->first();
                        if ($masterLocation) {
                            $masterLocation->autoVacateIfEmpty();
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Pengambilan stok dari ' . count($items) . ' part berhasil diproses',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview locations for multiple items from cart
     */
    public function previewCart(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.part_number' => 'required|string',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $invalidPart = $this->findInvalidMasterPartNumber($items);
        if ($invalidPart !== null) {
            return response()->json([
                'success' => false,
                'message' => "No Part {$invalidPart} tidak ditemukan di Master Part.",
            ], 422);
        }
        $previewData = [];
        $partNumbers = collect($items)->pluck('part_number')->filter()->values();
        $totals = $this->getTotalsByPartNumbers($partNumbers->all());

        try {
            foreach ($items as $cartItem) {
                $partNumber = $cartItem['part_number'];
                $requestedQty = $cartItem['pcs_quantity'];

                // Get total available stock
                $totalStock = (int) ($totals[$partNumber] ?? 0);

                if ($totalStock < $requestedQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok tidak cukup untuk part {$partNumber}! Available: {$totalStock} PCS, Requested: {$requestedQty} PCS",
                    ], 422);
                }

                // Get locations in FIFO order
                $locations = $this->getLocationsByFIFO($partNumber, $requestedQty, true, true);
                $plannedQty = (int) collect($locations)->sum('will_take_pcs');
                if ($plannedQty < $requestedQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok tersedia, namun kombinasi box part {$partNumber} tidak memenuhi kuantitas exact. Butuh box not full.",
                    ], 422);
                }

                $previewData[] = [
                    'part_number' => $partNumber,
                    'requested_qty' => $requestedQty,
                    'total_available' => $totalStock,
                    'locations' => $locations,
                ];
            }

            return response()->json([
                'success' => true,
                'preview' => $previewData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Undo a withdrawal
     */
    public function undo($withdrawalId)
    {
        try {
            $result = DB::transaction(function () use ($withdrawalId) {
                $withdrawal = StockWithdrawal::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();

                if ($withdrawal->status === 'reversed') {
                    return [
                        'success' => false,
                        'message' => 'Withdrawal ini sudah di-undo sebelumnya',
                        'status' => 422,
                    ];
                }

                $batchId = $withdrawal->withdrawal_batch_id;
                $batchWithdrawals = StockWithdrawal::where('withdrawal_batch_id', $batchId)
                    ->where('status', 'completed')
                    ->lockForUpdate()
                    ->get();

                $palletItemIds = $batchWithdrawals
                    ->pluck('pallet_item_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $palletItemsById = $palletItemIds->isEmpty()
                    ? collect()
                    : PalletItem::whereIn('id', $palletItemIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                $boxIds = $batchWithdrawals
                    ->pluck('box_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $boxesById = $boxIds->isEmpty()
                    ? collect()
                    : Box::whereIn('id', $boxIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                foreach ($batchWithdrawals as $batchWithdrawal) {
                    if (!$batchWithdrawal instanceof StockWithdrawal) {
                        continue;
                    }

                    if ($batchWithdrawal->pallet_item_id) {
                        $palletItem = $palletItemsById->get((int) $batchWithdrawal->pallet_item_id);

                        if ($palletItem) {
                            $palletItem->pcs_quantity = (int) $palletItem->pcs_quantity + (int) $batchWithdrawal->pcs_quantity;
                            $palletItem->box_quantity = (int) $palletItem->box_quantity + (int) $batchWithdrawal->box_quantity;
                            $palletItem->save();
                        }
                    }

                    $restoredPalletId = null;

                    if ($batchWithdrawal->pallet_item_id) {
                        $palletItem = $palletItemsById->get((int) $batchWithdrawal->pallet_item_id);
                        if ($palletItem) {
                            $restoredPalletId = (int) $palletItem->pallet_id;
                        }
                    }

                    if ($batchWithdrawal->box_id) {
                        $box = $boxesById->get((int) $batchWithdrawal->box_id);
                        if ($box) {
                            $box->is_withdrawn = false;
                            $box->withdrawn_at = null;
                            $box->save();

                            if (!$restoredPalletId) {
                                $pallet = $box->pallets()->select('pallets.id')->first();
                                $restoredPalletId = $pallet ? (int) $pallet->id : null;
                            }
                        }
                    }

                    if ($restoredPalletId && !empty($batchWithdrawal->warehouse_location) && $batchWithdrawal->warehouse_location !== 'Unknown') {
                        $masterLocation = MasterLocation::where('code', $batchWithdrawal->warehouse_location)
                            ->lockForUpdate()
                            ->first();

                        if ($masterLocation) {
                            $masterLocation->is_occupied = true;
                            $masterLocation->current_pallet_id = $restoredPalletId;
                            $masterLocation->save();
                        }
                    }

                    $batchWithdrawal->status = 'reversed';
                    $batchWithdrawal->save();
                }

                return [
                    'success' => true,
                    'message' => 'Withdrawal berhasil di-undo',
                    'status' => 200,
                ];
            });

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? null,
            ], (int) ($result['status'] ?? 200));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get total stock for a part number (exclude items with 0 quantity)
     */
    private function getTotalStockForPart($partNumber, bool $excludeAssigned = false)
    {
        $boxTotalQuery = DB::table('boxes')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->when($excludeAssigned, function ($q) {
                $q->whereNull('boxes.assigned_delivery_order_id');
            });

        $boxTotal = $this->applyStoredLocationExistsFilter($boxTotalQuery)
            ->sum('boxes.pcs_quantity');

        $legacyTotal = PalletItem::where('part_number', $partNumber)
            ->where('pcs_quantity', '>', 0)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->sum('pcs_quantity');

        return (int) $boxTotal + (int) $legacyTotal;
    }

    /**
     * Get total stock for multiple part numbers
     */
    private function getTotalsByPartNumbers(array $partNumbers): array
    {
        $partNumbers = array_values(array_unique(array_filter($partNumbers)));
        if (empty($partNumbers)) {
            return [];
        }

        $boxTotalsQuery = DB::table('boxes')
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->whereIn('boxes.part_number', $partNumbers)
            ->groupBy('boxes.part_number')
            ->select('boxes.part_number', DB::raw('SUM(boxes.pcs_quantity) as total'));

        $boxTotals = $this->applyStoredLocationExistsFilter($boxTotalsQuery)
            ->pluck('total', 'boxes.part_number');

        $legacyTotals = PalletItem::select('part_number', DB::raw('SUM(pcs_quantity) as total'))
            ->whereIn('part_number', $partNumbers)
            ->where('pcs_quantity', '>', 0)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->groupBy('part_number')
            ->pluck('total', 'part_number');

        $totals = [];
        foreach ($partNumbers as $partNumber) {
            $totals[$partNumber] = (int) ($boxTotals[$partNumber] ?? 0) + (int) ($legacyTotals[$partNumber] ?? 0);
        }

        return $totals;
    }

    /**
     * Get warehouse locations in FIFO order
     */
    private function getLocationsByFIFO($partNumber, $requestedQty, bool $excludeAssigned = false, bool $strictRemaining = false)
    {
        $locations = [];
        $remainingQty = $requestedQty;

        $storedPivotSubquery = $this->getStoredLocationPivotSubquery();

        $boxRows = DB::table('boxes')
            ->joinSub($storedPivotSubquery, 'stored_box', function ($join) {
                $join->on('stored_box.box_id', '=', 'boxes.id');
            })
            ->join('pallet_boxes as pb', 'pb.id', '=', 'stored_box.pivot_id')
            ->join('pallets', 'pallets.id', '=', 'pb.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->whereNotIn('boxes.id', function ($q) {
                $q->select('box_id')
                    ->from('delivery_pick_items')
                    ->whereIn('pick_session_id', function ($q2) {
                        $q2->select('id')
                            ->from('delivery_pick_sessions')
                            ->whereIn('status', ['scanning', 'blocked', 'approved']);
                    });
            })
            ->when($excludeAssigned, function ($q) {
                $q->whereNull('boxes.assigned_delivery_order_id');
            })
            ->orderBy('boxes.created_at', 'asc')
            ->select([
                'boxes.id as box_id',
                'boxes.box_number',
                'boxes.pcs_quantity',
                'boxes.is_not_full',
                'boxes.created_at as stored_at',
                'pallets.id as pallet_id',
                'pallets.pallet_number',
                'stock_locations.warehouse_location',
            ])
            ->get();

        foreach ($boxRows as $box) {
            if ($remainingQty <= 0) {
                break;
            }

            if ($strictRemaining && (int) $box->pcs_quantity > $remainingQty) {
                continue;
            }

            $takeQty = min($remainingQty, (int) $box->pcs_quantity);

            $locations[] = [
                'pallet_id' => $box->pallet_id,
                'pallet_number' => $box->pallet_number,
                'warehouse_location' => $box->warehouse_location,
                'stored_date' => $box->stored_at ? Carbon::parse($box->stored_at)->format('d/m/Y H:i') : '-',
                'available_pcs' => (int) $box->pcs_quantity,
                'available_box' => 1,
                'pcs_per_box' => (int) $box->pcs_quantity,
                'will_take_pcs' => $takeQty,
                'will_take_box' => 1,
                'box_number' => $box->box_number,
                'order' => count($locations) + 1,
                'is_not_full' => (bool) $box->is_not_full,
                'is_reserved' => false,
            ];

            $remainingQty -= $takeQty;
        }

        if (count($locations) === 0) {
            $palletItems = PalletItem::where('part_number', $partNumber)
                ->where('pcs_quantity', '>', 0)
                ->whereHas('pallet', function ($q) {
                    $q->whereHas('stockLocation')
                      ->doesntHave('boxes');
                })
                ->with(['pallet' => function ($q) {
                    $q->with('stockLocation');
                }])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($palletItems as $item) {
                if ($remainingQty <= 0) {
                    break;
                }

                if ($item->pcs_quantity <= 0) {
                    continue;
                }

                $takeQty = min($remainingQty, $item->pcs_quantity);
                $stockLocation = $item->pallet->stockLocation;
                $pcsPerBox = $item->box_quantity > 0 ? $item->pcs_quantity / $item->box_quantity : 0;
                $boxesToTake = $pcsPerBox > 0 ? floor($takeQty / $pcsPerBox) : 0;

                $locations[] = [
                    'pallet_id' => $item->pallet->id,
                    'pallet_number' => $item->pallet->pallet_number,
                    'box_number' => '-',
                    'warehouse_location' => $stockLocation ? $stockLocation->warehouse_location : 'Unknown',
                    'stored_date' => $stockLocation ? $stockLocation->stored_at->format('d/m/Y H:i') : '-',
                    'available_pcs' => $item->pcs_quantity,
                    'available_box' => $item->box_quantity,
                    'pcs_per_box' => $pcsPerBox,
                    'will_take_pcs' => $takeQty,
                    'will_take_box' => $boxesToTake,
                    'order' => count($locations) + 1,
                ];

                $remainingQty -= $takeQty;
            }
        }

        return $locations;
    }

    private function getReservedBoxesForOrder(int $orderId, string $partNumber, $deliveryDate = null, bool $lockRows = false)
    {
        $query = Box::query()
            ->with(['pallets.stockLocation'])
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
            ->where('boxes.assigned_delivery_order_id', $orderId)
            ->whereNotIn('boxes.id', function ($q) {
                $q->select('box_id')
                    ->from('delivery_pick_items')
                    ->whereIn('pick_session_id', function ($q2) {
                        $q2->select('id')
                            ->from('delivery_pick_sessions')
                            ->whereIn('status', ['scanning', 'blocked', 'approved']);
                    });
            })
            ->orderBy('boxes.created_at', 'asc');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Get withdrawal history
     */
    public function history()
    {
        $withdrawals = StockWithdrawal::with(['user', 'palletItem'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('operator.stock-withdrawal.history', [
            'withdrawals' => $withdrawals,
        ]);
    }
}
