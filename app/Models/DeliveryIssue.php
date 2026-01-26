<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryIssue extends Model
{
    protected $table = 'delivery_issues';

    protected $fillable = [
        'pick_session_id',
        'box_id',
        'scanned_code',
        'issue_type',
        'status',
        'resolved_by',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
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

    // User yang resolve issue
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope helpers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Mark issue sebagai resolved
     */
    public function resolve($userId, $notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'notes' => $notes,
        ]);
    }
}
