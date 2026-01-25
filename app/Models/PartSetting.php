<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartSetting extends Model
{
    protected $fillable = [
        'part_number',
        'qty_box',
    ];
}
