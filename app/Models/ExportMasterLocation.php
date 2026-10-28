<?php

namespace App\Models;

class ExportMasterLocation extends MasterLocation
{
    public const MASTER_SCOPE = 'export';

    protected $table = 'master_locations';

    protected $attributes = ['scope' => self::MASTER_SCOPE];
}
