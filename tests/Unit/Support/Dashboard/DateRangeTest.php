<?php

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\DateRange;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    public function test_default_is_last_30_days(): void
    {
        $range = DateRange::default();

        $this->assertSame(DateRange::PRESET_LAST_30_DAYS, $range->preset);
        $this->assertSame('2026-05-17', $range->fromDate());
        $this->assertSame('2026-06-15', $range->toDate());
        $this->assertSame(30, $range->days());
    }

    public function test_resolve_without_input_falls_back_to_default(): void
    {
        $range = DateRange::resolve(null, null, null);

        $this->assertSame(DateRange::PRESET_LAST_30_DAYS, $range->preset);
        $this->assertSame(30, $range->days());
    }

    public function test_today_preset_spans_one_day(): void
    {
        $range = DateRange::resolve('today', null, null);

        $this->assertSame('2026-06-15', $range->fromDate());
        $this->assertSame('2026-06-15', $range->toDate());
        $this->assertSame(1, $range->days());
    }

    public function test_last_7_days_preset_spans_seven_days(): void
    {
        $range = DateRange::resolve('last_7_days', null, null);

        $this->assertSame('2026-06-09', $range->fromDate());
        $this->assertSame(7, $range->days());
    }

    public function test_this_month_preset(): void
    {
        $range = DateRange::resolve('this_month', null, null);

        $this->assertSame('2026-06-01', $range->fromDate());
        $this->assertSame('2026-06-30', $range->toDate());
    }

    public function test_previous_month_preset_handles_month_lengths(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31 09:00:00'));

        $range = DateRange::resolve('previous_month', null, null);

        $this->assertSame('2026-02-01', $range->fromDate());
        $this->assertSame('2026-02-28', $range->toDate());
    }

    public function test_custom_range_is_accepted(): void
    {
        $range = DateRange::resolve('custom', '2026-01-01', '2026-01-10');

        $this->assertTrue($range->isCustom());
        $this->assertSame('2026-01-01', $range->fromDate());
        $this->assertSame('2026-01-10', $range->toDate());
        $this->assertSame(10, $range->days());
    }

    public function test_custom_range_with_from_after_to_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateRange::custom('2026-02-01', '2026-01-01');
    }

    public function test_custom_range_exceeding_max_days_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateRange::custom('2025-01-01', '2026-12-31');
    }

    public function test_query_params_reproduce_preset_and_custom(): void
    {
        $this->assertSame(['preset' => 'last_30_days'], DateRange::default()->toQueryParams());

        $this->assertSame(
            ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-10'],
            DateRange::custom('2026-01-01', '2026-01-10')->toQueryParams()
        );
    }
}
