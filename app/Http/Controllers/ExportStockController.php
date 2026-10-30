<?php

namespace App\Http\Controllers;

use App\Models\ExportStockBox;
use App\Services\ExportStockViewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportStockController extends Controller
{
    public function index(Request $request, ExportStockViewService $view)
    {
        $data = $request->validate(['box' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['stored', 'pending', 'spare', 'reserved'])]]);
        $search = trim($data['q'] ?? ''); $status = $data['status'] ?? '';
        $selected = !empty($data['box']) ? ExportStockBox::with($view::RELATIONS)->findOrFail($data['box']) : null;
        $listRelations = ['currentPallet.plannedPallet', 'fulfillment.plannedItem.pallet'];
        $pendingCount = $view->pendingQuery()->count();
        $boxes = ExportStockBox::with($listRelations)->when($search !== '', fn ($q) => $q->where(fn ($s) =>
            $s->where('box_number', 'like', '%'.$search.'%')->orWhere('part_number', 'like', '%'.$search.'%')))
            ->when($status === 'stored', fn ($q) => $q->where('state', 'stored'))
            ->when($status === 'pending', fn ($q) => $q->where(fn ($s) => $s->where('state', 'received')->orWhereHas('fulfillment', fn ($f) => $f->whereNull('placed_at'))))
            ->when($status === 'spare', fn ($q) => $q->where('state', 'stored')->whereDoesntHave('fulfillment'))
            ->when($status === 'reserved', fn ($q) => $q->whereHas('fulfillment'))
            ->orderBy('first_received_at')->orderBy('id')->paginate(20, ['*'], 'stock_page')->withQueryString();
        $storedCount = ExportStockBox::where('state', 'stored')->count();
        $choices = $selected && !$selected->fulfillment ? $view->choices() : ['pallets' => collect(), 'locations' => collect()];
        $selectedLoad = $selected?->fulfillment ? $view->groups(collect([$selected]))->first()['load'] : null;
        return view('export.stock.index', compact('selected', 'selectedLoad', 'pendingCount', 'boxes', 'storedCount', 'search', 'status') + $choices);
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
