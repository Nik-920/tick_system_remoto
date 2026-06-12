<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * Frequency of one category across the technician's relevant tickets:
 * active assignments (snapshot) plus created/resolved/rejected/cancelled in
 * the period. `totalRelevant` counts distinct tickets and `percentage` is its
 * share over the sum of all rows.
 */
final class CategoryBreakdownRow
{
    public function __construct(
        public readonly string $categoryName,
        public readonly int $activeCount,
        public readonly int $createdInPeriod,
        public readonly int $resolvedInPeriod,
        public readonly int $rejectedInPeriod,
        public readonly int $cancelledInPeriod,
        public readonly int $totalRelevant,
        public readonly float $percentage,
        public readonly int $possibleDuplicateActive,
        public readonly int $possibleRecurrenceActive,
    ) {}
}
