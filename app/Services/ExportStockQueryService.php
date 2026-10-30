<?php

namespace App\Services;

use App\Models\{ExportMasterLocation, ExportStockBox, ExportStockPallet};
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
            'pallet_id' => 'nullable|integer|min:1|exists:export_stock_pallets,id',
            'location_id' => ['nullable', 'integer', 'min:1', Rule::exists('master_locations', 'id')->where('scope', ExportMasterLocation::MASTER_SCOPE)],
        ]);

        return ['q' => trim($data['q'] ?? ''), 'status' => $data['status'] ?? '',
            'pallet_id' => !empty($data['pallet_id']) ? (int) $data['pallet_id'] : null,
            'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null];
    }

    public function boxes(array $filters): Builder
    {
        return ExportStockBox::query()->with(self::LIST_RELATIONS)
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($s) =>
                $s->where('box_number', 'like', '%'.$filters['q'].'%')->orWhere('part_number', 'like', '%'.$filters['q'].'%')))
            ->when($filters['status'] === 'stored', fn ($q) => $q->where('state', 'stored'))
            ->when($filters['status'] === 'pending', fn ($q) => $q->where(fn ($s) => $s->where('state', 'received')
                ->orWhereHas('fulfillment', fn ($f) => $f->whereNull('placed_at')))->queueReady())
            ->when($filters['status'] === 'spare', fn ($q) => $q->where('state', 'stored')->whereDoesntHave('fulfillment'))
            ->when($filters['status'] === 'reserved', fn ($q) => $q->whereHas('fulfillment'))
            // A reservation is a future destination; it must not change physical stock filters.
            ->when($filters['pallet_id'], fn ($q, $id) => $q->where('current_pallet_id', $id))
            ->when($filters['location_id'], fn ($q, $id) => $q->whereHas('currentPallet', fn ($p) => $p->where('master_location_id', $id)))
            ->orderBy('first_received_at')->orderBy('id');
    }

    public function palletOptions()
    {
        // Only physical pallets containing stock, independent of the active search/page.
        return ExportStockPallet::whereHas('boxes')->with('plannedPallet.palletPlan.deliveryPlan')->withCount('boxes')
            ->orderBy('location_code')->orderBy('id')->get();
    }
}
