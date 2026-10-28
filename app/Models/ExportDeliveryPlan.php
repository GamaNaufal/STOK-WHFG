<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportDeliveryPlan extends Model
{
    use SoftDeletes;

    protected $fillable = ['customer_name', 'delivery_deadline', 'status', 'revision', 'source_type',
        'source_name', 'total_pcs', 'total_boxes', 'created_by', 'updated_by'];

    protected $casts = ['delivery_deadline' => 'date', 'revision' => 'integer',
        'total_pcs' => 'integer', 'total_boxes' => 'integer'];

    public function items()
    {
        return $this->hasMany(ExportDeliveryPlanItem::class)->orderBy('position');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
