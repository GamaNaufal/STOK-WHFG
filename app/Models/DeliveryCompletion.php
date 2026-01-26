<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryCompletion extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'pick_session_id',
        'withdrawal_batch_id',
        'status',
        'completed_at',
        'redo_until',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'redo_until' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function session()
    {
        return $this->belongsTo(DeliveryPickSession::class, 'pick_session_id');
    }
}