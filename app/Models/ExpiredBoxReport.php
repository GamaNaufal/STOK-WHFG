<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiredBoxReport extends Model
{
    protected $fillable = [
        'box_id',
        'box_number',
        'part_number',
        'pallet_id',
        'pallet_number',
        'warehouse_location',
        'stored_at',
        'age_months',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
