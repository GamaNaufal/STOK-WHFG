<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpiredBoxReceipt extends Model
{
    use \App\Models\Concerns\HasMasterScope;

    public const MASTER_SCOPE = 'domestic';

    public const HANDLING_MODEL = ExpiredBoxHandling::class;

    protected $table = 'expired_box_receipts';

    protected $attributes = ['scope' => 'domestic'];

    protected $guarded = ['id'];

    protected $casts = ['received_at' => 'datetime'];

    public function handlings(): HasMany
    {
        return $this->hasMany(static::HANDLING_MODEL, 'receipt_id')->orderBy('id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
