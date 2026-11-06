<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ExportStockPallet extends Model
{
    public const BOX_LIMIT_RULE = 'nullable|integer|min:0|max:4294967295';

    protected $guarded = ['id'];
    protected $casts = ['retired_at' => 'datetime', 'box_limit' => 'integer'];
    public function boxes() { return $this->hasMany(ExportStockBox::class, 'current_pallet_id'); }
    public function plannedPallet() { return $this->belongsTo(ExportPlannedPallet::class, 'export_planned_pallet_id'); }
    public function location() { return $this->belongsTo(ExportMasterLocation::class, 'master_location_id'); }
    public function spareBoxLimit(): ?int
    {
        return $this->export_planned_pallet_id ? null : $this->box_limit;
    }

    public function capacityLabel(int $current): string
    {
        $limit = $this->spareBoxLimit();
        if ($limit === null) {
            return $current.' box'.($this->export_planned_pallet_id ? '' : ' · Tanpa batas');
        }
        $remaining = max(0, $limit - $current);

        return $current.'/'.$limit.' box · '.($remaining ? 'Sisa '.$remaining : 'Penuh');
    }

    /** Caller holds the export inventory mutex. Count boxes still physically on this pallet. */
    public function assertSpaceForIncomingBox(): void
    {
        $limit = $this->spareBoxLimit();
        if ($limit !== null && $this->boxes()->count() >= $limit) {
            throw ValidationException::withMessages(['destination' => 'Penempatan akan melebihi batas '.$limit
                .' box pada pallet '.$this->number.' di '.$this->location_code.'. Pilih tujuan yang masih memiliki kapasitas.']);
        }
    }

    public function scopeMixed($q)
    {
        return $q->whereRaw('(SELECT COUNT(DISTINCT source_box.part_setting_id) FROM export_stock_boxes source_box WHERE source_box.current_pallet_id = export_stock_pallets.id AND source_box.deleted_at IS NULL) > 1');
    }
    public function getNumberAttribute(): string { return 'EX-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
    public function getDisplayNameAttribute(): string
    {
        return $this->plannedPallet ? 'Pallet '.$this->plannedPallet->position : 'Pallet cadangan';
    }
}
