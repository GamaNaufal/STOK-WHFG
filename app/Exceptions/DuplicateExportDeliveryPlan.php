<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateExportDeliveryPlan extends RuntimeException
{
    public function __construct(public readonly array $review)
    {
        parent::__construct('Rencana identik sudah tercatat. Tinjau rencana pembanding dan isi alasan jika memang perlu membuat permintaan yang sama.');
    }
}
