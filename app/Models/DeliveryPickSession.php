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
        'redo_until',
        'completion_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(DeliveryPickItem::class, 'pick_session_id');
    }

    public function issues()
    {
        return $this->hasMany(DeliveryIssue::class, 'pick_session_id');
    }

    // User yang create session
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // User yang approve session
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope helpers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScanning($query)
    {
        return $query->where('status', 'scanning');
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
     * Get total items dalam session
     */
    public function getTotalItemsAttribute()
    {
        return $this->items()->count();
    }

    /**
     * Get total scanned items
     */
    public function getScannedItemsAttribute()
    {
        return $this->items()->where('status', 'scanned')->count();
    }

    /**
     * Check if session is blocked
     */
    public function isBlocked()
    {
        return $this->status === 'blocked';
    }

    /**
     * Check if session can be completed
     */
    public function canComplete()
    {
        return $this->status === 'approved' && $this->issues()->where('status', 'pending')->count() === 0;
    }
}