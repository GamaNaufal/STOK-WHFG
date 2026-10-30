<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPickingIssue extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['context' => 'array', 'revision' => 'integer', 'pcs_quantity' => 'integer',
        'approved_at' => 'datetime', 'confirmed_at' => 'datetime'];
    public function pallet() { return $this->belongsTo(ExportPlannedPallet::class, 'export_planned_pallet_id'); }
    public function stockBox() { return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id'); }
    public function returnPallet() { return $this->belongsTo(ExportStockPallet::class, 'return_pallet_id'); }
    public function returnLocation() { return $this->belongsTo(ExportMasterLocation::class, 'return_location_id'); }
    public function part() { return $this->belongsTo(ExportPartSetting::class, 'part_setting_id'); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function isClosed(): bool { return in_array($this->status, ['returned', 'input_error'], true); }
    public function scopeOpen($q) { return $q->whereNotIn('status', ['returned', 'input_error']); }
    public function getReasonAttribute(): string
    {
        return match ($this->issue_type) {
            'unknown_box' => 'Box belum terdaftar pada stok ekspor',
            'missing_location' => 'Box tidak sesuai; lokasi asal belum tersedia',
            default => 'Nomor box tidak sesuai rekomendasi FIFO',
        };
    }
}
