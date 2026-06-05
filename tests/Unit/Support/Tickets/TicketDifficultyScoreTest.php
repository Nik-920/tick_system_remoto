<?php

namespace Tests\Unit\Support\Tickets;

use App\Support\Tickets\TicketDifficultyScore;
use PHPUnit\Framework\TestCase;

class TicketDifficultyScoreTest extends TestCase
{
    public function test_priority_weights_are_applied(): void
    {
        $this->assertSame(40, TicketDifficultyScore::calculate('critical', 0));
        $this->assertSame(25, TicketDifficultyScore::calculate('high', 0));
        $this->assertSame(10, TicketDifficultyScore::calculate('medium', 0));
        $this->assertSame(5, TicketDifficultyScore::calculate('low', 0));
    }

    public function test_unknown_priority_falls_back_to_low_weight(): void
    {
        $this->assertSame(5, TicketDifficultyScore::calculate('whatever', 0));
        $this->assertSame(5, TicketDifficultyScore::calculate('', 0));
    }

    public function test_age_weight_grows_and_caps(): void
    {
        // low (5) + min(30, 5*2)=10 => 15
        $this->assertSame(15, TicketDifficultyScore::calculate('low', 5));
        // low (5) + cap 30 => 35
        $this->assertSame(35, TicketDifficultyScore::calculate('low', 100));
    }

    public function test_recurrence_weight_grows_and_caps(): void
    {
        // low (5) + recurrence 3*4=12 => 17
        $this->assertSame(17, TicketDifficultyScore::calculate('low', 0, 3));
        // low (5) + cap 20 => 25
        $this->assertSame(25, TicketDifficultyScore::calculate('low', 0, 100));
    }

    public function test_signal_weights_are_additive(): void
    {
        // low (5) + duplicate 10 + media 5 => 20
        $this->assertSame(20, TicketDifficultyScore::calculate('low', 0, 0, true, true));
        $this->assertSame(15, TicketDifficultyScore::calculate('low', 0, 0, true, false));
        $this->assertSame(10, TicketDifficultyScore::calculate('low', 0, 0, false, true));
    }

    public function test_critical_recent_outranks_low_aged_ticket(): void
    {
        $critical = TicketDifficultyScore::calculate('critical', 0);
        $lowButOld = TicketDifficultyScore::calculate('low', 10);

        $this->assertGreaterThan($lowButOld, $critical);
    }
}
