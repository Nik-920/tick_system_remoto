<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

use Carbon\CarbonInterface;

/**
 * One administrative closure within the period, sourced from a real
 * state_history transition (to_state = rejected | cancelled).
 *
 * - rejected: administrative closure decided by maintenance/admin.
 * - cancelled: withdrawal by the reporter; informative only, it never counts
 *   against the technician's productivity.
 */
final class ClosedTicketRow
{
    public const KIND_REJECTED = 'rejected';

    public const KIND_CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $id,
        public readonly string $idShort,
        public readonly string $title,
        public readonly string $locationName,
        public readonly string $categoryName,
        public readonly string $priority,
        public readonly string $kind,
        public readonly ?CarbonInterface $createdAt,
        public readonly ?CarbonInterface $closedAt,
        public readonly string $note,
    ) {}

    public function displayId(): string
    {
        return '#TIC-'.$this->idShort;
    }

    public function kindLabel(): string
    {
        return $this->kind === self::KIND_REJECTED ? 'Rechazado' : 'Cancelado';
    }
}
