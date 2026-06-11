<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

use Carbon\CarbonInterface;

/**
 * One active assignment (open / in_progress) of the technician, fully
 * denormalised for the PDF: location, category, age, last real transition
 * (state_history), first response, evidence coverage, the deterministic
 * operational sort rank and — when the AI flagged the ticket — the duplicate
 * warning with its human-readable explanation.
 *
 * Duplicate fields are nullable/false when there is no effective AI duplicate
 * for the ticket. The explanation comes from persisted Strategy metadata via
 * DuplicateExplanationPresenter; nothing is recalculated here.
 */
final class AssignmentRow
{
    /**
     * @param  array{toState: string, at: CarbonInterface, comment: string}|null  $lastTransition
     * @param  array<int, array{label: string, detail: string}>  $duplicateTopReasons
     */
    public function __construct(
        public readonly string $id,
        public readonly string $idShort,
        public readonly string $title,
        public readonly string $locationName,
        public readonly string $building,
        public readonly string $floor,
        public readonly string $roomCode,
        public readonly string $categoryName,
        public readonly string $priority,
        public readonly string $state,
        public readonly ?CarbonInterface $createdAt,
        public readonly int $ageDays,
        public readonly ?CarbonInterface $assignedAt,
        public readonly ?array $lastTransition,
        public readonly ?CarbonInterface $firstResponseAt,
        public readonly bool $hasFirstResponse,
        public readonly int $evidenceCount,
        public readonly bool $hasEvidence,
        public readonly string $recommendedAction,
        public readonly int $sortRank,
        public readonly bool $hasDuplicateWarning = false,
        public readonly ?float $duplicateSimilarity = null,
        public readonly ?int $duplicateStrategyScore = null,
        public readonly ?string $duplicateMatchedTicketId = null,
        public readonly ?string $duplicateMatchedTicketTitle = null,
        public readonly ?string $duplicateReviewStatus = null,
        public readonly bool $duplicateSuggestsRecurrence = false,
        public readonly array $duplicateTopReasons = [],
        public readonly ?string $duplicateExplanationSummary = null,
    ) {}

    /** Display id in the documented "#TIC-xxxxxxxx" form (first 8 UUID chars). */
    public function displayId(): string
    {
        return '#TIC-'.$this->idShort;
    }
}
