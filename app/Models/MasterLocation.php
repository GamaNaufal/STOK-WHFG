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

    protected $casts = [
        'is_occupied' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function currentPallet()
    {
        return $this->belongsTo(Pallet::class, 'current_pallet_id');
    }

    // Relationship ke stock locations
    public function stockLocations()
    {
        return $this->hasMany(StockLocation::class);
    }

    /**
     * Scope untuk cari available locations
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_occupied', false);
    }

    public function scopeOccupied($query)
    {
        return $query->where('is_occupied', true);
    }

    /**
     * Mark location sebagai occupied
     */
    public function occupyWithPallet($palletId)
    {
        $this->update([
            'is_occupied' => true,
            'current_pallet_id' => $palletId,
        ]);
    }

    /**
     * Check jika pallet masih punya stok
     */
    public function isPalletEmpty()
    {
        if (!$this->current_pallet_id) {
            return true;
        }

        $pallet = $this->currentPallet;
        if (!$pallet) {
            return true;
        }

        // Cek apakah masih ada items dengan quantity > 0
        return !$pallet->items()
            ->where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })
            ->exists();
    }

    /**
     * Auto vacate jika pallet empty
     */
    public function autoVacateIfEmpty()
    {
        if ($this->isPalletEmpty()) {
            $this->vacate();
        }
    }

    /**
     * Vacate location
     */
    public function vacate()
    {
        $this->update([
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);
    }
}
