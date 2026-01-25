<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'part_number',
        'quantity',
        'fulfilled_quantity',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }
}
