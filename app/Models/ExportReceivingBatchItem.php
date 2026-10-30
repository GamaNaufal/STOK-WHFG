<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportReceivingBatchItem extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['position' => 'integer', 'pcs_quantity' => 'integer'];
    public function batch() { return $this->belongsTo(ExportReceivingBatch::class, 'export_receiving_batch_id'); }
    public function stockBox() { return $this->belongsTo(ExportStockBox::class, 'export_stock_box_id'); }
}
