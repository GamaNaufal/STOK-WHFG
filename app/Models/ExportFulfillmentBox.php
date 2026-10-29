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
    ];

    public function plannedItem() { return $this->belongsTo(ExportPlannedPalletItem::class, 'export_planned_pallet_item_id'); }
    public function part() { return $this->belongsTo(ExportPartSetting::class, 'part_setting_id'); }
    public function placedLocation() { return $this->belongsTo(ExportMasterLocation::class, 'placed_location_id'); }
    public function reservedBy() { return $this->belongsTo(User::class, 'reserved_by'); }
    public function placedBy() { return $this->belongsTo(User::class, 'placed_by'); }
}
