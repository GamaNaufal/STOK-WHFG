<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartSetting extends Model
{
    protected $fillable = [
        'part_number',
        'qty_box',
    ];

    protected $casts = [
        'qty_box' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Ensure unique part_number
    protected $unique = ['part_number'];

    /**
     * Scope untuk cari by part number
     */
    public function scopeByPartNumber($query, $partNumber)
    {
        return $query->where('part_number', $partNumber);
    }

    /**
     * Get setting or create default
     */
    public static function getOrCreate($partNumber, $defaultQtyBox = 1)
    {
        return static::firstOrCreate(
            ['part_number' => $partNumber],
            ['qty_box' => $defaultQtyBox]
        );
    }
}
