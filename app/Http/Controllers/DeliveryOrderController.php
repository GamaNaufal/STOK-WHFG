<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Box;
use App\Models\PalletItem;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    // Dashboard: Only Approved Schedule (Visible to Admin & Warehouse)
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
           // Strict Role Check: Admin & Warehouse Operator only
                     if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) {
             if ($user->role === 'sales') return redirect()->route('delivery.create');
             if ($user->role === 'ppc') return redirect()->route('delivery.approvals');
             return redirect('/')->with('error', 'Unauthorized access to Schedule.');
        }

        $approvedOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'processing']) 
            ->orderBy('delivery_date', 'asc')
            ->get();

        // Precompute available stock per part (PCS)
        $boxTotals = Box::select('boxes.part_number', DB::raw('SUM(boxes.pcs_quantity) as total'))
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->where('boxes.is_withdrawn', false)
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

        $runningRequired = [];

        $approvedOrders->each(function ($order) use ($availableByPart, &$runningRequired) {
            $allAvailable = true;

            $today = now()->timezone(config('app.timezone'))->startOfDay();
            $deliveryDate = \Carbon\Carbon::parse($order->delivery_date)->timezone(config('app.timezone'))->startOfDay();
            $order->days_remaining = $today->diffInDays($deliveryDate, false);

            $order->items->each(function ($item) use ($availableByPart, &$runningRequired, &$allAvailable) {
                $partNumber = $item->part_number;
                $available = (int) ($availableByPart[$partNumber] ?? 0);

                $runningRequired[$partNumber] = ($runningRequired[$partNumber] ?? 0) + (int) $item->quantity;

                $previousRequired = $runningRequired[$partNumber] - (int) $item->quantity;
                $remainingBefore = $available - $previousRequired;

                $item->available_total = $available;
                $item->remaining_before = max(0, $remainingBefore);
                $item->display_fulfilled = max(0, min((int) $item->quantity, $remainingBefore));
                $item->is_fulfillable = $remainingBefore >= (int) $item->quantity;

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

        $historyOrders = \App\Models\DeliveryPickSession::with('order')
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

        return view('delivery.index', compact('approvedOrders', 'completedOrders', 'historyOrders'));
    }

    // Sales Page: Input Form & History
    public function createOrder()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->role !== 'sales' && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized access.');
        }

        $myOrders = \App\Models\DeliveryOrder::with(['items'])
            ->where('sales_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $partNumbers = \App\Models\PartSetting::orderBy('part_number', 'asc')
            ->pluck('part_number');

        return view('delivery.create', compact('myOrders', 'partNumbers'));
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
            ->get();

        $historyOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'rejected', 'correction', 'processing', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        return view('delivery.approvals', compact('pendingOrders', 'historyOrders'));
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
            'delivery_date' => $order->delivery_date->format('d M Y'),
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

        return view('delivery.edit', compact('order'));
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

        \Illuminate\Support\Facades\DB::beginTransaction();
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

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->route('delivery.create')->with('success', 'Perbaikan berhasil dikirim ulang ke PPC.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Error update order: ' . $e->getMessage());
        }
    }

    // Sales: Store new Delivery Order
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
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

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->back()->with('success', 'Delivery Order submitted to PPC.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Error creating order: ' . $e->getMessage());
        }
    }

    // PPC: Approve, Reject, or Correction
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,correction',
            'notes' => 'nullable|string'
        ]);

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
}
