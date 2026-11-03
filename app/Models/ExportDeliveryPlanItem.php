<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportDeliveryPlanItem extends Model
{
    protected $fillable = ['part_setting_id', 'position', 'part_number', 'pcs_quantity', 'pcs_per_box', 'box_quantity', 'not_full_pcs'];
    protected $casts = ['pcs_quantity' => 'integer', 'pcs_per_box' => 'integer', 'box_quantity' => 'integer', 'not_full_pcs' => 'integer'];

    public function plan()
    {
        return $this->belongsTo(ExportDeliveryPlan::class, 'export_delivery_plan_id');
    }

    public function partSetting()
    {
        return $this->belongsTo(PartSetting::class, 'part_setting_id');
    }
}
