<?php

namespace App\Support;

final class DeliveryPlanCode
{
    public static function normalize(?string $value): string
    {
        return mb_strtoupper(trim($value ?? ''), 'UTF-8');
    }
}
