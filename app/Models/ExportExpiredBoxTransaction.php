<?php

namespace App\Models;

class ExportExpiredBoxTransaction extends ExpiredBoxTransaction
{
    public const MASTER_SCOPE = 'export';

    public const HANDLING_MODEL = ExportExpiredBoxHandling::class;

    public const RECEIPT_MODEL = ExportExpiredBoxReceipt::class;

    protected $attributes = ['scope' => 'export'];
}
