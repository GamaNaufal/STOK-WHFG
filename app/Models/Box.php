<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'box_number',
        'part_number',
        'part_name',
        'pcs_quantity',
        'qr_code',
        'user_id',
        'qty_box',
        'type_box',
        'wk_transfer',
        'lot01',
        'lot02',
        'lot03',
        'is_withdrawn',
        'withdrawn_at',
        'is_not_full',
        'not_full_reason',
        'assigned_delivery_order_id',
    ];

    protected $casts = [
        'is_withdrawn' => 'boolean',
        'is_not_full' => 'boolean',
        'withdrawn_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $indexes = [
        'part_number',
        'box_number',
        'user_id',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship ke pallets melalui pallet_boxes table
    public function pallets()
    {
        return $this->belongsToMany(Pallet::class, 'pallet_boxes');
    }

    // Relationship untuk stock withdrawals
    public function stockWithdrawals()
    {
        return $this->hasMany(StockWithdrawal::class);
    }

    // Relationship untuk delivery pick items
    public function deliveryPickItems()
    {
        return $this->hasMany(DeliveryPickItem::class);
    }

    public function assignedDeliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'assigned_delivery_order_id');
    }

    // Scope helper
    public function scopeActive($query)
    {
        return $query->where('is_withdrawn', false);
    }

    public function scopeWithdrawn($query)
    {
        return $query->where('is_withdrawn', true);
    }
}
