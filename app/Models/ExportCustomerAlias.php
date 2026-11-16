<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportCustomerAlias extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    public function customer() { return $this->belongsTo(ExportCustomer::class, 'export_customer_id'); }
}
