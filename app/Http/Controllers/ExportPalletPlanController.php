<?php

namespace App\Http\Controllers;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAllocationNoticeService;
use App\Models\ExportStockAllocationNotice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExportPalletPlanController extends Controller
{
    public function index(Request $request)
    {
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $sort = in_array($request->query('sort'), ['newest', 'oldest'], true) ? $request->query('sort') : 'newest';
        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $deliveries = ExportDeliveryPlan::with('palletPlan')->when($search !== '', fn ($query) => $query
            ->where(fn ($q) => $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('plan_code', 'like', '%'.$search.'%')))
            ->orderBy('created_at', $direction)->orderBy('id', $direction)->paginate(15)->withQueryString();
        return view('export.pallet-plans.index', compact('deliveries', 'search', 'sort'));
    }

    private function context(ExportDeliveryPlan $delivery, ExportPalletPlanningService $service): array
    {
        $delivery->load('items', 'palletPlan.pallets.items', 'palletPlan.reservations', 'palletPlan.finalizedBy');
        $planning = $delivery->palletPlan;
        $rows = $service->rows($planning);
        $catalog = \App\Services\ExportBoxRequirements::catalog($delivery);
        foreach ($planning?->pallets ?? [] as $pallet) {
            foreach ($pallet->items as $item) {
                $key = \App\Services\ExportBoxRequirements::key($item->part_setting_id, $item->pcs_per_box);
                if (!$catalog->has($key)) $catalog->put($key, ['key' => $key,
                    'part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number,
                    'pcs_per_box' => $item->pcs_per_box, 'required' => 0,
                ]);
            }
        }
        $summary = $service->requirements($delivery, $rows);
        foreach ($summary as &$row) $row['part_number'] = $catalog->get($row['key'])['part_number'] ?? $row['part_number'];
        unset($row);
        $locations = ExportMasterLocation::with('reservation')->withExists(['stockLocations', 'stockPallet'])->orderBy('code')->get()->map(fn ($loc) => [
            'id' => $loc->id, 'code' => $loc->code,
            'blocked' => $loc->is_occupied || $loc->current_pallet_id || $loc->stock_locations_exists || $loc->stock_pallet_exists
                || ($loc->reservation && $loc->reservation->export_pallet_plan_id !== $planning?->id),
            'reserved' => (bool) $loc->reservation,
        ])->all();
        return compact('delivery', 'planning', 'rows', 'summary', 'locations') + ['catalog' => $catalog->values()->all(),
            'printReady' => $planning?->isFinalFor($delivery) ?? false];
    }

    public function show(ExportDeliveryPlan $deliveryPlan, ExportPalletPlanningService $service)
    {
        if (!$deliveryPlan->palletPlan()->exists()) return redirect()->route('export.pallet-plans.edit', $deliveryPlan);
        $data = $this->context($deliveryPlan, $service);
        $stockNotice = ExportStockAllocationNotice::where('export_pallet_plan_id', $data['planning']->id)
            ->orderByDesc('plan_revision')->first();
        $stockReviewRequired = $stockNotice && $data['printReady']
            && $stockNotice->plan_revision === $data['planning']->revision && !$stockNotice->acknowledged_at;
        $stockReviewOpen = $stockNotice && ($stockReviewRequired || request()->boolean('review_stock') || request()->has('stock_page'));
        // Preload the modal so reviewing allocations requires no request/navigation.
        $stockAssignments = $stockNotice ? $stockNotice->boxes()->with(['stockBox.currentPallet.plannedPallet', 'plannedItem.pallet'])
            ->orderBy('first_received_at')->orderBy('export_stock_box_id')->paginate(100, ['*'], 'stock_page')
            ->withQueryString()->fragment('stock-allocation-modal') : null;
        return view('export.pallet-plans.show', $data + compact('stockNotice', 'stockAssignments', 'stockReviewRequired', 'stockReviewOpen'));
    }

    public function acknowledgeStock(Request $request, ExportDeliveryPlan $deliveryPlan, ExportStockAllocationNotice $notice, ExportStockAllocationNoticeService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1']);
        $service->acknowledge($deliveryPlan->id, $notice->id, (int) $data['revision'], $request->user());
        $message = 'Tinjauan stok FIFO selesai. Tugas yang ditemukan sudah aktif di antrean penempatan/pemindahan.';
        if ($request->expectsJson()) return response()->json(['message' => $message]);
        return redirect()->route('export.pallet-plans.show', $deliveryPlan)->with('success', $message);
    }

    public function edit(ExportDeliveryPlan $deliveryPlan, ExportPalletPlanningService $service)
    {
        if ($deliveryPlan->palletPlan?->status === 'final') return redirect()->route('export.pallet-plans.show', $deliveryPlan);
        return view('export.pallet-plans.form', $this->context($deliveryPlan, $service));
    }

    public function update(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPalletPlanningService $service)
    {
        if ($request->has('pallets_json')) {
            $request->validate(['pallets_json' => 'required|string|max:1048576']);
            try { $rows = json_decode($request->pallets_json, true, 16, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw ValidationException::withMessages(['pallets' => 'Susunan pallet tidak dapat dibaca. Periksa dan coba lagi.']); }
            $request->merge(['pallets' => $rows]);
        }
        $data = $request->validate([
            'action' => 'required|in:draft,finalize', 'revision' => 'required|integer|min:0',
            'delivery_revision' => 'required|integer|min:1', 'pallets' => 'required|array|list|min:1|max:500',
            'pallets.*.id' => 'nullable|integer|min:1', 'pallets.*.master_location_id' => 'nullable|integer|min:1',
            'pallets.*.items' => 'present|array|list|max:500',
            'pallets.*.items.*.part_setting_id' => 'required|integer|min:1',
            'pallets.*.items.*.pcs_per_box' => 'nullable|integer|min:1|max:4294967295',
            'pallets.*.items.*.box_quantity' => 'required|integer|min:1|max:4294967295',
        ]);
        $service->save($deliveryPlan->id, $data, $request->user());
        return redirect()->route('export.pallet-plans.show', $deliveryPlan)->with('success',
            $data['action'] === 'finalize' ? 'Perencanaan final. Seluruh lokasi pallet sudah direservasi.' : 'Draft perencanaan pallet berhasil disimpan.');
    }

    public function transition(Request $request, ExportDeliveryPlan $deliveryPlan, ExportPalletPlanningService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'action' => 'required|in:reopen,cancel']);
        $service->transition($deliveryPlan->id, (int) $data['revision'], $request->user(), $data['action'] === 'cancel');
        return redirect()->route('export.pallet-plans.show', $deliveryPlan)->with('success',
            $data['action'] === 'cancel' ? 'Perencanaan dibatalkan. Reservasi lokasi dilepas.' : 'Perencanaan dibuka kembali sebagai draft.');
    }
}
