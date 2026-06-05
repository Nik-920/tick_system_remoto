<?php

declare(strict_types=1);

namespace App\Support\Tickets;

/**
 * Pure, side-effect-free urgency/difficulty score for a maintenance ticket.
 *
 * The score is computed from primitives so it stays portable and unit-testable;
 * the caller is responsible for resolving the inputs (priority, age, recurrence,
 * AI/evidence signals) without N+1 queries.
 *
 * Higher score = should be attended sooner.
 */
final class TicketDifficultyScore
{
    /** @var array<string, int> */
    private const PRIORITY_WEIGHTS = [
        'critical' => 40,
        'high' => 25,
        'medium' => 10,
        'low' => 5,
    ];

    private const FALLBACK_PRIORITY_WEIGHT = 5;

    private const AGE_WEIGHT_PER_DAY = 2;

    private const AGE_WEIGHT_CAP = 30;

    private const RECURRENCE_WEIGHT_PER_HIT = 4;

    private const RECURRENCE_WEIGHT_CAP = 20;

    private const DUPLICATE_WEIGHT = 10;

    private const MEDIA_WEIGHT = 5;

    public static function calculate(
        string $priority,
        int $daysOpen,
        int $recurrenceCount = 0,
        bool $hasEffectiveDuplicate = false,
        bool $hasMedia = false,
    ): int {
        return self::priorityWeight($priority)
            + self::ageWeight($daysOpen)
            + self::recurrenceWeight($recurrenceCount)
            + self::signalWeight($hasEffectiveDuplicate, $hasMedia);
    }

    public static function priorityWeight(string $priority): int
    {
        return self::PRIORITY_WEIGHTS[strtolower(trim($priority))] ?? self::FALLBACK_PRIORITY_WEIGHT;
    }

    private static function ageWeight(int $daysOpen): int
    {
        $days = max(0, $daysOpen);

        return min(self::AGE_WEIGHT_CAP, $days * self::AGE_WEIGHT_PER_DAY);
    }

    private static function recurrenceWeight(int $recurrenceCount): int
    {
        $count = max(0, $recurrenceCount);

        return min(self::RECURRENCE_WEIGHT_CAP, $count * self::RECURRENCE_WEIGHT_PER_HIT);
    }

    private static function signalWeight(bool $hasEffectiveDuplicate, bool $hasMedia): int
    {
        return ($hasEffectiveDuplicate ? self::DUPLICATE_WEIGHT : 0)
            + ($hasMedia ? self::MEDIA_WEIGHT : 0);
    }
}
