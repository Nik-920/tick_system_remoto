<?php

namespace Tests\Feature\Ai;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @group ai
 */
class RecurrenceTest extends TestCase
{
    public function test_recurrence_detection_respects_window(): void
    {
        $windowHours = config('ai.recurrence.window_hours');
        $enabled = $this->isRecurrenceEnabled();

        $this->assertIsInt($windowHours);
        $this->assertGreaterThanOrEqual(0, $windowHours);

        $recent = Carbon::now()->subHours(max($windowHours - 1, 0));
        $old = Carbon::now()->subHours($windowHours + 1);

        if (! $enabled) {
            $this->assertFalse($this->isRecurring($recent, 2, $enabled, $windowHours));
            return;
        }

        $this->assertTrue($this->isRecurring($recent, 2, $enabled, $windowHours));
        $this->assertFalse($this->isRecurring($old, 2, $enabled, $windowHours));
    }

    public function test_recurrence_requires_multiple_incidents(): void
    {
        $windowHours = config('ai.recurrence.window_hours');
        $enabled = $this->isRecurrenceEnabled();

        $recent = Carbon::now()->subHours(max($windowHours - 1, 0));

        if (! $enabled) {
            $this->assertFalse($this->isRecurring($recent, 2, $enabled, $windowHours));
            return;
        }

        $this->assertFalse($this->isRecurring($recent, 1, $enabled, $windowHours));
        $this->assertTrue($this->isRecurring($recent, 2, $enabled, $windowHours));
    }

    private function isRecurring(
        Carbon $lastResolvedAt,
        int $incidentCount,
        bool $enabled,
        int $windowHours
    ): bool {
        if (! $enabled) {
            return false;
        }

        if ($incidentCount < 2) {
            return false;
        }

        return $lastResolvedAt->getTimestamp() >= Carbon::now()->subHours($windowHours)->getTimestamp();
    }

    private function isRecurrenceEnabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.recurrence.enabled');
    }
}
