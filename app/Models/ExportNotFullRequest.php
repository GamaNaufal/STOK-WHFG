<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportNotFullRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['preparation' => 'array', 'revision' => 'integer', 'required_boxes' => 'integer', 'pcs_quantity' => 'integer', 'fixed_qty' => 'integer', 'approved_at' => 'datetime'];

    public function delivery()
    {
        return $this->belongsTo(ExportDeliveryPlan::class, 'export_delivery_plan_id')->withTrashed();
    }

    public function item()
    {
        return $this->belongsTo(ExportDeliveryPlanItem::class, 'export_delivery_plan_item_id');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockBox()
    {
        return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id')->withTrashed();
    }
}
