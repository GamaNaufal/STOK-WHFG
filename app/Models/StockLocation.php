<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    protected $fillable = [
        'pallet_id',
        'warehouse_location',
        'stored_at',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class);
    }
}
