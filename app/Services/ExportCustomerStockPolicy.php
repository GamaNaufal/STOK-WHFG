<?php

namespace App\Services;

use App\Models\{ExportBoxCustomerReservation, ExportCustomerStockException, ExportDeliveryCustomerLink, ExportDeliveryPlan, ExportFulfillmentBox, ExportPlannedPalletItem, ExportStockBox};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ExportCustomerStockPolicy
{
    public function ready(): bool { return Schema::hasTable('export_customer_stock_requests'); }

    public function ownerId(ExportStockBox $box): ?int
    {
        return $this->ready() ? ExportBoxCustomerReservation::where('export_stock_box_id', $box->id)->value('export_customer_id') : null;
    }

    public function customerId(?ExportDeliveryPlan $delivery): ?int
    {
        if (! $delivery) return null;
        return $this->ready() ? ExportDeliveryCustomerLink::where('export_delivery_plan_id', $delivery->id)->value('export_customer_id') : null;
    }

    /** Retain callers' expiry/position/issue filters; select own stock before general FIFO. */
    public function candidates(Builder $query, ?int $customerId, bool $prioritize = true): Builder
    {
        if (! $this->ready()) return $query;
        $query->where(fn ($q) => $q->whereDoesntHave('customerReservation')
            ->when($customerId, fn ($q) => $q->orWhereHas('customerReservation', fn ($r) => $r->where('export_customer_id', $customerId))));
        if ($customerId && $prioritize) {
            $query->reorder()->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM export_box_customer_reservations cr WHERE cr.export_stock_box_id = export_stock_boxes.id AND cr.export_customer_id = ?) THEN 0 ELSE 1 END', [$customerId])
                ->orderBy('first_received_at')->orderBy('id');
        }
        return $query;
    }

    public function assertForItem(ExportStockBox $box, ExportPlannedPalletItem $item, ?int $fulfillmentId = null): void
    {
        $owner = $this->ownerId($box);
        if (! $owner) return;
        $item->loadMissing('pallet.palletPlan.deliveryPlan');
        $delivery = $item->pallet->palletPlan->deliveryPlan;
        if ($owner === $this->customerId($delivery)) return;
        $exception = ExportCustomerStockException::where('export_stock_box_id', $box->id)
            ->where('export_customer_id', $owner)->where('export_delivery_plan_id', $delivery->id)
            ->where('export_planned_pallet_item_id', $item->id)->where('identity_cycle', $box->identity_cycle)
            ->where('status', 'active')->first();
        if ($exception && ($fulfillmentId ? (int) $exception->fulfillment_id === $fulfillmentId : $exception->fulfillment_id === null)) return;
        throw ValidationException::withMessages(['customer' => 'Box '.$box->box_number.' direservasi untuk customer lain. Gunakan pengecualian khusus delivery ini.']);
    }

    public function assertCommitment(ExportFulfillmentBox $commitment): void
    {
        $commitment->loadMissing('plannedItem.pallet.palletPlan.deliveryPlan');
        if ($commitment->export_stock_box_id && ($box = ExportStockBox::find($commitment->export_stock_box_id))) {
            $this->assertForItem($box, $commitment->plannedItem, $commitment->id);
        }
    }

    public function closeException(ExportFulfillmentBox $commitment, string $status = 'cancelled'): void
    {
        if (! $this->ready()) return;
        $exception = ExportCustomerStockException::where('fulfillment_id', $commitment->id)->where('status', 'active')->lockForUpdate()->first();
        if (! $exception) return;
        $before = $exception->toArray();
        $exception->update(['status' => $status, 'revision' => $exception->revision + 1]);
        $actor = auth()->id() ?: $exception->authorized_by;
        $box = ExportStockBox::withTrashed()->findOrFail($exception->export_stock_box_id);
        app(ExportCustomerReservationService::class)->event($box, 'exception_'.$status, $actor, $before, $exception->toArray(), $exception->reason);
    }
}
