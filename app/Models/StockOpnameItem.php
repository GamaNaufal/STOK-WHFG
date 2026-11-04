<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['initial_snapshot' => 'array', 'current_snapshot' => 'array', 'present' => 'boolean', 'first_scanned_at' => 'datetime'];
    public function scanner() { return $this->belongsTo(User::class, 'first_scanned_by'); }
}
