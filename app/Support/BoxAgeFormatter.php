<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class BoxAgeFormatter
{
    public static function format(DateTimeInterface|string|null $storedAt, DateTimeInterface|string|null $asOf = null): string
    {
        if (! $storedAt) {
            return '-';
        }

        $start = CarbonImmutable::parse($storedAt)->startOfDay();
        $end = CarbonImmutable::parse($asOf ?? now())->startOfDay();
        if ($end->lt($start)) {
            return '0 bulan 0 hari';
        }

        $months = ($end->year - $start->year) * 12 + $end->month - $start->month;
        if ($start->addMonthsNoOverflow($months)->gt($end)) {
            $months--;
        }
        $days = (int) $start->addMonthsNoOverflow($months)->diffInDays($end);

        return "{$months} bulan {$days} hari";
    }
}
