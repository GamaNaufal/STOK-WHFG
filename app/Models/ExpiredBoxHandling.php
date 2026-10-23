<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiredBoxHandling extends Model
{
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
