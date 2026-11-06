<?php

namespace App\Http\Controllers;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Services\ExportFulfillmentService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportFulfillmentController extends Controller
{
    public function index(Request $request, ExportFulfillmentService $service)
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D'],
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_keys($service::STATES))],
        ]);
        $search = trim($data['q'] ?? '');
        $status = $data['status'] ?? '';
        $today = now()->toImmutable()->startOfDay();
        $month = isset($data['month']) ? $today->setDateFrom($data['month'].'-01') : $today->startOfMonth();
        $lastDay = $month->endOfMonth();

        $plans = $service->overview()
            ->where('delivery_deadline', '>=', $month->toDateString())
            ->when($month->format('Y-m') !== '9999-12', fn ($query) => $query->where('delivery_deadline', '<', $month->addMonth()->toDateString()))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) =>
                $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('plan_code', 'like', '%'.$search.'%')))
            ->when($status !== '', fn ($query) => $query->where('fulfillment_status', $status))
            ->orderBy('delivery_deadline')->orderBy('delivery_id')
            ->get(['delivery_id', 'customer_name', 'plan_code', 'delivery_deadline', 'fulfillment_status']);
        $plansByDate = $plans->groupBy(fn ($plan) => substr($plan->delivery_deadline, 0, 10));

        $days = [];
        for ($date = $month->startOfWeek(CarbonInterface::MONDAY); $date <= $lastDay->endOfWeek(CarbonInterface::SUNDAY); $date = $date->addDay()) {
            $days[] = $date->isSameMonth($month) ? $date : null;
        }
        $weeks = array_chunk($days, 7);

        $calendar = compact('month', 'today', 'weeks', 'plansByDate', 'search', 'status') + ['states' => $service::STATES];
        if ($request->wantsJson()) {
            return response()->json([
                'calendar' => view('export.fulfillment.calendar', $calendar)->render(),
                'month' => $month->format('Y-m'),
                'total' => $plans->count(),
                'suggestions' => $search === '' ? [] : $plans->take(8)->map(fn ($plan) => [
                    'customer' => $plan->customer_name,
                    'code' => $plan->plan_code,
                    'deadline' => substr($plan->delivery_deadline, 0, 10),
                    'url' => route('export.fulfillment.show', $plan->delivery_id),
                ])->all(),
            ])->header('Vary', 'Accept');
        }

        return response()->view('export.fulfillment.index', $calendar)->header('Vary', 'Accept');
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
