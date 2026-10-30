<?php

namespace App\Http\Controllers;

use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Exports\ExportStockExport;
use App\Services\ExportStockQueryService;
use App\Services\ExportStockViewService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportStockController extends Controller
{
    public function index(Request $request, ExportStockViewService $view, ExportStockQueryService $stock)
    {
        $filters = $stock->filters($request);
        $boxes = $palletGroups = null;
        if ($filters['mode'] === 'pallet') {
            $palletGroups = $stock->groups($filters)->paginate(10, ['*'], 'stock_page')->withQueryString();
            $resultCount = $stock->boxes($filters)->count();
        } else {
            $boxes = $stock->boxes($filters)->paginate(20, ['*'], 'stock_page')->withQueryString();
            $resultCount = $boxes->total();
        }
        $hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['location_id'];
        $results = compact('filters', 'boxes', 'palletGroups', 'resultCount', 'hasFilters');
        if ($request->expectsJson()) {
            return response()->json(['html' => view('export.stock.results', $results)->render(), 'count' => $resultCount])
                ->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
        }

        $data = $request->validate(['box' => 'nullable|integer|min:1']);
        $search = $filters['q']; $status = $filters['status'];
        $selected = !empty($data['box']) ? ExportStockBox::with($view::RELATIONS)->findOrFail($data['box']) : null;
        $pendingCount = $view->pendingQuery()->count();
        $filterLocations = $stock->locationOptions();
        $storedCount = ExportStockBox::where('state', 'stored')->count();
        $choices = $selected && !$selected->fulfillment ? $view->choices() : ['pallets' => collect(), 'locations' => collect()];
        $selectedLoad = $selected?->fulfillment ? $view->groups(collect([$selected]))->first()['load'] : null;
        return response()->view('export.stock.index', $results + compact('selected', 'selectedLoad', 'pendingCount', 'storedCount', 'search', 'status', 'filterLocations') + $choices)
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
    }

    public function export(Request $request, ExportStockQueryService $stock)
    {
        $filters = $stock->filters($request);
        $query = $stock->boxes($filters)
            ->with('fulfillment.plannedItem.pallet.palletPlan.deliveryPlan');
        if ($filters['mode'] === 'pallet') {
            $query->reorder()->orderByRaw('CASE WHEN current_pallet_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('current_pallet_id')->orderBy('first_received_at')->orderBy('id');
        }

        $response = Excel::download(new ExportStockExport($query), 'stok_ekspor_'.now()->format('Ymd_His').'.xlsx');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    public function palletBoxes(Request $request, string $pallet, ExportStockQueryService $stock)
    {
        $filters = $stock->filters($request);
        $physicalPallet = $pallet === 'unplaced' ? null : ExportStockPallet::with('plannedPallet.palletPlan.deliveryPlan')->findOrFail($pallet);
        $boxes = $stock->boxes($filters)->where('current_pallet_id', $physicalPallet?->id)
            ->paginate(20, ['*'], 'box_page')->withQueryString();
        $data = compact('boxes', 'physicalPallet', 'filters');
        if (!$request->expectsJson()) return response()->view('export.stock.pallet-detail', $data + ['selected' => null])
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
        return response()->json(['html' => view('export.stock.pallet-modal-content', $data)->render()])
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
    }

    public function show(ExportStockBox $stockBox, ExportStockViewService $view)
    {
        $stockBox->load($view::RELATIONS);
        $choices = !$stockBox->fulfillment ? $view->choices() : ['pallets' => collect(), 'locations' => collect()];
        $selectedLoad = $stockBox->fulfillment ? $view->groups(collect([$stockBox]))->first()['load'] : null;
        return response()->json(['html' => view('export.stock.box-detail', ['selected' => $stockBox, 'selectedLoad' => $selectedLoad] + $choices)->render()])
            ->header('Cache-Control', 'private, no-store');
    }
}
