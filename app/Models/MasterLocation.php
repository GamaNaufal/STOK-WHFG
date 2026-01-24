<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterLocation extends Model
{
    protected $fillable = [
        'code',
        'is_occupied',
        'current_pallet_id'
    ];

    public function currentPallet()
    {
        return $this->belongsTo(Pallet::class, 'current_pallet_id');
    }
}
