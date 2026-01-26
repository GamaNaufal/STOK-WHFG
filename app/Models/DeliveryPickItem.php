<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPickItem extends Model
{
    protected $fillable = [
        'pick_session_id',
        'box_id',
        'part_number',
        'pcs_quantity',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
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