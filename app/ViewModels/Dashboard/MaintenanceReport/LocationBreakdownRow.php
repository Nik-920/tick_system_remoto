<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * Workload of one laboratory/location for the technician. Snapshot columns
 * (active/open/in progress/critical/duplicates) and period columns
 * (created/resolved/rejected/cancelled) are kept apart and labelled in the
 * PDF. `totalRelevant` counts distinct tickets across both clocks.
 */
final class LocationBreakdownRow
{
    public function __construct(
        public readonly string $locationName,
        public readonly string $building,
        public readonly string $floor,
        public readonly string $roomCode,
        public readonly int $activeCount,
        public readonly int $openCount,
        public readonly int $inProgressCount,
        public readonly int $createdInPeriod,
        public readonly int $resolvedInPeriod,
        public readonly int $rejectedInPeriod,
        public readonly int $cancelledInPeriod,
        public readonly int $totalRelevant,
        public readonly int $highCriticalActive,
        public readonly int $possibleDuplicateActive,
        public readonly int $possibleRecurrenceActive,
    ) {}
}
