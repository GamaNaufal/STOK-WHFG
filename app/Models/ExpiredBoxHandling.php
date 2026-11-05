<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiredBoxHandling extends Model
{
    use \App\Models\Concerns\HasMasterScope;

    public const MASTER_SCOPE = 'domestic';

    public const TRANSACTION_MODEL = ExpiredBoxTransaction::class;

    protected $table = 'expired_box_handlings';

    protected $attributes = ['scope' => 'domestic'];

    public static function groupKey(string $part, int $pcs): string
    {
        return hash('sha256', json_encode([$part, $pcs], JSON_THROW_ON_ERROR));
    }

    public function getGroupKeyAttribute(): string
    {
        return self::groupKey($this->part_number, (int) $this->pcs_quantity);
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(static::TRANSACTION_MODEL, 'transaction_id');
    }

    protected $guarded = ['id'];

    protected $casts = [
        'source_snapshot' => 'array', 'history_snapshot' => 'array',
        'original_stored_at' => 'datetime', 'sent_qa_at' => 'datetime',
        'qa_decided_at' => 'datetime', 'sent_packing_at' => 'datetime',
        'received_at' => 'datetime', 'label_issued_at' => 'datetime', 'usable_until' => 'datetime',
    ];

    public function getReferenceAttribute(): string
    {
        return 'HND-'.$this->created_at->format('Ymd').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'at_qa' => 'Di QA', 'awaiting_packing' => 'Di warehouse · menunggu packing',
            'at_packing' => 'Di Packing', 'completed' => 'Selesai · ID diganti',
            'rejected' => 'Tidak lolos QA · dihapus permanen', default => $this->status,
        };
    }
}
