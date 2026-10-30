<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportStockBox extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['box_number' => 'string', 'pcs_quantity' => 'integer', 'revision' => 'integer',
        'first_received_at' => 'datetime', 'stored_at' => 'datetime'];

    public function part() { return $this->belongsTo(ExportPartSetting::class, 'part_setting_id'); }
    public function currentPallet() { return $this->belongsTo(ExportStockPallet::class, 'current_pallet_id'); }
    public function fulfillment() { return $this->hasOne(ExportFulfillmentBox::class); }
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->fulfillment?->placed_at => 'Terpenuhi',
            (bool) $this->fulfillment?->awaitsQueueAcknowledgment() => 'Direservasi · menunggu Oke FIFO',
            (bool) $this->fulfillment => 'Direservasi · menunggu penempatan',
            $this->state === 'stored' => 'Stok cadangan',
            default => 'Pilih penempatan cadangan',
        };
    }
    public function scopeQueueReady($query)
    {
        return $query->whereDoesntHave('fulfillment', fn ($q) => $q->whereNotNull('allocation_notice_id')
            ->whereDoesntHave('allocationNotice', fn ($n) => $n->whereNotNull('acknowledged_at')));
    }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
    public function movements() { return $this->hasMany(ExportStockMovement::class); }
}
