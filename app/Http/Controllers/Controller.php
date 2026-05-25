<?php

namespace App\Http\Controllers;

use App\Models\PartSetting;

abstract class Controller
{
    protected function findExactPartSetting(?string $partNumber): ?PartSetting
    {
        $partNumber = (string) $partNumber;
        if ($partNumber === '') {
            return null;
        }

        $partSetting = PartSetting::where('part_number', $partNumber)->first();
        if (!$partSetting || (string) $partSetting->part_number !== $partNumber) {
            return null;
        }

        return $partSetting;
    }
}
