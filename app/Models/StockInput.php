<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockInput extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pallet_id',
        'pallet_item_id',
        'user_id',
        'warehouse_location',
        'pcs_quantity',
        'box_quantity',
        'stored_at',
        'part_numbers',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'part_numbers' => 'array',
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

    /**
     * Scope untuk query by date range
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}