<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalletItem extends Model
{
    protected $fillable = [
        'pallet_id',
        'part_number',
        'box_quantity',
        'pcs_quantity',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class);
    }
}
