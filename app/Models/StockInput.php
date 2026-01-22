<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockInput extends Model
{
    protected $fillable = [
        'pallet_id',
        'pallet_item_id',
        'user_id',
        'warehouse_location',
        'pcs_quantity',
        'box_quantity',
        'stored_at',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class);
    }

    public function palletItem()
    {
        return $this->belongsTo(PalletItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

