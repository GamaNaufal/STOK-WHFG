<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportDeliveryPlan extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\ClaimsDeliveryPlanCode;

    protected static function booted(): void
    {
        static::saving(function (self $plan) {
            $plan->plan_code=\App\Support\DeliveryPlanCode::normalize($plan->plan_code);
            if ($plan->plan_code==='') throw \Illuminate\Validation\ValidationException::withMessages(['plan_code'=>'Kode rencana delivery wajib diisi.']);
            $owner=\Illuminate\Support\Facades\DB::table('export_delivery_plan_codes')->where('code',$plan->plan_code)->value('export_delivery_plan_id');
            if ($owner!==null && (int)$owner!==(int)$plan->id) throw \Illuminate\Validation\ValidationException::withMessages(['plan_code'=>'Kode rencana delivery sudah pernah digunakan oleh rencana lain.']);
        });
    }

    protected $fillable = ['plan_code', 'customer_name', 'delivery_deadline', 'status', 'revision', 'source_type',
        'source_name', 'total_pcs', 'total_boxes', 'created_by', 'updated_by'];

    protected $casts = ['delivery_deadline' => 'date', 'revision' => 'integer',
        'total_pcs' => 'integer', 'total_boxes' => 'integer'];

    public function items()
    {
        return $this->hasMany(ExportDeliveryPlanItem::class)->orderBy('position');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function palletPlan()
    {
        return $this->hasOne(ExportPalletPlan::class);
    }
}
