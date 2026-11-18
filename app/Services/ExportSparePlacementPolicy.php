<?php

namespace App\Services;

use App\Models\ExportReceivingBatchItem;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExportSparePlacementPolicy
{
    private function previous(Collection $partIds, Collection $pallets): Collection
    {
        return ExportStockBox::whereIn('part_setting_id', $partIds)->where('state', 'stored')
            ->whereIn('current_pallet_id', $pallets->whereNull('export_planned_pallet_id')->pluck('id'))
            ->select(['part_setting_id', 'current_pallet_id'])
            ->selectRaw('MAX(stored_at) AS last_placement, MAX(id) AS last_box_id')
            ->groupBy('part_setting_id', 'current_pallet_id')->orderByDesc('last_placement')->orderByDesc('last_box_id')
            ->get()->unique('part_setting_id')->keyBy('part_setting_id');
    }

    public function forGroups(Collection $groups, Collection $pallets): Collection
    {
        $partIds = $groups->filter(fn ($group) => ! $group['planned'] && $group['actionable']->isNotEmpty())
            ->flatMap(fn ($group) => $group['actionable']->pluck('part_setting_id'))->unique();
        if ($partIds->isEmpty()) {
            return $groups;
        }
        $previous = $this->previous($partIds, $pallets);
        $byId = $pallets->keyBy('id');
        $incoming = [];

        return $groups->map(function ($group) use ($previous, $byId, &$incoming) {
            if ($group['planned'] || $group['actionable']->isEmpty()) {
                return $group;
            }
            $pallet = $byId->get($previous->get($group['actionable']->first()->part_setting_id)?->current_pallet_id);
            $count = $group['actionable']->count();
            $earlier = $pallet ? ($incoming[$pallet->id] ?? 0) : 0;
            $available = ! $pallet ? 0 : ($pallet->spareBoxLimit() === null ? PHP_INT_MAX
                : max(0, $pallet->spareBoxLimit() - $pallet->boxes_count - $earlier));
            $fits = $pallet && $count <= $available;
            $required = $fits && $group['actionable']->every(fn ($box) => $box->state === 'received'
                && $box->origin === 'external' && ! $box->source_box_id);
            $group['spare_recommendation'] = $pallet ? [
                'destination' => 'pallet:'.$pallet->id, 'location' => $pallet->location_code, 'number' => $pallet->number,
                'current' => (int) $pallet->boxes_count, 'limit' => $pallet->spareBoxLimit(),
                'required' => $required, 'quantity' => $count, 'earlier_incoming' => $earlier,
            ] : null;
            $group['spare_default_destination'] = $fits ? 'pallet:'.$pallet->id : '';
            if ($fits) {
                $incoming[$pallet->id] = $earlier + $count;
            }

            return $group;
        });
    }

    /** Resolve the complete receipt, even when the request submits only a subset. */
    public function forReceipts(Collection $boxes, ?Collection $pallets = null): Collection
    {
        $receipts = $boxes->filter(fn ($box) => $box->state === 'received' && ! $box->fulfillment
            && $box->origin === 'external' && ! $box->source_box_id && $box->expiry_label !== 'Expired');
        if ($receipts->isEmpty()) {
            return collect();
        }
        $pallets ??= ExportStockPallet::whereNull('retired_at')->whereNull('export_planned_pallet_id')
            ->whereHas('location')->whereHas('boxes')->withCount('boxes')->get();
        $links = ExportReceivingBatchItem::whereIn('export_stock_box_id', $receipts->pluck('id'))->get()->keyBy('export_stock_box_id');
        $batchIds = $links->pluck('export_receiving_batch_id')->unique();
        $pending = $batchIds->isEmpty() ? collect() : ExportStockBox::where('state', 'received')->eligible()
            ->where('origin', 'external')->whereNull('source_box_id')->whereDoesntHave('fulfillment')
            ->join('export_receiving_batch_items as receipt_item', 'receipt_item.export_stock_box_id', '=', 'export_stock_boxes.id')
            ->whereIn('receipt_item.export_receiving_batch_id', $batchIds)
            ->select('export_stock_boxes.*', 'receipt_item.export_receiving_batch_id as receiving_batch_id')
            ->orderBy('first_received_at')->orderBy('export_stock_boxes.id')->get();
        $contexts = $pending->groupBy('receiving_batch_id');
        foreach ($receipts as $box) {
            if (! $links->has($box->id)) {
                $contexts->put('legacy-'.$box->id, collect([$box]));
            }
        }
        $result = collect();
        foreach ($contexts as $context) {
            $groups = $context->groupBy('part_setting_id')->map(fn ($rows) => [
                'planned' => null, 'actionable' => $rows, 'part_number' => $rows->first()->part_number,
            ]);
            foreach ($this->forGroups($groups, $pallets) as $group) {
                foreach ($group['actionable'] as $box) {
                    $result->put($box->id, $group['spare_recommendation']);
                }
            }
        }

        return $result;
    }

    /** Called once, under the inventory mutex, before any placement in this request. */
    public function assertPlacements(array $rows): void
    {
        $boxes = ExportStockBox::with('fulfillment')->whereIn('id', array_column($rows, 'id'))->get();
        $rules = $this->forReceipts($boxes);
        foreach ($rows as $row) {
            $rule = $rules->get((int) $row['id']);
            if (($rule['required'] ?? false) && (! empty($row['location_id'])
                || 'pallet:'.($row['pallet_id'] ?? '') !== $rule['destination'])) {
                throw ValidationException::withMessages(['destination' => 'Seluruh '.$rule['quantity'].' box part '
                    .$boxes->firstWhere('id', (int) $row['id'])->part_number.' masih muat pada '.$rule['number'].' di '
                    .$rule['location'].'. Wajib ditempatkan pada pallet tersebut. Muat ulang penempatan terbaru.']);
            }
        }
    }
}
