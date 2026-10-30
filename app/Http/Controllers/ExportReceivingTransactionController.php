<?php

namespace App\Http\Controllers;

use App\Models\{ExportReceivingBatch, ExportReceivingBatchItem, ExportStockBox};
use App\Services\{ExportPlacementGuideService, ExportReceivingTransactionService, ExportStockViewService};
use Illuminate\Http\Request;

class ExportReceivingTransactionController extends Controller
{
    public function show(ExportReceivingBatch $batch, ExportReceivingTransactionService $service, ExportStockViewService $view)
    {
        abort_unless($batch->status === 'processed', 404);
        $batch->load('creator');
        return $this->detail($service->batchBoxes($batch), [
            'code' => $service::code('batch', $batch->id), 'received_at' => $batch->processed_at ?? $batch->created_at,
            'operator' => $batch->creator?->name ?? 'Operator',
            'print_url' => route('export.receiving.transactions.print', $batch),
            'detail_url' => route('export.receiving.transactions.show', $batch),
        ], $view, $batch);
    }

    public function legacy(ExportStockBox $stockBox, ExportStockViewService $view)
    {
        abort_if(ExportReceivingBatchItem::where('export_stock_box_id', $stockBox->id)->exists(), 404);
        $stockBox->load([...$view::RELATIONS, 'receiver']);
        return $this->detail(collect([$stockBox]), [
            'code' => ExportReceivingTransactionService::code('legacy', $stockBox->id),
            'received_at' => $stockBox->first_received_at, 'operator' => $stockBox->receiver?->name ?? 'Operator',
            'print_url' => route('export.receiving.transactions.legacy-print', $stockBox),
            'detail_url' => route('export.receiving.transactions.legacy', $stockBox),
        ], $view, null, $stockBox->id);
    }

    private function detail($boxes, array $receipt, ExportStockViewService $view, ?ExportReceivingBatch $batch, ?int $returnBox = null)
    {
        $groups = $view->groups($boxes);
        $receipt['total'] = $boxes->count();
        $receipt['pending'] = $groups->sum(fn ($group) => $group['pending']->count());
        $receipt['placed'] = $receipt['total'] - $receipt['pending'];
        $needsChoices = $groups->contains(fn ($group) => !$group['planned'] && $group['pending']->isNotEmpty());
        $choices = $needsChoices ? $view->choices() : ['pallets' => collect(), 'locations' => collect()];
        return view('export.receiving.transaction', compact('batch', 'groups', 'receipt', 'returnBox') + $choices);
    }

    public function printBatch(Request $request, ExportReceivingBatch $batch, ExportReceivingTransactionService $service, ExportStockViewService $view, ExportPlacementGuideService $guide)
    {
        return $this->printGuide($request, $this->show($batch, $service, $view)->getData(), $guide);
    }

    public function printLegacy(Request $request, ExportStockBox $stockBox, ExportStockViewService $view, ExportPlacementGuideService $guide)
    {
        return $this->printGuide($request, $this->legacy($stockBox, $view)->getData(), $guide);
    }

    private function printGuide(Request $request, array $data, ExportPlacementGuideService $guide)
    {
        $input = $request->validate(['spare_destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/']]);
        return response()->view('export.receiving.print-guide', $data + $guide->prepare($data, $input['spare_destination'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }
}
