<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportStockMovement extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['placed_at' => 'datetime'];
    public function box() { return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id'); }
}
