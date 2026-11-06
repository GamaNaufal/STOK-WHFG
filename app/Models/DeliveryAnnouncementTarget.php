<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAnnouncementTarget extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['delivery_granted' => 'boolean', 'eligible_boxes' => 'array', 'reserved_boxes' => 'array', 'shipped_boxes' => 'array'];

    public function announcement()
    {
        return $this->belongsTo(DeliveryAnnouncement::class);
    }
}
