<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * Workload of one laboratory/location for the technician. Snapshot columns
 * (active/open/in progress/critical/duplicates) and the period column
 * (resolvedInPeriod) are kept apart and labelled in the PDF.
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
        public readonly int $resolvedInPeriod,
        public readonly int $highCriticalActive,
        public readonly int $possibleDuplicateActive,
        public readonly int $possibleRecurrenceActive,
    ) {}
}
