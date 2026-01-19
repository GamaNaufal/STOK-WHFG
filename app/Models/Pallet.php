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
}
