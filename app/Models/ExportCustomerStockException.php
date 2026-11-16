<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportCustomerStockException extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'identity_cycle' => 'integer', 'revision' => 'integer'];
}
