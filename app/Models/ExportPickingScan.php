<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPickingScan extends Model
{
    protected $guarded = ['id'];

    public function issue()
    {
        return $this->belongsTo(ExportPickingIssue::class, 'export_picking_issue_id');
    }

    public function fulfillmentBox()
    {
        return $this->belongsTo(ExportFulfillmentBox::class, 'export_fulfillment_box_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
