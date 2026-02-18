<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Box;
use App\Models\PalletItem;
use App\Models\PartSetting;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    private function buildFifoPoolsByPart(): array
    {
        $boxRows = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.is_withdrawn', false)
            ->whereNull('boxes.assigned_delivery_order_id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->select('boxes.part_number', 'boxes.pcs_quantity', 'boxes.is_not_full', 'boxes.created_at')
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
    private function getStrictFulfillableForOrder(int $orderId, string $partNumber, int $requiredQty): int
    {
        $reservedBoxes = Box::query()
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where('boxes.assigned_delivery_order_id', $orderId)
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.pcs_quantity')
            ->get();

        $reservedTotal = (int) $reservedBoxes->sum('pcs_quantity');
        $remaining = max(0, $requiredQty - $reservedTotal);

        if ($remaining <= 0) {
            return $reservedTotal;
        }

        $fifoBoxes = Box::query()
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->whereNull('boxes.assigned_delivery_order_id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.pcs_quantity')
            ->get();

        $fifoTotal = 0;
        foreach ($fifoBoxes as $box) {
            if ($remaining <= 0) {
                break;
            }
            $boxQty = (int) $box->pcs_quantity;
            if ($boxQty > $remaining) {
                continue;
            }
            $fifoTotal += $boxQty;
            $remaining -= $boxQty;
        }

        return $reservedTotal + $fifoTotal;
    }
    private function getAvailableStockByPart(): array
    {
        $boxTotals = Box::select('boxes.part_number', DB::raw('SUM(boxes.pcs_quantity) as total'))
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->where('boxes.is_withdrawn', false)
            ->whereNull('boxes.assigned_delivery_order_id')
            ->groupBy('boxes.part_number')
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
        $rows = Box::select(
                'boxes.assigned_delivery_order_id',
                'boxes.part_number',
                DB::raw('SUM(boxes.pcs_quantity) as total')
            )
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->where('boxes.is_withdrawn', false)
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

    // Dashboard: Only Approved Schedule (Visible to Admin & Warehouse)
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
           // Strict Role Check: Admin & Warehouse Operator only
                 if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse', 'ppc'], true)) {
             if ($user->role === 'sales') return redirect()->route('delivery.create');
             return redirect('/')->with('error', 'Unauthorized access to Schedule.');
        }

        $approvedOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'processing']) 
            ->orderBy('delivery_date', 'asc')
            ->get();

        // Precompute available stock per part (PCS)
        $availableByPart = $this->getAvailableStockByPart();
        $reservedByOrder = $this->getReservedStockByOrder();

        $fifoPools = $this->buildFifoPoolsByPart();

        $partSettings = PartSetting::pluck('qty_box', 'part_number');

        $approvedOrders->each(function ($order) use ($availableByPart, $reservedByOrder, $partSettings, &$fifoPools) {
            $allAvailable = true;

            $today = now()->timezone(config('app.timezone'))->startOfDay();
            $deliveryDate = \Carbon\Carbon::parse($order->delivery_date)->timezone(config('app.timezone'))->startOfDay();
            $order->days_remaining = $today->diffInDays($deliveryDate, false);

            $order->items->each(function ($item) use ($availableByPart, $reservedByOrder, $order, $partSettings, &$fifoPools, &$allAvailable) {
                $partNumber = $item->part_number;
                $reservedForOrder = (int) ($reservedByOrder[$order->id][$partNumber] ?? 0);
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

                while ($remainingNeeded > 0 && count($pool) > 0) {
                    $box = $pool[0];
                    $nextBoxQty = is_array($box) ? (int) $box['qty'] : (int) $box;
                    $isNotFull = is_array($box) ? (bool) $box['is_not_full'] : false;
                    
                    // Allow taking box if:
                    // 1. Box qty <= remaining (normal case)
                    // 2. Box is not_full (can be taken even if larger, to fulfill remainder)
                    if ($nextBoxQty <= $remainingNeeded || $isNotFull) {
                        $takenFromFifo += $nextBoxQty;
                        $remainingNeeded -= $nextBoxQty;
                        array_shift($pool);
                    } else {
                        // Box is too large and not marked as not_full, skip it
                        break;
                    }
                }

                $fifoPools[$partNumber] = $pool;

                $strictFulfillable = min($requiredQty, $reservedForOrder + $takenFromFifo);
                $item->display_fulfilled = $strictFulfillable;
                $item->is_fulfillable = $strictFulfillable >= $requiredQty;
                $item->needs_not_full = $needsNotFull;

                if (!$item->is_fulfillable) {
                    $allAvailable = false;
                }
            });

            $order->has_sufficient_stock = $allAvailable;
        });

        $completedOrders = \App\Models\DeliveryPickSession::with('order')
            ->where('completion_status', 'completed')
            ->where('redo_until', '>=', now())
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get();

        $historyOrders = \App\Models\DeliveryPickSession::with('order.items')
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

        $deletedOrders = \App\Models\DeliveryOrder::withTrashed()
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

    // Sales Page: Input Form & History
    public function createOrder()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!in_array($user->role, ['sales', 'admin', 'ppc'])) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        if ($user->role === 'ppc') {
            return redirect()->route('delivery.index');
        }

        if (in_array($user->role, ['sales', 'admin'])) {
            $myOrders = \App\Models\DeliveryOrder::with(['items'])
                ->where('sales_user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        } else {
            $myOrders = \App\Models\DeliveryOrder::with(['items'])
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
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->role !== 'ppc' && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        $pendingOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->where('status', 'pending')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedOrders = \App\Models\DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Precompute available stock per part (PCS) - same logic as delivery schedule
        $availableByPart = $this->getAvailableStockByPart();
        $reservedByOrder = $this->getReservedStockByOrder();

        $sequenceOrders = $approvedOrders->concat($pendingOrders)->sort(function ($a, $b) {
            $dateA = \Carbon\Carbon::parse($a->delivery_date)->startOfDay();
            $dateB = \Carbon\Carbon::parse($b->delivery_date)->startOfDay();

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

        $historyOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'rejected', 'correction', 'processing', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        return view('operator.delivery.approvals', compact('pendingOrders', 'historyOrders'));
    }

    // Fetch order items for fulfill modal
    public function fulfillData($id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = \App\Models\DeliveryOrder::with('items')->findOrFail($id);

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
            'delivery_date' => \Carbon\Carbon::parse($order->delivery_date)->format('d M Y'),
            'items' => $items,
        ]);
    }

    // Sales: Edit correction request
    public function edit($id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $order = \App\Models\DeliveryOrder::with(['items'])->findOrFail($id);

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
        $user = \Illuminate\Support\Facades\Auth::user();
        $order = \App\Models\DeliveryOrder::with('items')->findOrFail($id);

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
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $order->customer_name = $request->customer_name;
            $order->delivery_date = $request->delivery_date;
            $order->status = 'pending';

            if ($request->notes) {
                $order->notes = ($order->notes ? $order->notes . "\n[Sales]: " : "[Sales]: ") . $request->notes;
            }

            $order->save();

            // Replace items
            $order->items()->delete();
            foreach ($request->items as $item) {
                \App\Models\DeliveryOrderItem::create([
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
        $user = \Illuminate\Support\Facades\Auth::user();
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

        DB::beginTransaction();
        try {
            // Check if updating existing (resubmit correction) or new
            // Implementation note: User didn't ask for edit/resubmit UI yet, just status flow.
            // For now, assume this creates NEW. Editing requires ID.
            
            $order = \App\Models\DeliveryOrder::create([
                'sales_user_id' => \Illuminate\Support\Facades\Auth::id(),
                'customer_name' => $request->customer_name,
                'delivery_date' => $request->delivery_date,
                'status' => 'pending',
                'notes' => $request->notes ?? null
            ]);

            foreach ($request->items as $item) {
                \App\Models\DeliveryOrderItem::create([
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
        $user = \Illuminate\Support\Facades\Auth::user();
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

        $order = \App\Models\DeliveryOrder::findOrFail($id);
        $order->status = $request->status;
        
        // Append PPC notes if provided
        if($request->notes) {
            $order->notes = ($order->notes ? $order->notes . "\n[PPC]: " : "[PPC]: ") . $request->notes;
        }

        $order->save();

        $msg = 'Order status updated to ' . ucfirst($request->status);
        if($request->status == 'correction') $msg .= '. Sent back to Sales.';

        return redirect()->back()->with('success', $msg);
    }

    public function destroy($id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->role !== 'admin') {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $order = \App\Models\DeliveryOrder::findOrFail($id);
        $order->status = 'deleted';
        $order->save();
        $order->delete();

        return redirect()->back()->with('success', 'Delivery schedule deleted.');
    }

    public function approvalImpact($id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!in_array($user->role, ['ppc', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = \App\Models\DeliveryOrder::with('items')->findOrFail($id);

        $availableByPart = $this->getAvailableStockByPart();

        $approvedOrders = \App\Models\DeliveryOrder::with('items')
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
            $dateA = \Carbon\Carbon::parse($a->delivery_date)->startOfDay();
            $dateB = \Carbon\Carbon::parse($b->delivery_date)->startOfDay();

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
