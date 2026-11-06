<?php

namespace App\Http\Controllers;

use App\Models\{ExportDeliveryPlan, ExportMasterLocation, ExportPlannedPallet, ExportStockAllocationNotice, PartSetting};
use App\Services\{ExportBoxRequirements, ExportFulfillmentCompletionService, ExportFulfillmentRevisionService, ExportStockViewService};
use Illuminate\Http\Request;

class ExportFulfillmentActionController extends Controller
{
    private function pallet(ExportDeliveryPlan $delivery, ExportPlannedPallet $pallet): void
    {
        $plan = $delivery->palletPlan;
        abort_unless($plan && $plan->isFinalFor($delivery) && (int) $pallet->export_pallet_plan_id === $plan->id, 404);
    }

    private function versions(Request $request): array
    {
        return $request->validate(['revision' => 'required|integer|min:1', 'fulfillment_revision' => 'required|integer|min:1']);
    }

    public function readyForm(ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        abort_if($pallet->ready_at, 409, 'Pallet sudah siap. Muat ulang halaman.');
        [, $items, $commitments] = $service->contents($pallet);
        $locations = ExportMasterLocation::where(fn ($q) => $q->whereKey($pallet->master_location_id)
            ->orWhere(fn ($free) => $free->available()->whereNull('current_pallet_id')->whereDoesntHave('stockLocations')->whereDoesntHave('stockPallet')))->orderBy('code')->get();
        return response()->view('export.fulfillment.ready-modal', ['delivery' => $deliveryPlan, 'pallet' => $pallet,
            'items' => $items, 'locations' => $locations, 'commitments' => $commitments])->header('Cache-Control', 'private, no-store');
    }

    public function ready(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        $data = $this->versions($request) + $request->validate(['location_id' => 'required|integer|min:1', 'request_token' => 'required|uuid', 'placement_confirmed' => 'accepted']);
        $service->ready($deliveryPlan->id, $pallet->id, $data, $request->user());
        return redirect()->route('export.fulfillment.show', $deliveryPlan)->with('success', 'Pallet dikonfirmasi siap. Isi pallet dikunci.');
    }

    private function revisionInputs(Request $request): array
    {
        $max = ExportFulfillmentRevisionService::MAX_QUANTITY;

        return $request->validate(['quantities' => 'required|array|min:1|max:5000', 'quantities.*' => "required|integer|min:0|max:$max",
            'additions' => 'sometimes|array|max:5000', 'additions.*' => "required|integer|min:1|max:$max"]);
    }

    public function revise(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        [, $items, $commitments] = app(ExportFulfillmentRevisionService::class)->contents($pallet);
        $quantities = $items->pluck('box_quantity', 'id')->all();
        $additions = [];
        $preview = null;
        if ($request->isMethod('post')) {
            $versions = $this->versions($request);
            abort_unless($versions['revision'] == $deliveryPlan->palletPlan->revision && $versions['fulfillment_revision'] == $pallet->fulfillment_revision, 409, 'Pallet berubah. Muat ulang revisi.');
            $data = $this->revisionInputs($request);
            $quantities = $data['quantities'];
            $additions = $data['additions'] ?? [];
            if (! $request->boolean('edit')) {
                $preview = $service->reduction($pallet, $quantities, $additions);
            }
        }

        $choices = $preview && $preview['returns']->isNotEmpty() ? app(ExportStockViewService::class)->choices() : [];
        if ($choices) {
            $choices['pallets'] = $choices['pallets']->whereNull('export_planned_pallet_id');
        }
        $catalog = collect();
        if (! $preview) {
            $existing = $items->keyBy(fn ($row) => ExportBoxRequirements::key($row->part_setting_id, $row->pcs_per_box));
            $catalog = PartSetting::where('qty_box', '>', 0)->orderBy('part_number')->get(['id', 'part_number', 'qty_box'])
                ->reject(fn ($part) => $existing->has(ExportBoxRequirements::key($part->id, (int) $part->qty_box)))->values();
        }
        $pendingFifo = ExportStockAllocationNotice::where('export_pallet_plan_id', $deliveryPlan->palletPlan->id)
            ->where('plan_revision', $deliveryPlan->palletPlan->revision)->whereNull('acknowledged_at')
            ->whereHas('boxes', fn ($query) => $query->whereNull('placed_at'))->exists();

        return response()->view('export.fulfillment.revise', ['delivery' => $deliveryPlan, 'pallet' => $pallet,
            'items' => $items, 'reserved' => $commitments->countBy('export_planned_pallet_item_id'),
            'placed' => $commitments->whereNotNull('placed_at')->countBy('export_planned_pallet_item_id'),
            'quantities' => $quantities, 'additions' => $additions, 'preview' => $preview, 'catalog' => $catalog,
            'pendingFifo' => $pendingFifo] + $choices)->header('Cache-Control', 'private, no-store');
    }

