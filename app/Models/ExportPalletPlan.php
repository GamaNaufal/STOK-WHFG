<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportPalletPlan extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['revision' => 'integer', 'delivery_revision' => 'integer', 'finalized_at' => 'datetime', 'entered_fulfillment' => 'boolean'];

    public function deliveryPlan() { return $this->belongsTo(ExportDeliveryPlan::class, 'export_delivery_plan_id'); }
    public function pallets() { return $this->hasMany(ExportPlannedPallet::class)->orderBy('position'); }
    public function reservations() { return $this->hasMany(ExportPalletLocationReservation::class); }
    public function finalizedBy() { return $this->belongsTo(User::class, 'finalized_by'); }

    public function isFinalFor(ExportDeliveryPlan $delivery): bool
    {
        return $this->status === 'final' && $this->delivery_revision === $delivery->revision
            && (int) $this->export_delivery_plan_id === $delivery->id;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) { 'final' => 'Final', 'cancelled' => 'Dibatalkan', default => 'Draft' };
    }
}
