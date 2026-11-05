<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExportStockBox;
use App\Models\StockOpnameSession;
use Illuminate\Database\Eloquent\Builder;

/** Reads physical stock, including expiry/QA and reservations, without applying delivery eligibility filters. */
class StockOpnameInventory
{
    public function catalogue(string $scope): Builder
    {
        return $scope === 'export'
            ? ExportStockBox::withTrashed()->with(['currentPallet.plannedPallet', 'expiredHandling'])
            : Box::withTrashed()->with(['pallets.stockLocation', 'expiredHandling']);
    }

    public function onHand(Builder $query, string $scope): Builder
    {
        $query->whereNull('deleted_at');
        if ($scope === 'export') {
            return $query->where(fn ($q) => $q->whereIn('state', ['received', 'stored'])
                ->orWhere(fn ($q) => $q->where('state', 'handling')->whereHas('expiredHandling', fn ($h) => $h->whereIn('status', ['at_qa', 'awaiting_packing', 'at_packing']))));
        }

        return $query->where('is_withdrawn', false)->where(fn ($box) => $box
            ->whereNull('expired_status')->orWhere('expired_status', '!=', 'handled')
            ->orWhereHas('expiredHandling', fn ($qa) => $qa->whereIn('status', ['at_qa', 'awaiting_packing', 'at_packing'])));
    }

    public function snapshot($box, string $scope): array
    {
        if ($scope === 'export') {
            $qa = $box->expiredHandling;
            $handling = $box->state === 'handling' && $qa && in_array($qa->status, ['at_qa', 'awaiting_packing', 'at_packing'], true);
            $present = ! $box->trashed() && (in_array($box->state, ['received', 'stored'], true) || $handling);
            $pallet = $box->currentPallet?->display_name;
            $location = $box->currentPallet?->location_code;
            $status = $box->state;
            if ($handling) {
                $status = $qa->status_label;
                $location = $qa->status_label;
            }
            $reason = $present ? '' : 'Box sudah keluar atau diarsipkan dari stok ekspor.';
        } else {
            $qa = $box->expiredHandling;
            $handling = $box->expired_status === 'handled';
            $present = ! $box->trashed() && ! $box->is_withdrawn
                && (! $handling || ($qa && in_array($qa->status, ['at_qa', 'awaiting_packing', 'at_packing'], true)));
            $pallet = $box->pallets->sortBy('id')->pluck('pallet_number')->implode(' / ');
            $location = $box->pallets->sortBy('id')->map(fn ($p) => $p->stockLocation?->warehouse_location)->filter()->unique()->implode(' / ');
            $status = $handling ? ($qa?->status_label ?? 'Handled lama') : ($box->expired_status ?? 'active');
            if ($handling && $present) {
                $location = $qa->status_label;
            }
            $reason = $present ? '' : 'Box sudah keluar, diarsipkan, atau ID lama sudah diganti.';
        }

        return ['id' => $box->id, 'box_number' => $box->box_number, 'part_number' => $box->part_number,
            'pcs_quantity' => (int) $box->pcs_quantity, 'pallet' => $pallet ?: 'Belum ada pallet',
            'location' => $location ?: 'Belum ada lokasi', 'status' => $status, 'present' => $present, 'reason' => $reason];
    }

    public function observed(StockOpnameSession $session): array
    {
        // Include old stock returned to the warehouse and new receipts already archived during this session.
        $query = $this->catalogue($session->scope)->where(function ($query) use ($session) {
            $query->where('id', '>', $session->source_max_id)
                ->orWhereIn('box_number', $session->items()->select('box_number'))
                ->orWhere(fn ($current) => $this->onHand($current, $session->scope));
        });
        $snapshots = [];
        foreach ($query->orderBy('id')->lazyById(500) as $box) {
            $snapshots[$box->box_number] = $this->snapshot($box, $session->scope);
        }

        return $snapshots;
    }
}
