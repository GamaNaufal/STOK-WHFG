<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportStockBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExportStockCustomerReadiness
{
    public function __construct(
        private ExportFulfillmentService $fulfillment,
        private ExportStockQueryService $stock,
    ) {}

    private function activeDeliveries(): Builder
    {
        return ExportDeliveryPlan::whereNotIn('status', ['cancelled', 'completed'])
            ->whereDoesntHave('palletPlan', fn ($plan) => $plan
                ->where(fn ($q) => $q->where('status', 'cancelled')->orWhereNotNull('completed_at')));
    }

    public function customers(): Collection
    {
        $legacy = $this->activeDeliveries()
            ->when(app(ExportCustomerStockPolicy::class)->ready(), fn ($q) => $q->whereDoesntHave('customerLink'))
            ->selectRaw('LOWER(TRIM(customer_name)) AS customer_key, MIN(TRIM(customer_name)) AS customer_label')
            ->groupByRaw('LOWER(TRIM(customer_name))')->orderBy('customer_label')
            ->pluck('customer_label', 'customer_key');
        if (! app(ExportCustomerStockPolicy::class)->ready()) return $legacy;
        $mapped = \App\Models\ExportDeliveryCustomerLink::with('customer')->whereIn('export_delivery_plan_id', $this->activeDeliveries()->select('id'))
            ->get()->unique('export_customer_id')->mapWithKeys(fn ($link) => ['customer:'.$link->export_customer_id => $link->customer->code.' · '.$link->customer->name]);
        return $legacy->merge($mapped)->sort();
    }

    public function forCustomer(string $customer): array
    {
        $deliveries = collect();
        $shortages = collect();
        if ($customer === '') {
            return compact('customer', 'deliveries', 'shortages');
        }

        $query = $this->activeDeliveries();
        $selectedCustomerId = preg_match('/^customer:([1-9][0-9]*)$/D', $customer, $match) ? (int) $match[1] : null;
        if ($selectedCustomerId && app(ExportCustomerStockPolicy::class)->ready()) $query->whereHas('customerLink', fn ($q) => $q->where('export_customer_id', $selectedCustomerId));
        else $query->whereRaw('LOWER(TRIM(customer_name)) = ?', [$customer]);
        $plans = $query->with(['items', 'palletPlan'])->orderBy('delivery_deadline')->orderBy('id')->get();
        $metrics = $this->fulfillment->itemMetrics(stockBacked: true)
            ->whereIn('pallet.export_pallet_plan_id', $plans->pluck('palletPlan.id')->filter()->all())
            ->get()->groupBy('plan_id');

        $deliveries = $plans->map(function (ExportDeliveryPlan $delivery) use ($metrics) {
            $planned = $metrics->get($delivery->palletPlan?->id, collect())
                ->groupBy(fn ($row) => ExportBoxRequirements::key($row->part_setting_id, $row->pcs_per_box));
            $parts = ExportBoxRequirements::catalog($delivery)->map(function (array $need, string $key) use ($planned) {
                $ready = $placed = $reserved = 0;
                foreach ($planned->get($key, []) as $row) {
                    $covered = min($row->required_boxes, $row->fulfilled_boxes);
                    $confirmed = min($covered, $row->ready_boxes);
                    $ready += $confirmed;
                    $placed += $covered - $confirmed;
                    $reserved += min($row->required_boxes - $covered, $row->reserved_boxes);
                }

                $ready = min($need['required'], $ready);
                $placed = min($need['required'] - $ready, $placed);
                $reserved = min($need['required'] - $ready - $placed, $reserved);
                $unallocated = $need['required'] - $ready - $placed - $reserved;

                return $need + compact('ready', 'placed', 'reserved', 'unallocated');
            });

            return [
                'delivery' => $delivery,
                'required' => $parts->sum('required'),
                'ready' => $parts->sum('ready'),
                'placed' => $parts->sum('placed'),
                'reserved' => $parts->sum('reserved'),
                'unallocated' => $parts->sum('unallocated'),
                'parts' => $parts,
            ];
        });

        $shortages = $deliveries->flatMap(fn ($row) => $row['parts']->values())
            ->where('unallocated', '>', 0)->groupBy('key')
            ->map(fn (Collection $rows) => array_replace($rows->first(), [
                'unallocated' => $rows->sum('unallocated'),
                'is_not_full' => $rows->contains('is_not_full', true),
            ]));
        if ($shortages->isNotEmpty()) {
            $linkedIds = app(ExportCustomerStockPolicy::class)->ready()
                ? \App\Models\ExportDeliveryCustomerLink::whereIn('export_delivery_plan_id', $plans->modelKeys())->pluck('export_customer_id')->unique() : collect();
            $free = app(ExportCustomerStockPolicy::class)->candidates($this->stock->freeBoxes(ExportStockBox::query()), $linkedIds->count() === 1 ? (int) $linkedIds->first() : null, false)
                ->whereIn('part_setting_id', $shortages->pluck('part_setting_id')->unique())
                ->whereIn('pcs_quantity', $shortages->pluck('pcs_per_box')->unique())
                ->groupBy('part_setting_id', 'pcs_quantity')
                ->selectRaw('part_setting_id, pcs_quantity, COUNT(*) AS box_count')->get()
                ->mapWithKeys(fn ($row) => [ExportBoxRequirements::key($row->part_setting_id, $row->pcs_quantity) => (int) $row->box_count]);
            $shortages = $shortages->map(function (array $row, string $key) use ($free) {
                $available = $free->get($key, 0);

                return $row + ['available' => $available, 'remaining' => max(0, $row['unallocated'] - $available)];
            })->sortBy([['part_number', 'asc'], ['pcs_per_box', 'desc']])->values();
        }

        return compact('customer', 'deliveries', 'shortages');
    }
}
