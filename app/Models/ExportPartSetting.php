<?php

namespace App\Models;

class ExportPartSetting extends PartSetting
{
    public const MASTER_SCOPE = 'export';

    protected $table = 'part_settings';

    protected $attributes = ['scope' => self::MASTER_SCOPE];
}
