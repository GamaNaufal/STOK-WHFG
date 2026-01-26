<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sales_user_id',
        'customer_name',
        'delivery_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    // Relationship ke pick sessions
    public function pickSessions()
    {
        return $this->hasMany(DeliveryPickSession::class);
    }

    /**
     * Scope untuk filter by status
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if order is ready untuk pickup
     */
    public function isReadyForPickup()
    {
        return $this->status === 'approved';
    }

    /**
     * Get total items quantity
     */
    public function getTotalQuantityAttribute()
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get total fulfilled quantity
     */
    public function getTotalFulfilledAttribute()
    {
        return $this->items()->sum('fulfilled_quantity');
    }
}
