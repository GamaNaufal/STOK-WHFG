<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpiredBoxTransaction extends Model
{
    protected $guarded = ['id'];

    public function handlings(): HasMany
    {
        return $this->hasMany(ExpiredBoxHandling::class, 'transaction_id')->orderBy('id');
    }

    public function getReferenceAttribute(): string
    {
        return 'EXP-'.$this->created_at->format('Ymd').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStageAttribute(): string
    {
        $this->loadMissing('handlings');
        foreach (['at_qa', 'awaiting_packing', 'at_packing'] as $stage) {
            if ($this->handlings->contains('status', $stage)) {
                return $stage;
            }
        }

        return 'completed';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->stage) {
            'at_qa' => 'Menunggu hasil QA',
            'awaiting_packing' => 'Siap diserahkan ke packing',
            'at_packing' => 'Menunggu hasil packing',
            default => 'Selesai',
        };
    }

    public function getNextActionAttribute(): ?string
    {
        return match ($this->stage) {
            'at_qa' => 'qa', 'awaiting_packing' => 'packing', 'at_packing' => 'receive', default => null,
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->next_action) {
            'qa' => 'Catat hasil QA', 'packing' => 'Serahkan ke packing',
            'receive' => 'Terima hasil packing', default => 'Lihat transaksi',
        };
    }
}
