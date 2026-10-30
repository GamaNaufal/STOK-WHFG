<?php

namespace App\Services;

use App\Models\{ExportMasterLocation, ExportStockBox, ExportStockPallet};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExportStockViewService
{
    public const RELATIONS = ['currentPallet.plannedPallet', 'fulfillment.plannedItem.pallet.palletPlan.deliveryPlan'];

    public function pendingQuery(): Builder
    {
        return ExportStockBox::where(fn ($query) => $query->where('state', 'received')
            ->orWhereHas('fulfillment', fn ($fulfillment) => $fulfillment->whereNull('placed_at')));
    }

    public function choices(): array
    {
        return [
            'pallets' => ExportStockPallet::whereNull('retired_at')->whereHas('location')->whereHas('boxes')
                ->with('plannedPallet')->withCount('boxes')->orderBy('id')->get(),
            'locations' => ExportMasterLocation::available()->whereNull('current_pallet_id')->whereDoesntHave('stockLocations')
                ->whereDoesntHave('stockPallet')->orderBy('code')->get(),
        ];
    }

    public function groups(Collection $boxes): Collection
    {
        $plannedIds = $boxes->map(fn ($box) => $box->fulfillment?->plannedItem->pallet->id)->filter()->unique();
        $targets = $plannedIds->isEmpty() ? collect() : ExportStockPallet::whereNull('retired_at')
            ->whereIn('export_planned_pallet_id', $plannedIds)->withCount('boxes')->get()->keyBy('export_planned_pallet_id');
        return $boxes->groupBy(fn ($box) => $box->fulfillment ? 'planned-'.$box->fulfillment->plannedItem->pallet->id : 'spare')
            ->map(function ($rows, $key) use ($targets) {
                $planned = $rows->first()->fulfillment?->plannedItem->pallet;
                $pending = $rows->filter(fn ($box) => $box->state === 'received' || ($box->fulfillment && !$box->fulfillment->placed_at))->values();
                $target = $planned ? $targets->get($planned->id) : null;
                $current = (int) ($target?->boxes_count ?? 0);
                $incoming = $pending->filter(fn ($box) => !$target || (int) $box->current_pallet_id !== (int) $target->id)->count();
                return ['key' => $key, 'planned' => $planned, 'delivery' => $planned?->palletPlan->deliveryPlan,
                    'load' => $planned ? ['current' => $current, 'incoming' => $incoming, 'total' => $current + $incoming] : null,
                    'boxes' => $rows->values(), 'pending' => $pending,
                    'placements' => $pending->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision,
                        'request_token' => (string) \Illuminate\Support\Str::uuid()])->all()];
            })->values();
    }
}
