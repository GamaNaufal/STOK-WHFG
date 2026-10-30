<?php

namespace App\Http\Controllers;

use App\Models\{ExportDeliveryPlan, ExportMasterLocation, ExportPlannedPallet};
use App\Services\{ExportFulfillmentCompletionService, ExportStockViewService};
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
        [, $items] = $service->contents($pallet);
        $locations = ExportMasterLocation::where(fn ($q) => $q->whereKey($pallet->master_location_id)
            ->orWhere(fn ($free) => $free->available()->whereNull('current_pallet_id')->whereDoesntHave('stockLocations')->whereDoesntHave('stockPallet')))->orderBy('code')->get();
        return response()->view('export.fulfillment.ready-modal', ['delivery' => $deliveryPlan, 'pallet' => $pallet,
            'items' => $items, 'locations' => $locations])->header('Cache-Control', 'private, no-store');
    }

    public function ready(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        $data = $this->versions($request) + $request->validate(['location_id' => 'required|integer|min:1', 'request_token' => 'required|uuid', 'placement_confirmed' => 'accepted']);
        $service->ready($deliveryPlan->id, $pallet->id, $data, $request->user());
        return redirect()->route('export.fulfillment.show', $deliveryPlan)->with('success', 'Pallet dikonfirmasi siap. Isi pallet dikunci.');
    }

    public function revise(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPlannedPallet $pallet, ExportFulfillmentCompletionService $service)
    {
        $this->pallet($deliveryPlan, $pallet);
        [, $items] = app(\App\Services\ExportFulfillmentRevisionService::class)->contents($pallet);
        $quantities = $items->pluck('box_quantity', 'id')->all(); $preview = null;
        if ($request->isMethod('post')) {
            $versions = $this->versions($request);
            abort_unless($versions['revision'] == $deliveryPlan->palletPlan->revision && $versions['fulfillment_revision'] == $pallet->fulfillment_revision, 409, 'Pallet berubah. Muat ulang revisi.');
            $data = $request->validate(['quantities' => 'required|array|min:1|max:5000', 'quantities.*' => 'required|integer|min:0']);
            $quantities = $data['quantities'];
            $preview = $service->reduction($pallet, $quantities);
        }
        $choices = app(ExportStockViewService::class)->choices();
        $choices['pallets'] = $choices['pallets']->reject(fn ($p) => (int) $p->export_planned_pallet_id === $pallet->id);
        return response()->view('export.fulfillment.revise', ['delivery' => $deliveryPlan, 'pallet' => $pallet,
            'items' => $items, 'quantities' => $quantities, 'preview' => $preview] + $choices)->header('Cache-Control', 'private, no-store');
    }

    public function reduce(Request $request, int $deliveryPlan, int $pallet, ExportFulfillmentCompletionService $service)
    {
        $data = $this->versions($request) + $request->validate(['quantities' => 'required|array|min:1|max:5000', 'quantities.*' => 'required|integer|min:0',
            'fingerprint' => 'required|string|size:64', 'destination' => ['nullable', 'required_if:return_confirmed,1', 'regex:/^(location|pallet):[1-9][0-9]*$/'],
            'request_token' => 'required|uuid', 'return_confirmed' => 'sometimes|accepted']);
        $service->reduce($deliveryPlan, $pallet, $data, $request->user());
        return (ExportDeliveryPlan::find($deliveryPlan)
            ? redirect()->route('export.fulfillment.show', $deliveryPlan)
            : redirect()->route('export.fulfillment.index'))
            ->with('success', 'Revisi disimpan. Reservasi dilepas dan box terbaru yang dikembalikan menjadi stok cadangan. Pallet yang tersisa perlu dikonfirmasi siap.');
    }

    public function cancelForm(ExportDeliveryPlan $deliveryPlan, ExportFulfillmentCompletionService $service)
    {
        abort_unless($deliveryPlan->palletPlan?->isFinalFor($deliveryPlan), 404);
        $preview = $service->cancellation($deliveryPlan);
        $choices = app(ExportStockViewService::class)->choices();
        $choices['pallets'] = $choices['pallets']->reject(fn ($p) => $p->plannedPallet?->export_pallet_plan_id === $deliveryPlan->palletPlan->id);
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
