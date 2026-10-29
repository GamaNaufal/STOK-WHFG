<?php

namespace App\Http\Controllers;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Services\ExportPalletPlanningService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExportPalletPlanController extends Controller
{
    public function index(Request $request)
    {
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $deliveries = ExportDeliveryPlan::with('palletPlan')->when($search !== '', fn ($query) => $query
            ->where(fn ($q) => $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('plan_code', 'like', '%'.$search.'%')))
            ->orderBy('delivery_deadline')->orderBy('id')->paginate(15)->withQueryString();
        return view('export.pallet-plans.index', compact('deliveries', 'search'));
    }

    private function context(ExportDeliveryPlan $delivery, ExportPalletPlanningService $service): array
    {
        $delivery->load('items', 'palletPlan.pallets.items', 'palletPlan.reservations', 'palletPlan.finalizedBy');
        $planning = $delivery->palletPlan;
        $rows = $service->rows($planning);
        $catalog = $delivery->items->map(fn ($item) => [
            'part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number,
            'pcs_per_box' => $item->pcs_per_box, 'required' => $item->box_quantity,
        ])->keyBy('part_setting_id');
        foreach ($planning?->pallets ?? [] as $pallet) {
            foreach ($pallet->items as $item) {
                if (!$catalog->has($item->part_setting_id)) $catalog->put($item->part_setting_id, [
                    'part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number,
                    'pcs_per_box' => $item->pcs_per_box, 'required' => 0,
                ]);
            }
        }
        $summary = $service->requirements($delivery, $rows);
        foreach ($summary as &$row) $row['part_number'] = $catalog->get($row['part_setting_id'])['part_number'] ?? $row['part_number'];
        unset($row);
        $locations = ExportMasterLocation::with('reservation')->withExists('stockLocations')->orderBy('code')->get()->map(fn ($loc) => [
            'id' => $loc->id, 'code' => $loc->code,
            'blocked' => $loc->is_occupied || $loc->current_pallet_id || $loc->stock_locations_exists
                || ($loc->reservation && $loc->reservation->export_pallet_plan_id !== $planning?->id),
            'reserved' => (bool) $loc->reservation,
        ])->all();
        return compact('delivery', 'planning', 'rows', 'summary', 'locations') + ['catalog' => $catalog->values()->all()];
    }

    public function show(ExportDeliveryPlan $deliveryPlan, ExportPalletPlanningService $service)
    {
        if (!$deliveryPlan->palletPlan()->exists()) return redirect()->route('export.pallet-plans.edit', $deliveryPlan);
        return view('export.pallet-plans.show', $this->context($deliveryPlan, $service));
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
