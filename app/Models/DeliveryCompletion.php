<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Unused model (legacy table).
 */
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function session()
    {
        return $this->belongsTo(DeliveryPickSession::class, 'pick_session_id');
    }

    // Scope helpers
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if completion is overdue
     */
    public function isOverdue()
    {
        return $this->redo_until && now()->isAfter($this->redo_until);
    }
}