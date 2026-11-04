<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameSession extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'summary' => 'array', 'source_max_id' => 'integer'];
    public function starter() { return $this->belongsTo(User::class, 'started_by'); }
    public function finisher() { return $this->belongsTo(User::class, 'finished_by'); }
    public function items() { return $this->hasMany(StockOpnameItem::class); }
    public function scans() { return $this->hasMany(StockOpnameScan::class); }
    public function getCodeAttribute(): string
    {
        return 'SO-'.($this->scope === 'export' ? 'EX' : 'LK').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
    public function getScopeLabelAttribute(): string { return $this->scope === 'export' ? 'Ekspor' : 'Lokal'; }
}
