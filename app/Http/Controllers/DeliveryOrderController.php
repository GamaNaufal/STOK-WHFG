<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryOrderController extends Controller
{
    // Dashboard: Only Approved Schedule (Visible to Admin & Warehouse)
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
           // Strict Role Check: Admin & Warehouse Operator only
           if ($user->role !== 'warehouse_operator' && $user->role !== 'admin') {
             if ($user->role === 'sales') return redirect()->route('delivery.create');
             if ($user->role === 'ppc') return redirect()->route('delivery.approvals');
             return redirect('/')->with('error', 'Unauthorized access to Schedule.');
        }

        $approvedOrders = \App\Models\DeliveryOrder::with(['items', 'salesUser'])
            ->whereIn('status', ['approved', 'processing', 'completed']) 
            ->orderBy('delivery_date', 'asc')
            ->get();

        return view('delivery.index', compact('approvedOrders'));
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

        return view('delivery.create', compact('myOrders'));
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

        return view('delivery.approvals', compact('pendingOrders'));
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
