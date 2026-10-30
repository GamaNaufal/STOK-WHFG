<?php

namespace App\Services;

use App\Models\{ExportMasterLocation, ExportStockBox, ExportStockPallet};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExportStockViewService
{
    public const RELATIONS = ['currentPallet.plannedPallet', 'fulfillment.allocationNotice', 'fulfillment.plannedItem.pallet.palletPlan.deliveryPlan'];

    public function pendingQuery(): Builder
    {
        return ExportStockBox::where(fn ($query) => $query->where('state', 'received')
            ->orWhereHas('fulfillment', fn ($fulfillment) => $fulfillment->whereNull('placed_at')))->queueReady();
    }

    public function choices(): array
    {
        return [
            'pallets' => ExportStockPallet::whereNull('retired_at')->whereHas('location')->whereHas('boxes')
                ->whereDoesntHave('plannedPallet', fn ($p) => $p->whereNotNull('ready_at'))
                ->with('plannedPallet')->withCount('boxes')->orderBy('id')->get(),
            'locations' => ExportMasterLocation::available()->whereNull('current_pallet_id')->whereDoesntHave('stockLocations')
                ->whereDoesntHave('stockPallet')->orderBy('code')->get(),
        ];
    }

    public function groups(Collection $boxes): Collection
    {
        $commitments = new \Illuminate\Database\Eloquent\Collection($boxes->map(fn ($b) => $b->fulfillment)->filter()->all());
        app(ExportPickingService::class)->enrich($commitments);
        $plannedIds = $boxes->map(fn ($box) => $box->fulfillment?->plannedItem->pallet->id)->filter()->unique();
        $pickIssues = \App\Models\ExportPickingIssue::open()->where(fn ($q) => $q->whereIn('export_planned_pallet_id', $plannedIds)->orWhere('reported_by', auth()->id())->orWhereIn('box_number', $boxes->pluck('box_number')))->orderBy('id')->get();
        $targets = $plannedIds->isEmpty() ? collect() : ExportStockPallet::whereNull('retired_at')
            ->whereIn('export_planned_pallet_id', $plannedIds)->withCount('boxes')->get()->keyBy('export_planned_pallet_id');
        return $boxes->groupBy(fn ($box) => $box->fulfillment ? 'planned-'.$box->fulfillment->plannedItem->pallet->id : 'spare')
            ->map(function ($rows, $key) use ($targets, $pickIssues) {
                $planned = $rows->first()->fulfillment?->plannedItem->pallet;
                $pending = $rows->filter(fn ($box) => $box->state === 'received' || ($box->fulfillment && !$box->fulfillment->placed_at))->values();
                $waiting = $pending->filter(fn ($box) => $box->fulfillment?->awaitsQueueAcknowledgment())->values();
                $actionable = $pending->reject(fn ($box) => $box->fulfillment?->awaitsQueueAcknowledgment())->values();
                $target = $planned ? $targets->get($planned->id) : null;
                $current = (int) ($target?->boxes_count ?? 0);
                $incoming = $pending->filter(fn ($box) => !$target || (int) $box->current_pallet_id !== (int) $target->id)->count();
                $requiresPicking = $actionable->filter(fn ($b) => $b->current_pallet_id && $b->fulfillment?->requires_scan);
                $needsPicking = $requiresPicking->filter(fn ($b) => !$b->fulfillment->picking_ready);
                $pickingIssue = $pickIssues->first(fn ($issue) => (int)$issue->reported_by === auth()->id() || ($planned && (int)$issue->export_planned_pallet_id === $planned->id) || $rows->contains('box_number', $issue->box_number));
                return ['key' => $key, 'planned' => $planned, 'delivery' => $planned?->palletPlan->deliveryPlan,
                    'picking_required' => $requiresPicking->count(), 'picking_remaining' => $needsPicking->count(), 'picking_issue' => $pickingIssue,
                    'load' => $planned ? ['current' => $current, 'incoming' => $incoming, 'total' => $current + $incoming] : null,
                    'boxes' => $rows->values(), 'pending' => $pending, 'waiting' => $waiting, 'actionable' => $actionable,
                    'placements' => $actionable->map(fn ($box) => ['id' => $box->id, 'revision' => $box->revision,
                        'request_token' => (string) \Illuminate\Support\Str::uuid()])->all()];
            })->values();
    }
}
