<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $withdrawal_batch_id
 * @property int|null $user_id
 * @property int|null $pallet_item_id
 * @property int|null $box_id
 * @property string $part_number
 * @property int $pcs_quantity
 * @property int $box_quantity
 * @property string $warehouse_location
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 */
class StockWithdrawal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'withdrawal_batch_id',
        'user_id',
        'pallet_item_id',
        'box_id',
        'part_number',
        'pcs_quantity',
        'box_quantity',
        'warehouse_location',
        'status',
        'notes',
        'withdrawn_at',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'status' => 'string', // completed, reversed, cancelled
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function palletItem()
    {
        return $this->belongsTo(PalletItem::class);
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    // Scope untuk query yang sering digunakan
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeReversed($query)
    {
        return $query->where('status', 'reversed');
    }
}
