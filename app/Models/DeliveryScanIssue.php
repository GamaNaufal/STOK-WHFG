<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryScanIssue extends Model
{
    protected $fillable = [
        'pick_session_id',
        'box_id',
        'scanned_code',
        'reason',
        'status',
        'resolved_by',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(DeliveryPickSession::class, 'pick_session_id');
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }
}