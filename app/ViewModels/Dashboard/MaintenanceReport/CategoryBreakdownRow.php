<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * Frequency of one category across the technician's relevant tickets:
 * active assignments (snapshot) plus resolved in period. `percentage` is the
 * share of `totalRelevant` over the sum of all rows.
 */
final class CategoryBreakdownRow
{
    public function __construct(
        public readonly string $categoryName,
        public readonly int $activeCount,
        public readonly int $resolvedInPeriod,
        public readonly int $totalRelevant,
        public readonly float $percentage,
        public readonly int $possibleDuplicateActive,
        public readonly int $possibleRecurrenceActive,
    ) {}
}
