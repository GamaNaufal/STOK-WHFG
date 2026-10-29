<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPlannedPalletItem extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['pcs_per_box' => 'integer', 'box_quantity' => 'integer', 'part_setting_id' => 'integer'];
    public function pallet() { return $this->belongsTo(ExportPlannedPallet::class, 'export_planned_pallet_id'); }
    public function fulfillmentBoxes() { return $this->hasMany(ExportFulfillmentBox::class); }
}
