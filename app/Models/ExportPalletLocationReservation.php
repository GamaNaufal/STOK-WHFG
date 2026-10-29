<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPalletLocationReservation extends Model
{
    protected $guarded = [];
    protected $primaryKey = 'master_location_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $casts = ['master_location_id' => 'integer', 'export_pallet_plan_id' => 'integer', 'export_planned_pallet_id' => 'integer'];
    public function palletPlan() { return $this->belongsTo(ExportPalletPlan::class, 'export_pallet_plan_id'); }
    public function pallet() { return $this->belongsTo(ExportPlannedPallet::class, 'export_planned_pallet_id'); }
}