    public function reduce(Request $request, int $deliveryPlan, int $pallet, ExportFulfillmentCompletionService $service)
    {
        $data = $this->versions($request) + $this->revisionInputs($request) + $request->validate([
            'fingerprint' => 'required|string|size:64', 'destination' => ['nullable', 'required_if:return_confirmed,1', 'regex:/^(location|pallet):[1-9][0-9]*$/'],
            'request_token' => 'required|uuid', 'return_confirmed' => 'sometimes|accepted']);
        $service->reduce($deliveryPlan, $pallet, $data, $request->user());

        return (ExportDeliveryPlan::find($deliveryPlan)
            ? redirect()->route('export.fulfillment.show', $deliveryPlan)
            : redirect()->route('export.fulfillment.index'))
            ->with('success', 'Revisi pallet dan rencana delivery disimpan. Cek antrean untuk tugas FIFO baru dan konfirmasi kembali pallet yang direvisi setelah kebutuhannya terpenuhi.');
    }

    public function cancelForm(ExportDeliveryPlan $deliveryPlan, ExportFulfillmentCompletionService $service)
    {
        abort_unless($deliveryPlan->palletPlan?->isFinalFor($deliveryPlan), 404);
        $preview = $service->cancellation($deliveryPlan);
        $choices = app(ExportStockViewService::class)->choices();
        $choices['pallets'] = $choices['pallets']->whereNull('export_planned_pallet_id');
        return response()->view('export.fulfillment.cancel', ['delivery' => $deliveryPlan, 'preview' => $preview] + $choices)
            ->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, int $deliveryPlan, ExportFulfillmentCompletionService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'fingerprint' => 'required|string|size:64',
            'reason' => 'required|string|max:500', 'request_token' => 'required|uuid', 'return_confirmed' => 'sometimes|accepted',
            'destination' => ['nullable', 'required_if:return_confirmed,1', 'regex:/^(location|pallet):[1-9][0-9]*$/']]);
        $service->cancel($deliveryPlan, $data, $request->user());
        return redirect()->route('export.fulfillment.index')->with('success', 'Permintaan dibatalkan. Reservasi dan lokasi dilepas; box tetap sebagai stok cadangan dan riwayat tersimpan.');
    }

    public function reopen(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        $data = $this->versions($request) + $request->validate(['request_token' => 'required|uuid']);
        $service->reopen($deliveryPlan->id, $pallet->id, $data, $request->user());
        return redirect()->route('export.fulfillment.show', $deliveryPlan)->with('success', 'Kesiapan pallet dibuka ulang. Konfirmasikan kembali lokasi dan isi pallet sebelum pengiriman.');
    }

    public function complete(Request $request, int $deliveryPlan, ExportFulfillmentCompletionService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'request_token' => 'required|uuid', 'shipped_confirmed' => 'accepted']);
        $service->complete($deliveryPlan, $data, $request->user());
        return redirect()->route('export.fulfillment.index')->with('success', 'Permintaan selesai. Seluruh box sudah keluar gudang, lokasi dibebaskan, dan riwayat tersimpan di audit trail.');
    }
}
