<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryIssue;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\NotFullBoxRequest;
use App\Models\PalletItem;
use App\Models\PartSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
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

    private function getActivePickLockedBoxIds(): array
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

    private function getActivePickLockedStockByOrderPart(): array
    {
        $rows = DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->whereIn('delivery_pick_sessions.status', self::ACTIVE_LOCK_STATUSES)
            ->select('delivery_pick_sessions.delivery_order_id', 'delivery_pick_items.part_number', DB::raw('SUM(delivery_pick_items.pcs_quantity) as total'))
            ->groupBy('delivery_pick_sessions.delivery_order_id', 'delivery_pick_items.part_number')
            ->get();

        $locked = [];
        foreach ($rows as $row) {
            $locked[(int) $row->delivery_order_id][(string) $row->part_number] = (int) $row->total;
        }

        return $locked;
    }

    private function buildFifoPoolsByPart(): array
    {
        $lockedBoxIds = $this->getActivePickLockedBoxIds();

        $boxRowsQuery = DB::table('boxes')
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNull('boxes.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('boxes.id', $lockedBoxIds);
            })
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.part_number', 'boxes.pcs_quantity', 'boxes.is_not_full', 'boxes.created_at');

        $boxRows = $this->applyStoredLocationExistsFilter($boxRowsQuery)
            ->get();

        $legacyRows = PalletItem::where('pcs_quantity', '>', 0)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->select('part_number', 'pcs_quantity', 'created_at')
            ->get();

        $combined = [];
        foreach ($boxRows as $row) {
            $combined[] = [
                'part_number' => $row->part_number,
                'pcs_quantity' => (int) $row->pcs_quantity,
                'is_not_full' => (bool) $row->is_not_full,
                'created_at' => $row->created_at,
            ];
        }

        foreach ($legacyRows as $row) {
            $combined[] = [
                'part_number' => $row->part_number,
                'pcs_quantity' => (int) $row->pcs_quantity,
                'is_not_full' => false,
                'created_at' => $row->created_at,
            ];
        }

        usort($combined, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });

        $pools = [];
        foreach ($combined as $row) {
            $pools[$row['part_number']][] = [
                'qty' => (int) $row['pcs_quantity'],
                'is_not_full' => $row['is_not_full'],
            ];
        }

        return $pools;
    }
    private function getAvailableStockByPart(): array
    {
        $lockedBoxIds = $this->getActivePickLockedBoxIds();

        $boxTotals = $this->applyStoredLocationExistsFilter(DB::table('boxes')
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNull('boxes.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('boxes.id', $lockedBoxIds);
            })
            ->select('boxes.part_number', DB::raw('SUM(boxes.pcs_quantity) as total'))
            ->groupBy('boxes.part_number'))
            ->pluck('total', 'boxes.part_number');

        $legacyTotals = PalletItem::select('part_number', DB::raw('SUM(pcs_quantity) as total'))
            ->where('pcs_quantity', '>', 0)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->groupBy('part_number')
            ->pluck('total', 'part_number');

        $availableByPart = $boxTotals->toArray();
        foreach ($legacyTotals as $partNumber => $total) {
            $availableByPart[$partNumber] = ($availableByPart[$partNumber] ?? 0) + (int) $total;
        }

        return $availableByPart;
    }

    private function getReservedStockByOrder(): array
    {
        $rows = DB::table('boxes')
            ->select(
                'boxes.assigned_delivery_order_id',
                'boxes.part_number',
                DB::raw('SUM(boxes.pcs_quantity) as total')
            )
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNotNull('boxes.assigned_delivery_order_id')
            ->groupBy('boxes.assigned_delivery_order_id', 'boxes.part_number')
            ->get();

        $reserved = [];
        foreach ($rows as $row) {
            $orderId = (int) $row->assigned_delivery_order_id;
            $partNumber = $row->part_number;
            $reserved[$orderId][$partNumber] = (int) $row->total;
        }

        return $reserved;
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

    // Dashboard: Only Approved Schedule (Visible to Admin & Warehouse)
    public function index()
    {
        $user = Auth::user();
        
           // Strict Role Check: Admin & Warehouse Operator only
                 if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse', 'ppc'], true)) {
             if ($user->role === 'sales') return redirect()->route('delivery.create');
             return redirect('/')->with('error', 'Unauthorized access to Schedule.');
        }

        $approvedOrders = DeliveryOrder::with(['items', 'salesUser'])
            ->withCount(['childDeliveryOrders as split_children_count'])
            ->whereIn('status', ['approved', 'processing', 'partial'])
            ->orderBy('delivery_date', 'asc')
            ->get();

        $currentUserId = (int) Auth::id();

        $globalActiveSession = DeliveryPickSession::query()
            ->with('creator')
            ->withCount('items')
            ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $pendingAdditionalApprovalOrderIds = NotFullBoxRequest::query()
            ->where('request_type', 'additional')
            ->where('status', 'pending')
            ->whereIn('delivery_order_id', $approvedOrders->pluck('id')->all())
            ->pluck('delivery_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $pendingAdditionalApprovalByOrderId = array_fill_keys($pendingAdditionalApprovalOrderIds, true);

        // Precompute available stock per part (PCS)
        $availableByPart = $this->getAvailableStockByPart();
        $reservedByOrder = $this->getReservedStockByOrder();
        $activeLockedByOrderPart = $this->getActivePickLockedStockByOrderPart();

        $fifoPools = $this->buildFifoPoolsByPart();

        $partSettings = PartSetting::pluck('qty_box', 'part_number');

        $approvedOrders->each(function ($order) use ($availableByPart, $reservedByOrder, $activeLockedByOrderPart, $partSettings, &$fifoPools, $pendingAdditionalApprovalByOrderId, $globalActiveSession, $currentUserId) {
            $allAvailable = true;
            $hasAnyFulfillable = false;
            $hasPendingAdditionalApproval = !empty($pendingAdditionalApprovalByOrderId[(int) $order->id]);

            $today = now()->startOfDay();
            $deliveryDate = Carbon::parse($order->delivery_date)->startOfDay();
            $order->days_remaining = $today->diffInDays($deliveryDate, false);

            $order->items->each(function ($item) use ($availableByPart, $reservedByOrder, $activeLockedByOrderPart, $order, $partSettings, &$fifoPools, &$allAvailable) {
                $partNumber = $item->part_number;
                $reservedForOrder = (int) ($reservedByOrder[$order->id][$partNumber] ?? 0);
                $lockedForOrder = (int) ($activeLockedByOrderPart[$order->id][$partNumber] ?? 0);
                $reservedForOrder += $lockedForOrder;
                $available = (int) ($availableByPart[$partNumber] ?? 0) + $reservedForOrder;

                $item->available_total = $available;
                $requiredQty = (int) $item->quantity;
                $remainingNeeded = max(0, $requiredQty - $reservedForOrder);

                $takenFromFifo = 0;
                $pool = $fifoPools[$partNumber] ?? [];
                $needsNotFull = false;

                if ($remainingNeeded > 0 && isset($partSettings[$partNumber])) {
                    $fixedQty = (int) $partSettings[$partNumber];
                    if ($fixedQty > 0) {
                        $fullBoxesRequired = intdiv($remainingNeeded, $fixedQty);
                        $remainder = $remainingNeeded % $fixedQty;
                        $availableFullBoxes = 0;
                        $hasNotFullBoxForRemainder = false;
                        
                        foreach ($pool as $box) {
                            $qty = is_array($box) ? (int) $box['qty'] : (int) $box;
                            $isNotFull = is_array($box) ? (bool) $box['is_not_full'] : false;
                            
                            if ($qty === $fixedQty) {
                                $availableFullBoxes++;
                            } elseif ($remainder > 0 && ($isNotFull || $qty < $fixedQty)) {
                                // Found a not-full box or partial box that can help with remainder
                                $hasNotFullBoxForRemainder = true;
                            }
                        }
                        
                        // Only need not-full if there's remainder AND we have full boxes but NO not-full box
                        $needsNotFull = $remainder > 0 && $availableFullBoxes >= $fullBoxesRequired && !$hasNotFullBoxForRemainder;
                    }
                }

                $poolIndex = 0;
                while ($remainingNeeded > 0 && isset($pool[$poolIndex])) {
                    $box = $pool[$poolIndex];
                    $nextBoxQty = is_array($box) ? (int) $box['qty'] : (int) $box;
                    $isNotFull = is_array($box) ? (bool) $box['is_not_full'] : false;
                    
                    // Allow taking box if:
                    // 1. Box qty <= remaining (normal case)
                    // 2. Box is not_full (can be taken even if larger, to fulfill remainder)
                    if ($nextBoxQty <= $remainingNeeded || $isNotFull) {
                        $takenFromFifo += $nextBoxQty;
                        $remainingNeeded -= $nextBoxQty;
                        array_splice($pool, $poolIndex, 1);
                    } else {
                        // Box is too large and not marked as not_full, keep it for later orders
                        $poolIndex++;
                    }
                }

                $fifoPools[$partNumber] = $pool;

                $strictFulfillable = min($requiredQty, $reservedForOrder + $takenFromFifo);
                $item->display_fulfilled = $strictFulfillable;
                $item->is_fulfillable = $strictFulfillable >= $requiredQty;
                $item->needs_not_full = $needsNotFull;

                if ($strictFulfillable > 0) {
                    $hasAnyFulfillable = true;
                }

                if (!$item->is_fulfillable) {
                    $allAvailable = false;
                }
            });

            $order->has_pending_additional_approval = $hasPendingAdditionalApproval;
            $order->readiness_reason = $hasPendingAdditionalApproval
                ? 'Pending approval not full tambahan'
                : null;
            $order->has_sufficient_stock = $allAvailable && !$hasPendingAdditionalApproval;
            $order->has_partial_stock = $hasAnyFulfillable && !$allAvailable && !$hasPendingAdditionalApproval;

            $hasGlobalLock = $globalActiveSession !== null;
            $isActiveOrder = $hasGlobalLock && (int) $globalActiveSession->delivery_order_id === (int) $order->id;
            $isOwner = $isActiveOrder && (int) $globalActiveSession->created_by === $currentUserId;
            $ownerName = $hasGlobalLock ? trim((string) optional($globalActiveSession->creator)->name) : '';

            $order->has_active_pick_session = $isActiveOrder;
            $order->active_pick_owned_by_current_user = $isOwner;
            $order->active_pick_owner_name = $ownerName !== '' ? $ownerName : '-';
            $order->global_pick_lock_active = $hasGlobalLock;
            $order->global_pick_lock_order_id = $hasGlobalLock ? (int) $globalActiveSession->delivery_order_id : null;
            $order->is_split_child = !empty($order->parent_delivery_order_id);
            $order->has_split_children = ((int) ($order->split_children_count ?? 0)) > 0;
            $order->can_split_delivery = !$order->is_split_child
                && in_array($order->status, ['approved', 'partial'], true)
                && empty($order->has_active_pick_session)
                && empty($order->has_pending_additional_approval)
                && $order->items->contains(fn ($item) => (int) $item->quantity > 1);
            $order->split_disabled_reason = null;

            if (!$order->can_split_delivery) {
                if ($order->is_split_child) {
                    $order->split_disabled_reason = 'Order ini adalah hasil split dan tidak bisa di-split lagi.';
                } elseif (!in_array($order->status, ['approved', 'partial'], true)) {
                    $order->split_disabled_reason = 'Split hanya tersedia untuk order berstatus approved atau partial.';
                } elseif (!empty($order->has_active_pick_session)) {
                    $order->split_disabled_reason = 'Order sedang diproses, split sementara tidak bisa dilakukan.';
                } elseif (!empty($order->has_pending_additional_approval)) {
                    $order->split_disabled_reason = 'Masih ada pending approval not full tambahan.';
                } elseif (!$order->items->contains(fn ($item) => (int) $item->quantity > 1)) {
                    $order->split_disabled_reason = 'Tidak ada part dengan qty lebih dari 1 untuk di-split.';
                } else {
                    $order->split_disabled_reason = 'Order ini belum memenuhi syarat split.';
                }
            }
            $order->can_restore_split = $order->is_split_child && $order->status !== 'deleted';

            if ($isOwner) {
                $order->active_pick_resume_url = route('delivery.pick.scan', [$order->id, $globalActiveSession->id]);
            } else {
                $order->active_pick_resume_url = null;
            }
        });

        $completedOrders = DeliveryPickSession::with('order')
            ->where('completion_status', 'completed')
            ->where('redo_until', '>=', now())
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get();

        $historyOrders = DeliveryPickSession::with('order.items')
            ->where(function ($q) {
                $q->where('completion_status', 'redone')
                  ->orWhere(function ($q2) {
                      $q2->where('completion_status', 'completed')
                         ->where('redo_until', '<', now());
                  });
            })
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->get();

        $deletedOrders = DeliveryOrder::withTrashed()
            ->with('items')
            ->where('status', 'deleted')
            ->orderBy('deleted_at', 'desc')
            ->limit(50)
            ->get();

        $historyRows = collect();

        foreach ($historyOrders as $history) {
            $historyRows->push((object) [
                'order' => $history->order,
                'completed_at' => $history->completed_at,
                'status_label' => $history->completion_status === 'redone' ? 'Redone' : 'Expired',
            ]);
        }

        foreach ($deletedOrders as $order) {
            $historyRows->push((object) [
                'order' => $order,
                'completed_at' => $order->deleted_at,
                'status_label' => 'Deleted',
            ]);
        }

        $historyRows = $historyRows->sortByDesc('completed_at')->values();

        return view('operator.delivery.index', compact('approvedOrders', 'completedOrders', 'historyRows'));
    }

    public function split(Request $request, $id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'admin_warehouse'], true)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($id, $validated) {
                $order = DeliveryOrder::with(['items'])->lockForUpdate()->findOrFail($id);

                if (!empty($order->parent_delivery_order_id)) {
                    throw new \RuntimeException('Delivery hasil split tidak dapat di-split lagi.');
                }

                if (!in_array($order->status, ['approved', 'partial'], true)) {
                    throw new \RuntimeException('Delivery hanya bisa di-split dari status approved atau partial.');
                }

                $hasActivePickSession = DeliveryPickSession::where('delivery_order_id', (int) $order->id)
                    ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
                    ->lockForUpdate()
                    ->exists();

                if ($hasActivePickSession) {
                    throw new \RuntimeException('Delivery sedang diproses dan tidak bisa di-split.');
                }

                $itemsToSplit = $validated['items'];
                // Validate each requested part exists and quantity is valid
                $childItems = [];
                foreach ($itemsToSplit as $entry) {
                    $partNumber = trim((string) ($entry['part_number'] ?? ''));
                    $splitQuantity = (int) ($entry['quantity'] ?? 0);

                    $sourceItem = $order->items->firstWhere('part_number', $partNumber);
                    if (!$sourceItem) {
                        throw new \RuntimeException("Part {$partNumber} tidak ditemukan pada delivery ini.");
                    }

                    $currentQuantity = (int) $sourceItem->quantity;
                    if ($splitQuantity <= 0 || $splitQuantity >= $currentQuantity) {
                        throw new \RuntimeException("Qty split untuk part {$partNumber} harus antara 1 dan " . ($currentQuantity - 1) . ".");
                    }

                    $childItems[] = ['part_number' => $partNumber, 'quantity' => $splitQuantity, 'source_item' => $sourceItem];
                }

                // Create child order with selected parts
                $childOrder = DeliveryOrder::create([
                    'parent_delivery_order_id' => $order->id,
                    'sales_user_id' => $order->sales_user_id,
                    'customer_name' => $order->customer_name,
                    'delivery_date' => $order->delivery_date,
                    'status' => 'approved',
                    'notes' => $order->notes,
                ]);

                foreach ($childItems as $ci) {
                    /** @var \App\Models\DeliveryOrderItem $src */
                    $src = $ci['source_item'];
                    $splitQuantity = (int) $ci['quantity'];

                    // Reduce parent item
                    $src->quantity = (int) $src->quantity - $splitQuantity;
                    $src->save();

                    // Create child item
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $childOrder->id,
                        'part_number' => $ci['part_number'],
                        'quantity' => $splitQuantity,
                        'fulfilled_quantity' => 0,
                    ]);
                }

                if ($order->status !== 'partial') {
                    $order->status = 'partial';
                    $order->save();
                }
            });

            return redirect()->back()->with('success', 'Delivery berhasil di-split.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function restoreSplit($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'admin_warehouse'], true)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            DB::transaction(function () use ($id) {
                $childOrder = DeliveryOrder::with(['items'])->lockForUpdate()->findOrFail($id);

                if (empty($childOrder->parent_delivery_order_id)) {
                    throw new \RuntimeException('Delivery ini bukan hasil split.');
                }

                if (in_array($childOrder->status, ['deleted', 'completed'], true)) {
                    throw new \RuntimeException('Delivery ini tidak bisa dikembalikan.');
                }

                $parentOrder = DeliveryOrder::with(['items'])->lockForUpdate()->findOrFail((int) $childOrder->parent_delivery_order_id);

                $hasActivePickSession = DeliveryPickSession::where('delivery_order_id', (int) $childOrder->id)
                    ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
                    ->lockForUpdate()
                    ->exists();

                if ($hasActivePickSession) {
                    throw new \RuntimeException('Delivery hasil split sedang diproses dan tidak bisa dikembalikan.');
                }

                $childItem = $childOrder->items->first();
                if (!$childItem) {
                    throw new \RuntimeException('Item split tidak ditemukan.');
                }

                $parentItem = $parentOrder->items->firstWhere('part_number', $childItem->part_number);
                if ($parentItem) {
                    $parentItem->quantity = (int) $parentItem->quantity + (int) $childItem->quantity;
                    $parentItem->save();
                } else {
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $parentOrder->id,
                        'part_number' => $childItem->part_number,
                        'quantity' => (int) $childItem->quantity,
                        'fulfilled_quantity' => 0,
                    ]);
                }

                $childOrder->status = 'deleted';
                $childOrder->save();
                $childOrder->delete();

                $remainingActiveChildren = DeliveryOrder::query()
                    ->where('parent_delivery_order_id', (int) $parentOrder->id)
                    ->whereNull('deleted_at')
                    ->exists();

                $parentOrder->status = $remainingActiveChildren ? 'partial' : 'approved';
                $parentOrder->save();
            });

            return redirect()->back()->with('success', 'Delivery split berhasil dikembalikan ke delivery utama.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // Sales Page: Input Form & History
    public function createOrder()
    {
        $user = Auth::user();
        if (!in_array($user->role, ['sales', 'admin', 'ppc'])) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        if ($user->role === 'ppc') {
            return redirect()->route('delivery.index');
        }

        if (in_array($user->role, ['sales', 'admin'])) {
            $myOrders = DeliveryOrder::with(['items'])
                ->where('sales_user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        } else {
            $myOrders = DeliveryOrder::with(['items'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        }

        $partNumbers = PartSetting::orderBy('part_number', 'asc')
            ->pluck('part_number');

        return view('operator.delivery.create', compact('myOrders', 'partNumbers'));
    }

    // PPC Page: Pending Approvals
    public function pendingApprovals()
    {
        $user = Auth::user();
        if ($user->role !== 'ppc' && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        $pendingOrders = DeliveryOrder::with(['items', 'salesUser'])
            ->where('status', 'pending')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedOrders = DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Precompute available stock per part (PCS) - same logic as delivery schedule
        $availableByPart = $this->getAvailableStockByPart();
        $reservedByOrder = $this->getReservedStockByOrder();

        $sequenceOrders = $approvedOrders->concat($pendingOrders)->sort(function ($a, $b) {
            $dateA = Carbon::parse($a->delivery_date)->startOfDay();
            $dateB = Carbon::parse($b->delivery_date)->startOfDay();

            if ($dateA->equalTo($dateB)) {
                return $a->created_at < $b->created_at ? 1 : -1;
            }

            return $dateA->lessThan($dateB) ? -1 : 1;
        })->values();

        $runningRequired = [];

        $sequenceOrders->each(function ($order) use ($availableByPart, $reservedByOrder, &$runningRequired) {
            $order->items->each(function ($item) use ($availableByPart, $reservedByOrder, $order, &$runningRequired) {
                $partNumber = $item->part_number;
                $reservedForOrder = (int) ($reservedByOrder[$order->id][$partNumber] ?? 0);
                $available = (int) ($availableByPart[$partNumber] ?? 0) + $reservedForOrder;

                $runningRequired[$partNumber] = ($runningRequired[$partNumber] ?? 0) + (int) $item->quantity;

                $previousRequired = $runningRequired[$partNumber] - (int) $item->quantity;
                $remainingBefore = $available - $previousRequired;

                $item->available_total = $available;
                $item->remaining_before = max(0, $remainingBefore);
                $item->display_fulfilled = max(0, min((int) $item->quantity, $remainingBefore));
                $item->available_stock = $item->display_fulfilled;
                $item->stock_warning = $remainingBefore < (int) $item->quantity;
            });
        });

        $historyOrders = DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'rejected', 'correction', 'processing', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        return view('operator.delivery.approvals', compact('pendingOrders', 'historyOrders'));
    }

    // Fetch order items for fulfill modal
    public function fulfillData($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = DeliveryOrder::with('items')->findOrFail($id);
        $hasPendingAdditionalApproval = NotFullBoxRequest::query()
            ->where('delivery_order_id', (int) $order->id)
            ->where('request_type', 'additional')
            ->where('status', 'pending')
            ->exists();
        $blockedReason = $hasPendingAdditionalApproval
            ? 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.'
            : null;

        $items = $order->items->map(function ($item) {
            $remaining = max(0, $item->quantity - $item->fulfilled_quantity);
            return [
                'id' => $item->id,
                'part_number' => $item->part_number,
                'required' => $item->quantity,
                'fulfilled' => $item->fulfilled_quantity,
                'remaining' => $remaining,
            ];
        })->values();

        return response()->json([
            'order_id' => $order->id,
            'customer_name' => $order->customer_name,
            'delivery_date' => Carbon::parse($order->delivery_date)->format('d M Y'),
            'items' => $items,
            'is_blocked' => $hasPendingAdditionalApproval,
            'blocked_reason' => $blockedReason,
        ]);
    }

    // Sales: Edit correction request
    public function edit($id)
    {
        $user = Auth::user();
        $order = DeliveryOrder::with(['items'])->findOrFail($id);

        if ($user->role !== 'sales' && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        if ($order->sales_user_id !== $user->id && $user->role !== 'admin') {
            return redirect()->route('delivery.create')->with('error', 'Order ini bukan milik Anda.');
        }

        if ($order->status !== 'correction') {
            return redirect()->route('delivery.create')->with('error', 'Order tidak dalam status koreksi.');
        }

        $partNumbers = PartSetting::orderBy('part_number', 'asc')
            ->pluck('part_number');

        return view('operator.delivery.edit', compact('order', 'partNumbers'));
    }

    // Sales: Update correction request
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $order = DeliveryOrder::with('items')->findOrFail($id);

        if ($user->role !== 'sales' && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        if ($order->sales_user_id !== $user->id && $user->role !== 'admin') {
            return redirect()->route('delivery.create')->with('error', 'Order ini bukan milik Anda.');
        }

        $request->validate([
            'customer_name' => 'required|string',
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        $invalidPart = $this->findInvalidMasterPartNumber($request->input('items', []));
        if ($invalidPart !== null) {
            return redirect()->back()
                ->with('error', "No Part {$invalidPart} tidak ditemukan di Master Part.")
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $order->customer_name = $request->customer_name;
            $order->delivery_date = $request->delivery_date;
            $order->status = 'pending';
            $order->notes = null;

            $order->save();

            // Replace items
            $order->items()->delete();
            foreach ($request->items as $item) {
                DeliveryOrderItem::create([
                    'delivery_order_id' => $order->id,
                    'part_number' => $item['part_number'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return redirect()->route('delivery.create')->with('success', 'Perbaikan berhasil dikirim ulang ke PPC.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error update order: ' . $e->getMessage());
        }
    }

    // Sales: Store new Delivery Order
    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'sales' && $user->role !== 'admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'customer_name' => 'required|string',
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $invalidPart = $this->findInvalidMasterPartNumber($request->input('items', []));
        if ($invalidPart !== null) {
            return redirect()->back()
                ->with('error', "No Part {$invalidPart} tidak ditemukan di Master Part.")
                ->withInput();
        }

        DB::beginTransaction();
        try {
            // Check if updating existing (resubmit correction) or new
            // Implementation note: User didn't ask for edit/resubmit UI yet, just status flow.
            // For now, assume this creates NEW. Editing requires ID.
            
            $order = DeliveryOrder::create([
                'sales_user_id' => Auth::id(),
                'customer_name' => $request->customer_name,
                'delivery_date' => $request->delivery_date,
                'status' => 'pending',
                'notes' => null
            ]);

            foreach ($request->items as $item) {
                DeliveryOrderItem::create([
                    'delivery_order_id' => $order->id,
                    'part_number' => $item['part_number'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Delivery Order submitted to PPC.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error creating order: ' . $e->getMessage());
        }
    }

    // PPC: Approve, Reject, or Correction
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'ppc' && $user->role !== 'admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'status' => 'required|in:approved,rejected,correction',
            'notes' => 'nullable|string'
        ]);

        if ($request->status === 'approved' && trim((string) $request->notes) === '') {
            return redirect()->back()
                ->withErrors(['notes' => 'Keterangan PPC wajib diisi saat approve.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $id) {
            $order = DeliveryOrder::whereKey($id)->lockForUpdate()->firstOrFail();
            $order->status = $request->status;

            $ppcNotes = trim((string) $request->notes);
            if ($ppcNotes !== '') {
                $order->notes = $ppcNotes;
            }

            $order->save();
        });

        $msg = 'Order status updated to ' . ucfirst($request->status);
        if($request->status == 'correction') $msg .= '. Sent back to Sales.';

        return redirect()->back()->with('success', $msg);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        DB::transaction(function () use ($id, $user) {
            $order = DeliveryOrder::whereKey($id)->lockForUpdate()->firstOrFail();

            Box::where('assigned_delivery_order_id', (int) $order->id)
                ->lockForUpdate()
                ->update([
                    'assigned_delivery_order_id' => null,
                    'updated_at' => now(),
                ]);

            /** @var \Illuminate\Database\Eloquent\Collection<int, DeliveryPickSession> $sessions */
            $sessions = DeliveryPickSession::where('delivery_order_id', (int) $order->id)
                ->lockForUpdate()
                ->get();

            if ($sessions->isNotEmpty()) {
                $sessionIds = $sessions->pluck('id')->map(fn ($sessionId) => (int) $sessionId)->all();

                DeliveryPickItem::whereIn('pick_session_id', $sessionIds)
                    ->lockForUpdate()
                    ->delete();

                DeliveryIssue::whereIn('pick_session_id', $sessionIds)
                    ->where('status', 'pending')
                    ->delete();

                foreach ($sessions as $session) {
                    /** @var DeliveryPickSession $session */
                    if (!in_array((string) $session->status, ['completed', 'cancelled'], true)) {
                        $notes = trim((string) $session->approval_notes);
                        $cancelNote = 'Sesi dibatalkan otomatis karena schedule dihapus oleh ' . ($user->name ?? ('user #' . $user->id)) . '.';

                        $session->status = 'cancelled';
                        $session->verification_box_ids = null;
                        $session->approval_notes = $notes !== '' ? ($notes . ' | ' . $cancelNote) : $cancelNote;
                        $session->save();
                    }
                }
            }

            $order->status = 'deleted';
            $order->save();
            $order->delete();
        });

        return redirect()->back()->with('success', 'Delivery schedule deleted.');
    }

    public function approvalImpact($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['ppc', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = DeliveryOrder::with('items')->findOrFail($id);

        $availableByPart = $this->getAvailableStockByPart();

        $approvedOrders = DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $compute = function ($orders) use ($availableByPart) {
            $runningRequired = [];
            $result = [];

            foreach ($orders as $currentOrder) {
                $fully = true;
                $shortParts = [];

                foreach ($currentOrder->items as $item) {
                    $partNumber = $item->part_number;
                    $available = (int) ($availableByPart[$partNumber] ?? 0);

                    $runningRequired[$partNumber] = ($runningRequired[$partNumber] ?? 0) + (int) $item->quantity;

                    $previousRequired = $runningRequired[$partNumber] - (int) $item->quantity;
                    $remainingBefore = $available - $previousRequired;
                    $displayFulfilled = max(0, min((int) $item->quantity, $remainingBefore));

                    if ($displayFulfilled < (int) $item->quantity) {
                        $fully = false;
                        $shortParts[] = [
                            'part_number' => $partNumber,
                            'can_fulfill' => $displayFulfilled,
                            'required' => (int) $item->quantity,
                        ];
                    }
                }

                $result[$currentOrder->id] = [
                    'fully' => $fully,
                    'short_parts' => $shortParts,
                ];
            }

            return $result;
        };

        $baseline = $compute($approvedOrders);

        $sequenceOrders = $approvedOrders->concat(collect([$order]))->sort(function ($a, $b) {
            $dateA = Carbon::parse($a->delivery_date)->startOfDay();
            $dateB = Carbon::parse($b->delivery_date)->startOfDay();

            if ($dateA->equalTo($dateB)) {
                return $a->created_at < $b->created_at ? 1 : -1;
            }

            return $dateA->lessThan($dateB) ? -1 : 1;
        })->values();

        $after = $compute($sequenceOrders);

        $impacted = [];
        foreach ($approvedOrders as $approvedOrder) {
            $before = $baseline[$approvedOrder->id] ?? ['fully' => false, 'short_parts' => []];
            $afterState = $after[$approvedOrder->id] ?? ['fully' => false, 'short_parts' => []];

            if ($before['fully'] && !$afterState['fully']) {
                $impacted[] = [
                    'id' => $approvedOrder->id,
                    'delivery_date' => $approvedOrder->delivery_date?->format('d M Y') ?? null,
                    'customer_name' => $approvedOrder->customer_name,
                    'short_parts' => $afterState['short_parts'],
                ];
            }
        }

        return response()->json([
            'impacted' => $impacted,
        ]);
    }
}
