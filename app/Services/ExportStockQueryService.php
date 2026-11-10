<?php

namespace App\Services;

use App\Models\ExportMasterLocation;
use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPalletItem;
use App\Models\ExportStockBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportStockQueryService
{
    public const LIST_RELATIONS = ['currentPallet.plannedPallet', 'fulfillment.allocationNotice', 'fulfillment.plannedItem.pallet.palletPlan.deliveryPlan'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'part_number' => 'nullable|string|max:100',
            'pcs_per_box' => 'nullable|integer|min:1|max:4294967295',
            'status' => ['nullable', Rule::in(['stored', 'pending', 'spare', 'reserved', 'free', 'in_progress'])],
            'mode' => ['nullable', Rule::in(['box', 'pallet'])],
            'location_id' => ['nullable', 'integer', 'min:1', Rule::exists('master_locations', 'id')->where('scope', ExportMasterLocation::MASTER_SCOPE)],
        ]);

        // Older bookmarked reservation/placement filters belong to spare stock.
        $status = $data['status'] ?? '';
        if (in_array($status, ['pending', 'reserved'], true)) {
            $status = 'spare';
        }

        return ['q' => trim($data['q'] ?? ''), 'part_number' => trim($data['part_number'] ?? ''), 'pcs_per_box' => $data['pcs_per_box'] ?? null, 'status' => $status,
            'mode' => $data['mode'] ?? 'box',
            'location_id' => ! empty($data['location_id']) ? (int) $data['location_id'] : null];
    }

    public function boxes(array $filters): Builder
    {
        return ExportStockBox::query()->whereIn('state', ['received', 'stored'])->with(self::LIST_RELATIONS)
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
            ->when($filters['pcs_per_box'] ?? null, fn ($q, $pcs) => $q->where('pcs_quantity', $pcs))
            ->when(($filters['part_number'] ?? '') !== '', fn ($q) => ($filters['pcs_per_box'] ?? null)
                ? $q->where('part_number', $filters['part_number'])
                : $q->where('part_number', 'like', '%'.$filters['part_number'].'%'))
            ->when($filters['status'] !== '', fn ($q) => $this->applyStatus($q, $filters['status']))
            // A reservation is a future destination; it must not change physical stock filters.
            ->when($filters['location_id'], fn ($q, $id) => $q->whereHas('currentPallet', fn ($p) => $p->where('master_location_id', $id)))
            ->orderBy('first_received_at')->orderBy('id');
    }

    public function freeBoxes(Builder $query): Builder
    {
        return $query->eligible()->where('state', 'stored')
            ->where(fn ($box) => $box->whereNull('expired_status')->orWhere('expired_status', '<>', 'handled'))
            ->whereDoesntHave('fulfillment')
            ->whereHas('currentPallet', fn ($pallet) => $pallet->whereNull('retired_at')
                ->whereNull('export_planned_pallet_id')->whereNotNull('master_location_id'))
            ->whereNotIn('box_number', ExportPickingIssue::open()->select('box_number'));
    }

    private function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'free' => $this->freeBoxes($query),
            'in_progress' => $query->stockCategory('spare')
                ->where(fn ($box) => $box->where('state', 'received')->orWhereHas('fulfillment')),
            default => $query->stockCategory($status),
        };
    }

    public function overviewCounts(): array
    {
        $active = ExportStockBox::whereIn('state', ['received', 'stored']);
        $stored = $this->applyStatus(clone $active, 'stored')->count();

        return [
            'stored' => $stored,
            'spare' => (clone $active)->count() - $stored,
            'free' => $this->applyStatus(clone $active, 'free')->count(),
            'in_progress' => $this->applyStatus(clone $active, 'in_progress')->count(),
        ];
    }

    public function groups(array $filters)
    {
        return $this->boxes($filters)->withoutEagerLoads()->reorder()->select('current_pallet_id')
            ->selectRaw('COUNT(*) AS matched_box_count, SUM(pcs_quantity) AS matched_pcs_quantity')
            ->groupBy('current_pallet_id')
            ->with(['currentPallet' => fn ($p) => $p->withCount('boxes')->with('plannedPallet.palletPlan.deliveryPlan')])
            ->orderByRaw('CASE WHEN current_pallet_id IS NULL THEN 1 ELSE 0 END')->orderBy('current_pallet_id');
    }

    public function reservations(array $filters, array $physicalPalletIds)
    {
        $stockIds = $this->boxes($filters)->withoutEagerLoads()->reorder()
            ->where(function ($query) use ($physicalPalletIds) {
                $query->whereIn('current_pallet_id', array_filter($physicalPalletIds));
                if (in_array(null, $physicalPalletIds, true)) {
                    $query->orWhereNull('current_pallet_id');
                }
            })->select('export_stock_boxes.id');

        return ExportPlannedPalletItem::join('export_fulfillment_boxes as reservation', 'reservation.export_planned_pallet_item_id', '=', 'export_planned_pallet_items.id')
            ->join('export_stock_boxes as stock', 'stock.id', '=', 'reservation.export_stock_box_id')
            ->whereIn('stock.id', $stockIds)
            ->select('stock.current_pallet_id', 'export_planned_pallet_items.export_planned_pallet_id')
            ->selectRaw('COUNT(*) AS reserved_box_count')
            ->groupBy('stock.current_pallet_id', 'export_planned_pallet_items.export_planned_pallet_id')
            ->with('pallet.palletPlan.deliveryPlan')->get()
            ->groupBy(fn ($reservation) => $reservation->current_pallet_id ?? 'unplaced');
    }

    public function locationOptions()
    {
        return ExportMasterLocation::whereHas('stockPallet', fn ($p) => $p->whereHas('boxes'))
            ->orderBy('code')->get(['id', 'code']);
    }
}
