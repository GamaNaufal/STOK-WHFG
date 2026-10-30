<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportStockPallet extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['retired_at' => 'datetime'];
    public function boxes() { return $this->hasMany(ExportStockBox::class, 'current_pallet_id'); }
    public function plannedPallet() { return $this->belongsTo(ExportPlannedPallet::class, 'export_planned_pallet_id'); }
    public function location() { return $this->belongsTo(ExportMasterLocation::class, 'master_location_id'); }
    public function getNumberAttribute(): string { return 'EX-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
    public function getDisplayNameAttribute(): string
    {
        return $this->plannedPallet ? 'Pallet '.$this->plannedPallet->position : 'Pallet cadangan';
    }
}
