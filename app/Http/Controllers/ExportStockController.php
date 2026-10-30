<?php

namespace App\Http\Controllers;

use App\Models\ExportStockBox;
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
        $data = $request->validate(['box' => 'nullable|integer|min:1']);
        $search = $filters['q']; $status = $filters['status'];
        $selected = !empty($data['box']) ? ExportStockBox::with($view::RELATIONS)->findOrFail($data['box']) : null;
        $pendingCount = $view->pendingQuery()->count();
        $boxes = $stock->boxes($filters)->paginate(20, ['*'], 'stock_page')->withQueryString();
        $filterPallets = $stock->palletOptions();
        $filterLocations = $filterPallets->whereNotNull('master_location_id')->unique('master_location_id');
        $storedCount = ExportStockBox::where('state', 'stored')->count();
        $choices = $selected && !$selected->fulfillment ? $view->choices() : ['pallets' => collect(), 'locations' => collect()];
        $selectedLoad = $selected?->fulfillment ? $view->groups(collect([$selected]))->first()['load'] : null;
        return view('export.stock.index', compact('selected', 'selectedLoad', 'pendingCount', 'boxes', 'storedCount', 'search', 'status', 'filters', 'filterPallets', 'filterLocations') + $choices);
    }

    public function export(Request $request, ExportStockQueryService $stock)
    {
        $query = $stock->boxes($stock->filters($request))
            ->with('fulfillment.plannedItem.pallet.palletPlan.deliveryPlan');

        $response = Excel::download(new ExportStockExport($query), 'stok_ekspor_'.now()->format('Ymd_His').'.xlsx');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
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
