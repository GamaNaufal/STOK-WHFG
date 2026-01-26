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

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class);
    }

    // Relationship ke stock withdrawals
    public function stockWithdrawals()
    {
        return $this->hasMany(StockWithdrawal::class);
    }

    // Relationship ke stock inputs
    public function stockInputs()
    {
        return $this->hasMany(StockInput::class);
    }

    /**
     * Get part settings untuk standardisasi qty_box
     */
    public function partSetting()
    {
        return $this->hasOne(PartSetting::class, 'part_number', 'part_number');
    }
}
