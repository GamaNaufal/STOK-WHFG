<?php

namespace App\Models;

class ExportExpiredBoxReceipt extends ExpiredBoxReceipt
{
    public const MASTER_SCOPE = 'export';

    public const HANDLING_MODEL = ExportExpiredBoxHandling::class;

    protected $attributes = ['scope' => 'export'];
}
