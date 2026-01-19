<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'pallet_item_id',
        'part_number',
        'pcs_quantity',
        'box_quantity',
        'warehouse_location',
        'status',
        'notes',
        'withdrawn_at',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function palletItem()
    {
        return $this->belongsTo(PalletItem::class);
    }
}
