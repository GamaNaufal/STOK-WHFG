<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPickSession extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'created_by',
        'status',
        'started_at',
        'completed_at',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function items()
    {
        return $this->hasMany(DeliveryPickItem::class, 'pick_session_id');
    }

    public function issues()
    {
        return $this->hasMany(DeliveryScanIssue::class, 'pick_session_id');
    }
}