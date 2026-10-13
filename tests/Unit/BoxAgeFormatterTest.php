<?php

namespace Tests\Unit;

use App\Support\BoxAgeFormatter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BoxAgeFormatterTest extends TestCase
{
    public function test_formats_total_calendar_months_and_remaining_days(): void
    {
        $this->assertSame('10 bulan 12 hari', BoxAgeFormatter::format('2025-12-01', '2026-10-13'));
        $this->assertSame('25 bulan 3 hari', BoxAgeFormatter::format('2024-09-10', '2026-10-13'));
        $this->assertSame('0 bulan 5 hari', BoxAgeFormatter::format('2026-10-08', '2026-10-13'));
    }

    public function test_month_end_and_leap_year_use_calendar_months_without_overflow(): void
    {
        $this->assertSame('1 bulan 0 hari', BoxAgeFormatter::format('2026-01-31', '2026-02-28'));
        $this->assertSame('1 bulan 0 hari', BoxAgeFormatter::format('2024-01-31', '2024-02-29'));
        $this->assertSame('1 bulan 30 hari', BoxAgeFormatter::format('2026-01-31', '2026-03-30'));
        $this->assertSame('2 bulan 0 hari', BoxAgeFormatter::format('2026-01-31', '2026-03-31'));
    }

    public function test_history_age_uses_supplied_handling_date_and_preserves_input(): void
    {
        $storedAt = CarbonImmutable::parse('2025-12-01 15:30:00');
        $handledAt = CarbonImmutable::parse('2026-09-13 10:00:00');
        $this->assertSame('9 bulan 12 hari', BoxAgeFormatter::format($storedAt, $handledAt));
        $this->assertSame('2025-12-01 15:30:00', $storedAt->toDateTimeString());
        $this->assertSame('2026-09-13 10:00:00', $handledAt->toDateTimeString());
    }

    public function test_missing_and_future_storage_dates_do_not_show_negative_age(): void
    {
        $this->assertSame('-', BoxAgeFormatter::format(null, '2026-10-13'));
        $this->assertSame('0 bulan 0 hari', BoxAgeFormatter::format('2026-10-14', '2026-10-13'));
    }
}
