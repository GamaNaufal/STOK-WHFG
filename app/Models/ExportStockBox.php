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
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
    public function movements() { return $this->hasMany(ExportStockMovement::class); }
}
