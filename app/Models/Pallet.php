<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pallet extends Model
{
    protected $fillable = [
        'pallet_number',
    ];

    public function items()
    {
        return $this->hasMany(PalletItem::class);
    }

    public function stockLocation()
    {
        return $this->hasOne(StockLocation::class);
    }

    // Relationship ke boxes melalui pallet_boxes table
    public function boxes()
    {
        return $this->belongsToMany(Box::class, 'pallet_boxes');
    }

    // Relationship ke stock inputs
    public function stockInputs()
    {
        return $this->hasMany(StockInput::class);
    }

    // Relationship ke master location
    public function currentLocation()
    {
        return $this->hasOne(MasterLocation::class, 'current_pallet_id');
    }

    /**
     * Get total PCS dalam pallet
     */
    public function getTotalPcsAttribute()
    {
        return $this->items()->sum('pcs_quantity');
    }

    /**
     * Get total boxes dalam pallet
     */
    public function getTotalBoxesAttribute()
    {
        return $this->boxes()->count();
    }
}
