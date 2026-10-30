<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPlannedPallet extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['position' => 'integer', 'master_location_id' => 'integer'];
    public function palletPlan() { return $this->belongsTo(ExportPalletPlan::class, 'export_pallet_plan_id'); }
    public function items() { return $this->hasMany(ExportPlannedPalletItem::class)->orderBy('id'); }
    public function location() { return $this->belongsTo(ExportMasterLocation::class, 'master_location_id'); }
    public function fulfillmentBoxes() { return $this->hasManyThrough(ExportFulfillmentBox::class, ExportPlannedPalletItem::class); }
}
