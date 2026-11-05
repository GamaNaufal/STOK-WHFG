<?php

namespace App\Models;

class ExportExpiredBoxHandling extends ExpiredBoxHandling
{
    public const MASTER_SCOPE = 'export';

    public const TRANSACTION_MODEL = ExportExpiredBoxTransaction::class;

    protected $attributes = ['scope' => 'export'];
}
