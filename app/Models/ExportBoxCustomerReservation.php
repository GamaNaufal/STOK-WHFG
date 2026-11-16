<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportBoxCustomerReservation extends Model
{
    protected $guarded = ['id'];
    public function customer() { return $this->belongsTo(ExportCustomer::class, 'export_customer_id'); }
}
