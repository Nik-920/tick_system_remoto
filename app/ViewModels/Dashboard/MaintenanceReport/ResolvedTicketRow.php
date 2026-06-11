<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

use Carbon\CarbonInterface;

/**
 * One ticket resolved by the technician within the selected period
 * (clock: periodo, source: tickets.resolved_at).
 */
final class ResolvedTicketRow
{
    public function __construct(
        public readonly string $id,
        public readonly string $idShort,
        public readonly string $title,
        public readonly string $locationName,
        public readonly string $categoryName,
        public readonly string $priority,
        public readonly ?CarbonInterface $createdAt,
        public readonly ?CarbonInterface $resolvedAt,
        public readonly ?float $durationHours,
        public readonly int $evidenceCount,
    ) {}

    public function displayId(): string
    {
        return '#TIC-'.$this->idShort;
    }
}
