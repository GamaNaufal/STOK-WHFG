<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportStockBox extends Model
{
    use \App\Models\Concerns\ClaimsGlobalBoxIdentity;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const INVENTORY_SCOPE = 'export';

    protected bool $legacyIdentity = false;

    protected function adoptsLegacyIdentity(): bool
    {
        return $this->legacyIdentity;
    }

    public static function registerLegacyIdentity(array $attributes): static
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($attributes) {
            $number = $attributes['box_number'];
            $legacy = ExportFulfillmentBox::where('box_number', $number)->whereNull('export_stock_box_id')
                ->whereHas('plannedItem.pallet.palletPlan.deliveryPlan', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))->lockForUpdate()->first();
            $registered = \Illuminate\Support\Facades\DB::table('box_identities')->where('box_number', $number)->where('origin_scope', 'export')->exists();
            if (! $legacy || ! $registered || static::withTrashed()->where('box_number', $number)->exists()
                || (int) $legacy->part_setting_id !== (int) $attributes['part_setting_id'] || (int) $legacy->pcs_quantity !== (int) $attributes['pcs_quantity']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['issue' => 'Identitas box lama tidak tersedia untuk pencatatan fisik ekspor.']);
            }
            $attributes['first_received_at'] = $legacy->first_received_at;
            $box = new static($attributes);
            $box->legacyIdentity = true;
            $box->save();

            return $box;
        });
    }

    protected $guarded = ['id'];

    protected $casts = ['box_number' => 'string', 'pcs_quantity' => 'integer', 'revision' => 'integer', 'is_not_full' => 'boolean',
        'standard_pcs_quantity' => 'integer', 'receipt_pcs_quantity' => 'integer',
        'first_received_at' => 'datetime', 'stored_at' => 'datetime'];

    public function part()
    {
        return $this->belongsTo(PartSetting::class, 'part_setting_id');
    }

    public function currentPallet()
    {
        return $this->belongsTo(ExportStockPallet::class, 'current_pallet_id');
    }

    public function fulfillment()
    {
        return $this->hasOne(ExportFulfillmentBox::class);
    }

    public function scopeStockCategory($query, string $category)
    {
        $confirmed = fn ($pallet) => $pallet->whereNotNull('ready_at');
        if ($category === 'stored') {
            return $query->whereHas('fulfillment.plannedItem.pallet', $confirmed);
        }

        return $query->whereDoesntHave('fulfillment.plannedItem.pallet', $confirmed);
    }

    public function getStockCategoryLabelAttribute(): string
    {
        return $this->fulfillment?->plannedItem?->pallet?->ready_at ? 'Tersimpan' : 'Stok cadangan';
    }

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

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function movements()
    {
        return $this->hasMany(ExportStockMovement::class);
    }
}
