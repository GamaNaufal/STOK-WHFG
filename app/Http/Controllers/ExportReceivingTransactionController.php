<?php

namespace App\Http\Controllers;

use App\Models\ExportPlannedPallet;
use App\Models\ExportReceivingBatch;
use App\Models\ExportReceivingBatchItem;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Services\ExportPlacementGuideService;
use App\Services\ExportReceivingTransactionService;
use App\Services\ExportStockViewService;
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

    public function recommendation(ExportStockAllocationNotice $notice, ExportPlannedPallet $pallet, ExportReceivingTransactionService $service, ExportStockViewService $view)
    {
        $boxes = $service->recommendationBoxes($notice, $pallet);
        abort_if($boxes->isEmpty(), 404, 'Rekomendasi tidak lagi aktif. Periksa antrean terbaru.');
        $notice->load('palletPlan.deliveryPlan');
        $parameters = ['notice' => $notice->id, 'pallet' => $pallet->id];

        return $this->detail($boxes, [
            'code' => $service::code('recommendation', $notice->id, $pallet->id),
            'received_at' => $notice->acknowledged_at ?? $notice->created_at,
            'operator' => \App\Models\User::find($notice->acknowledged_by ?? $notice->created_by)?->name ?? 'Operator',
            'notice_id' => $notice->id, 'pallet_id' => $pallet->id,
            'print_url' => route('export.receiving.transactions.recommendation-print', $parameters),
            'detail_url' => route('export.receiving.transactions.recommendation', $parameters),
            'delivery' => $notice->palletPlan->deliveryPlan,
        ], $view, null);
    }

    private function detail($boxes, array $receipt, ExportStockViewService $view, ?ExportReceivingBatch $batch, ?int $returnBox = null)
    {
        $groups = $view->groups($boxes, empty($receipt['notice_id']));
        $receipt['total'] = $boxes->count();
        $receipt['pending'] = $groups->sum(fn ($group) => $group['pending']->count());
        $receipt['redirected'] = $groups->sum(fn ($group) => $group['redirected']->count());
        $receipt['placed'] = $receipt['total'] - $receipt['pending'] - $receipt['redirected'];
        $needsChoices = $groups->contains(fn ($group) => ! $group['planned'] && $group['pending']->isNotEmpty());
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

    public function printRecommendation(Request $request, ExportStockAllocationNotice $notice, ExportPlannedPallet $pallet, ExportReceivingTransactionService $service, ExportStockViewService $view, ExportPlacementGuideService $guide)
    {
        return $this->printGuide($request, $this->recommendation($notice, $pallet, $service, $view)->getData(), $guide);
    }

    private function printGuide(Request $request, array $data, ExportPlacementGuideService $guide)
    {
        $input = $request->validate(['spare_destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/'],
            'spare_placements_json' => 'nullable|string|max:1048576']);
        $assignments = [];
        if (isset($input['spare_placements_json'])) {
            try {
                $rows = json_decode($input['spare_placements_json'], true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages(['spare_placements' => 'Pembagian tujuan cetak tidak dapat dibaca.']);
            }
            $validated = validator(['spare_placements' => $rows], [
                'spare_placements' => 'required|array|list|max:1000', 'spare_placements.*.id' => 'required|integer|min:1|distinct',
                'spare_placements.*.destination' => ['present', 'nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/'],
            ])->validate();
            $assignments = $validated['spare_placements'];
            $allowed = $data['groups']->filter(fn ($group) => ! $group['planned'])->flatMap(fn ($group) => $group['pending'])->pluck('id');
            if (collect($assignments)->contains(fn ($row) => ! $allowed->contains((int) $row['id']))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['spare_placements' => 'Pembagian cetak hanya boleh memuat box cadangan yang masih menunggu pada transaksi ini. Muat ulang instruksi terbaru.']);
            }
        }

        return response()->view('export.receiving.print-guide', $data + $guide->prepare($data, $input['spare_destination'] ?? null, $assignments))
            ->header('Cache-Control', 'private, no-store');
    }
}
