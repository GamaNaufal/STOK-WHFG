<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Active reservation or confirmed placement; not an inventory receipt. */
class ExportFulfillmentBox extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'box_number' => 'string', 'pcs_quantity' => 'integer',
        'first_received_at' => 'datetime', 'reserved_at' => 'datetime', 'placed_at' => 'datetime',
        'picking_required' => 'boolean', 'picking_verified_at' => 'datetime', 'picking_verified_revision' => 'integer',
    ];

    public function plannedItem() { return $this->belongsTo(ExportPlannedPalletItem::class, 'export_planned_pallet_item_id'); }
    public function stockBox() { return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id'); }
    public function allocationNotice() { return $this->belongsTo(ExportStockAllocationNotice::class, 'allocation_notice_id'); }
    public function awaitsQueueAcknowledgment(): bool
    {
        return $this->allocation_notice_id !== null && !$this->allocationNotice?->acknowledged_at;
    }
    public function part() { return $this->belongsTo(ExportPartSetting::class, 'part_setting_id'); }
    public function placedLocation() { return $this->belongsTo(ExportMasterLocation::class, 'placed_location_id'); }
    public function reservedBy() { return $this->belongsTo(User::class, 'reserved_by'); }
    public function placedBy() { return $this->belongsTo(User::class, 'placed_by'); }
}
