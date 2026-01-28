<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    protected $fillable = [
        'type',
        'model',
        'model_id',
        'action',
        'old_values',
        'new_values',
        'description',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship ke User yang melakukan aksi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get old values sebagai array
     */
    public function getOldValuesArray()
    {
        return $this->old_values ? json_decode($this->old_values, true) : [];
    }

    /**
     * Get new values sebagai array
     */
    public function getNewValuesArray()
    {
        return $this->new_values ? json_decode($this->new_values, true) : [];
    }

    /**
     * Scope untuk filter by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope untuk filter by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope untuk mendapatkan audit logs terbaru
     */
    public function scopeLatest($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Helper untuk menampilkan deskripsi perubahan
     */
    public function getChangeDescription()
    {
        $oldValues = $this->getOldValuesArray();
        $newValues = $this->getNewValuesArray();

        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[] = "{$key}: '{$oldValue}' → '{$newValue}'";
            }
        }

        return implode(', ', $changes) ?: 'Data dibuat';
    }
}
