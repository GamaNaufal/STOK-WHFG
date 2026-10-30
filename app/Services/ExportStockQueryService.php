<?php

namespace App\Services;

use App\Models\{ExportMasterLocation, ExportStockBox};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportStockQueryService
{
    public const LIST_RELATIONS = ['currentPallet.plannedPallet', 'fulfillment.allocationNotice', 'fulfillment.plannedItem.pallet'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(['stored', 'pending', 'spare', 'reserved'])],
            'mode' => ['nullable', Rule::in(['box', 'pallet'])],
            'location_id' => ['nullable', 'integer', 'min:1', Rule::exists('master_locations', 'id')->where('scope', ExportMasterLocation::MASTER_SCOPE)],
        ]);

        return ['q' => trim($data['q'] ?? ''), 'status' => $data['status'] ?? '',
            'mode' => $data['mode'] ?? 'box',
            'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null];
    }

    public function boxes(array $filters): Builder
    {
        return ExportStockBox::query()->with(self::LIST_RELATIONS)
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($s) use ($filters) {
                $search = $filters['q'];
                $s->where('box_number', 'like', '%'.$search.'%')->orWhere('part_number', 'like', '%'.$search.'%')
                    ->orWhereHas('currentPallet', function ($p) use ($search) {
                        $p->where('location_code', 'like', '%'.$search.'%')
                            ->orWhereHas('plannedPallet.palletPlan.deliveryPlan', fn ($d) => $d->where('plan_code', 'like', '%'.$search.'%'));
                        if (preg_match('/^pallet\s*(\d+)$/i', $search, $matches)) {
                            $p->orWhereHas('plannedPallet', fn ($plan) => $plan->where('position', (int) $matches[1]));
                        } elseif (preg_match('/^pallet\s+cadangan$/i', $search)) {
                            $p->orWhereNull('export_planned_pallet_id');
                        }
                    });
            }))
            ->when($filters['status'] === 'stored', fn ($q) => $q->where('state', 'stored'))
            ->when($filters['status'] === 'pending', fn ($q) => $q->where(fn ($s) => $s->where('state', 'received')
                ->orWhereHas('fulfillment', fn ($f) => $f->whereNull('placed_at')))->queueReady())
            ->when($filters['status'] === 'spare', fn ($q) => $q->where('state', 'stored')->whereDoesntHave('fulfillment'))
            ->when($filters['status'] === 'reserved', fn ($q) => $q->whereHas('fulfillment'))
            // A reservation is a future destination; it must not change physical stock filters.
            ->when($filters['location_id'], fn ($q, $id) => $q->whereHas('currentPallet', fn ($p) => $p->where('master_location_id', $id)))
            ->orderBy('first_received_at')->orderBy('id');
    }

    public function groups(array $filters)
    {
        return $this->boxes($filters)->withoutEagerLoads()->reorder()->select('current_pallet_id')
            ->selectRaw('COUNT(*) AS matched_box_count, SUM(pcs_quantity) AS matched_pcs_quantity')
            ->groupBy('current_pallet_id')
            ->with(['currentPallet' => fn ($p) => $p->withCount('boxes')->with('plannedPallet.palletPlan.deliveryPlan')])
            ->orderByRaw('CASE WHEN current_pallet_id IS NULL THEN 1 ELSE 0 END')->orderBy('current_pallet_id');
    }

    public function locationOptions()
    {
        return ExportMasterLocation::whereHas('stockPallet', fn ($p) => $p->whereHas('boxes'))
            ->orderBy('code')->get(['id', 'code']);
    }
}
