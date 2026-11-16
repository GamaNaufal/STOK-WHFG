<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Active reservation or confirmed placement; not an inventory receipt. */
class ExportFulfillmentBox extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($row) {
            $row->identity_cycle = $row->export_stock_box_id
                ? ExportStockBox::withTrashed()->whereKey($row->export_stock_box_id)->value('identity_cycle')
                : max(1, \App\Support\BoxIdentity::lastCycle($row->box_number));
            if ($row->export_stock_box_id) {
                app(\App\Services\ExportCustomerStockPolicy::class)->assertForItem(ExportStockBox::findOrFail($row->export_stock_box_id), $row->plannedItem);
            }
        });
        static::created(function ($row) {
            if (app(\App\Services\ExportCustomerStockPolicy::class)->ready()) {
                ExportCustomerStockException::where('export_stock_box_id', $row->export_stock_box_id)
                    ->where('export_planned_pallet_item_id', $row->export_planned_pallet_item_id)->where('status', 'active')
                    ->whereNull('fulfillment_id')->update(['fulfillment_id' => $row->id]);
            }
        });
        static::deleting(fn ($row) => app(\App\Services\ExportCustomerStockPolicy::class)->closeException($row));
    }

    protected $guarded = ['id'];

    protected $casts = [
        'box_number' => 'string', 'pcs_quantity' => 'integer',
        'first_received_at' => 'datetime', 'reserved_at' => 'datetime', 'placed_at' => 'datetime',
        'picking_started_at' => 'datetime',
        'merge_pending_verification' => 'boolean', 'picking_required' => 'boolean', 'picking_verified_at' => 'datetime', 'picking_verified_revision' => 'integer',
    ];

    public function scopeRequiresPickingScan(Builder $query): Builder
    {
        return $query->whereNotNull('export_fulfillment_boxes.allocation_notice_id')
            ->whereHas('stockBox', fn ($box) => $box->whereNotNull('current_pallet_id'))
            ->where(fn ($required) => $required->where('export_fulfillment_boxes.picking_required', true)
                ->orWhereHas('stockBox.currentPallet', fn ($pallet) => $pallet->mixed())
                ->orWhereHas('plannedItem', fn ($item) => $item->whereExists(fn ($other) => $other
                    ->selectRaw('1')->from('export_fulfillment_boxes as other_pick')
                    ->join('export_planned_pallet_items as other_item', 'other_item.id', '=', 'other_pick.export_planned_pallet_item_id')
                    ->whereColumn('other_pick.allocation_notice_id', 'export_fulfillment_boxes.allocation_notice_id')
                    ->whereColumn('other_pick.part_setting_id', 'export_fulfillment_boxes.part_setting_id')
                    ->whereColumn('other_item.export_planned_pallet_id', '<>', 'export_planned_pallet_items.export_planned_pallet_id'))));
    }

    public function plannedItem() { return $this->belongsTo(ExportPlannedPalletItem::class, 'export_planned_pallet_item_id'); }
    public function stockBox() { return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id'); }
    public function allocationNotice() { return $this->belongsTo(ExportStockAllocationNotice::class, 'allocation_notice_id'); }
    public function awaitsQueueAcknowledgment(): bool
    {
        return $this->allocation_notice_id !== null && !$this->allocationNotice?->acknowledged_at;
    }
    public function part() { return $this->belongsTo(PartSetting::class, 'part_setting_id'); }
    public function placedLocation() { return $this->belongsTo(ExportMasterLocation::class, 'placed_location_id'); }
    public function reservedBy() { return $this->belongsTo(User::class, 'reserved_by'); }
    public function placedBy() { return $this->belongsTo(User::class, 'placed_by'); }
}
