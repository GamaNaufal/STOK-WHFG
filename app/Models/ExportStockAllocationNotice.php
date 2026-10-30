<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportStockAllocationNotice extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['plan_revision' => 'integer', 'acknowledged_at' => 'datetime'];

    public function palletPlan() { return $this->belongsTo(ExportPalletPlan::class, 'export_pallet_plan_id'); }
    public function boxes() { return $this->hasMany(ExportFulfillmentBox::class, 'allocation_notice_id'); }
}
