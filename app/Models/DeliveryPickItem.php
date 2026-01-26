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
        'scanned_by',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(DeliveryPickSession::class, 'pick_session_id');
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    // User yang scan
    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * Scope helpers
     */
    public function scopeScanned($query)
    {
        return $query->where('status', 'scanned');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Mark sebagai scanned
     */
    public function markAsScanned($userId = null)
    {
        $this->update([
            'status' => 'scanned',
            'scanned_at' => now(),
            'scanned_by' => $userId,
        ]);
    }
}