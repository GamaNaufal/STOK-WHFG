<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartSetting extends Model
{
    use \App\Models\Concerns\HasMasterScope;

    public const MASTER_SCOPE = 'domestic';

    protected $attributes = ['scope' => self::MASTER_SCOPE];

    protected $fillable = [
        'part_number',
        'qty_box',
    ];

    protected $casts = [
        'qty_box' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Database uniqueness is enforced on (scope, part_number).

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
