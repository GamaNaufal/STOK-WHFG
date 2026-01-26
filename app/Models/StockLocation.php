<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    protected $fillable = [
        'pallet_id',
        'master_location_id',
        'warehouse_location',
        'stored_at',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class);
    }

    // Relationship ke master location untuk better tracking
    public function masterLocation()
    {
        return $this->belongsTo(MasterLocation::class);
    }

    /**
     * Scope untuk cari berdasarkan warehouse location
     */
    public function scopeByWarehouse($query, $location)
    {
        return $query->where('warehouse_location', $location);
    }
}
