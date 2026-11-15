<?php

namespace Tests\Unit;

use App\Services\OperationalReportService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class OperationalReportRangeTest extends TestCase
{
    public function test_long_history_keeps_observed_dates_without_generating_empty_calendar_years(): void
    {
        $days = new \ReflectionMethod(OperationalReportService::class, 'reportDays');
        $result = $days->invoke(new OperationalReportService, Carbon::parse('1900-01-01'), Carbon::parse('2100-01-01'),
            collect(['2026-01-01' => 12]), collect(['2026-01-01' => 3, '2026-02-01' => 8]));
        $this->assertSame(['2026-01-01', '2026-02-01'], collect($result)->map(fn ($day) => $day->toDateString())->values()->all());
    }

    public function test_short_range_still_includes_days_without_activity(): void
    {
        $days = new \ReflectionMethod(OperationalReportService::class, 'reportDays');
        $result = $days->invoke(new OperationalReportService, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-03')->endOfDay(), collect(), collect());
        $this->assertSame(['2026-01-01', '2026-01-02', '2026-01-03'], collect($result)->map(fn ($day) => $day->toDateString())->values()->all());
    }
}
