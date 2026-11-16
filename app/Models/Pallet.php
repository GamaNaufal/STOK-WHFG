<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $pallet_number
 * @property int|null $boxes_count
 */
class Pallet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pallet_number',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    // Relationship box aktif (belum withdrawn/expired/deleted)
    public function activeBoxes()
    {
        return $this->belongsToMany(Box::class, 'pallet_boxes')
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->where(function ($q) { \App\Models\Box::applyUsableExpiryFilter($q, 'boxes'); });
    }

    // Physical occupancy includes expired stock, even after a reactivation deadline.
    // Handled stock has left for QA; withdrawn/deleted stock no longer occupies storage.
    public function storedBoxes()
    {
        return $this->belongsToMany(Box::class, 'pallet_boxes')
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->where(fn ($query) => $query->whereNull('boxes.expired_status')
                ->orWhere('boxes.expired_status', '!=', 'handled'));
    }

    public function hasStoredInventory(): bool
    {
        if ($this->storedBoxes()->exists()) {
            return true;
        }
        // Ignore stale summaries once physical box history is available.
        if ($this->boxes()->withTrashed()->exists()) {
            return false;
        }

        return $this->items()->where(fn ($query) => $query
            ->where('pcs_quantity', '>', 0)->orWhere('box_quantity', '>', 0))->exists();
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
