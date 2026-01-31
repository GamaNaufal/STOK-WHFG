<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotFullBoxRequest extends Model
{
    protected $fillable = [
        'box_number',
        'part_number',
        'pcs_quantity',
        'fixed_qty',
        'reason',
        'request_type',
        'delivery_order_id',
        'requested_by',
        'target_pallet_id',
        'target_location_id',
        'target_location_code',
        'status',
        'approved_by',
        'approved_at',
        'box_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function targetPallet()
    {
        return $this->belongsTo(Pallet::class, 'target_pallet_id');
    }

    public function targetLocation()
    {
        return $this->belongsTo(MasterLocation::class, 'target_location_id');
    }

    public function box()
    {
        return $this->belongsTo(Box::class, 'box_id');
    }
}
