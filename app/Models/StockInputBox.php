<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockInputBox extends Model
{
    protected $fillable = [
        'stock_input_id',
        'box_id',
    ];

    public function stockInput()
    {
        return $this->belongsTo(StockInput::class);
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }
}
