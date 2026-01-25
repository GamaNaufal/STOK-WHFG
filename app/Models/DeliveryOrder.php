<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'sales_user_id',
        'customer_name',
        'delivery_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
