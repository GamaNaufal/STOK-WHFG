<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAnnouncement extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean', 'quota' => 'integer'];

    public function targets()
    {
        return $this->hasMany(DeliveryAnnouncementTarget::class, 'announcement_id');
    }

    public function getUnitAttribute(): string
    {
        return $this->quota_type === 'box' ? 'box' : 'delivery';
    }

    public function usage(): array
    {
        $used = $reserved = 0;
        foreach ($this->targets as $target) {
            $used += $this->quota_type === 'box' ? count($target->shipped_boxes) : (int) $target->delivery_granted;
            $reserved += $this->quota_type === 'box' ? count($target->reserved_boxes) : 0;
        }

        return ['used' => $used, 'reserved' => $reserved, 'remaining' => $this->quota - $used - $reserved];
    }
}
