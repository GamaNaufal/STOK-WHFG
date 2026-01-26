<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $fillable = [
        'box_number',
        'part_number',
        'pcs_quantity',
        'qr_code',
        'user_id',
        'qty_box',
        'is_withdrawn',
        'withdrawn_at',
    ];

    protected $casts = [
        'is_withdrawn' => 'boolean',
        'withdrawn_at' => 'datetime',
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
}
