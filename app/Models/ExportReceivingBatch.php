<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportReceivingBatch extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['status' => 'draft', 'revision' => 1];
    protected $casts = ['revision' => 'integer', 'processed_at' => 'datetime'];
    public function items() { return $this->hasMany(ExportReceivingBatchItem::class)->orderBy('position'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
