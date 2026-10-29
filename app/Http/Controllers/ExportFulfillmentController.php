<?php

namespace App\Http\Controllers;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Services\ExportFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportFulfillmentController extends Controller
{
    public function index(Request $request, ExportFulfillmentService $service)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(array_keys($service::STATES))]]);
        $search = trim($data['q'] ?? '');
        $status = $data['status'] ?? '';
        $query = $service->overview()->when($search !== '', fn ($query) => $query->where(fn ($q) =>
            $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('plan_code', 'like', '%'.$search.'%')));
        $counts = (clone $query)->selectRaw('fulfillment_status, COUNT(*) as total')->groupBy('fulfillment_status')->pluck('total', 'fulfillment_status');
        $plans = $query->when($status !== '', fn ($query) => $query->where('fulfillment_status', $status))
            ->orderBy('delivery_deadline')->orderBy('delivery_id')->paginate(15)->withQueryString();
        foreach ($plans as $plan) $plan->metrics = $service->displayMetrics($plan);
        return view('export.fulfillment.index', compact('plans', 'search', 'status', 'counts') + ['states' => $service::STATES]);
    }

    public function show(Request $request, ExportDeliveryPlan $deliveryPlan, ExportFulfillmentService $service)
    {
        $overview = $service->overview()->where('delivery_id', $deliveryPlan->id)->first();
        abort_unless($overview, 404);
        $deliveryPlan->load('palletPlan.pallets');
        $items = $service->itemMetrics()->where('pallet.export_pallet_plan_id', $overview->plan_id)
            ->orderBy('pallet.position')->orderBy('item.id')->get();
        foreach ($items as $item) $item->metrics = $service->displayMetrics($item);
        $pallets = $deliveryPlan->palletPlan->pallets->map(function ($pallet) use ($items, $service) {
            $rows = $items->where('pallet_id', $pallet->id)->values();
            $total = (object) ['required_boxes' => $rows->sum('required_boxes'), 'fulfilled_boxes' => $rows->sum('fulfilled_boxes'),
                'reserved_boxes' => $rows->sum('reserved_boxes'), 'invalid_boxes' => $rows->sum('invalid_boxes'),
                'remaining_boxes' => $rows->sum(fn ($row) => $row->metrics['remaining']),
                'unreserved_boxes' => $rows->sum(fn ($row) => $row->metrics['unreserved']),
                'covered_boxes' => $rows->sum(fn ($row) => $row->metrics['covered']),
                'excess_boxes' => $rows->sum(fn ($row) => $row->metrics['excess'])];
            return ['pallet' => $pallet, 'items' => $rows, 'metrics' => $service->displayMetrics($total)];
        });
        // Independent pagination prevents a large delivery from rendering every box.
        $boxes = ExportFulfillmentBox::with('plannedItem.pallet', 'part', 'placedLocation', 'placedBy')
            ->whereHas('plannedItem.pallet', fn ($query) => $query->where('export_pallet_plan_id', $overview->plan_id))
            ->orderBy('first_received_at')->orderBy('box_number')->paginate(25)->withQueryString();
        foreach ($boxes as $box) $box->display_status = $service->boxState($box);
        return view('export.fulfillment.show', ['delivery' => $deliveryPlan, 'overview' => $overview,
            'metrics' => $service->displayMetrics($overview), 'pallets' => $pallets, 'boxes' => $boxes,
            'states' => $service::STATES]);
    }
}
