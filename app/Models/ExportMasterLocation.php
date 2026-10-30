<?php

namespace App\Models;

class ExportMasterLocation extends MasterLocation
{
    public const MASTER_SCOPE = 'export';

    protected $table = 'master_locations';

    protected $attributes = ['scope' => self::MASTER_SCOPE];

    public function reservation()
    {
        return $this->hasOne(ExportPalletLocationReservation::class, 'master_location_id');
    }

    public function plannedPallets()
    {
        return $this->hasMany(ExportPlannedPallet::class, 'master_location_id');
    }

    public function stockPallet() { return $this->hasOne(ExportStockPallet::class, 'master_location_id')->whereNull('retired_at'); }
    public function stockPalletHistory() { return $this->hasMany(ExportStockPallet::class, 'initial_location_id'); }

    public function scopeAvailable($query)
    {
        return parent::scopeAvailable($query)->whereDoesntHave('reservation');
    }
}
