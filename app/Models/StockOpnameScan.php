<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameScan extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'scanned_at' => 'datetime'];
    public function scanner() { return $this->belongsTo(User::class, 'scanned_by'); }
}
