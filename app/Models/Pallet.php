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
}
