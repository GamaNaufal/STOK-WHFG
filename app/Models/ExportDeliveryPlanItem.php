<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportDeliveryPlanItem extends Model
{
    protected $fillable = ['part_setting_id', 'position', 'part_number', 'pcs_quantity', 'pcs_per_box', 'box_quantity'];
    protected $casts = ['pcs_quantity' => 'integer', 'pcs_per_box' => 'integer', 'box_quantity' => 'integer'];

    public function plan()
    {
        return $this->belongsTo(ExportDeliveryPlan::class, 'export_delivery_plan_id');
    }

    public function partSetting()
    {
        return $this->belongsTo(ExportPartSetting::class, 'part_setting_id');
    }
}
