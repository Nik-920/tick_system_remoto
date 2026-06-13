<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

use Carbon\CarbonInterface;

/**
 * One historical recurrence insight from location_incident_history for a
 * (location, category) pair present in the technician's active assignments.
 * avg_resolution_time is a free-form string in the schema, so it is surfaced
 * verbatim as reference text — never used for arithmetic.
 */
final class RecurrenceInsightRow
{
    public function __construct(
        public readonly string $locationName,
        public readonly string $categoryName,
        public readonly int $recurrenceCount,
        public readonly ?CarbonInterface $lastResolvedAt,
        public readonly string $avgResolutionLabel,
        public readonly string $recommendation,
    ) {}
}
