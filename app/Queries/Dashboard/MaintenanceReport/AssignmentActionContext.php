<?php

declare(strict_types=1);

namespace App\Queries\Dashboard\MaintenanceReport;

use Carbon\CarbonImmutable;

/**
 * Groups per-ticket attributes needed to compute the deterministic recommended
 * action for an active assignment row (Fase 13 priority order).
 *
 * @property-read string                    $state
 * @property-read string                    $priority
 * @property-read int                       $ageDays
 * @property-read bool                      $hasFirstResponse
 * @property-read CarbonImmutable|null      $lastActivityAt
 * @property-read int                       $evidenceCount
 * @property-read array<string,mixed>|null  $duplicate
 * @property-read bool                      $isRecurrentPair
 */
final readonly class AssignmentActionContext
{
    /**
     * @param  array<string, mixed>|null  $duplicate
     */
    public function __construct(
        public string $state,
        public string $priority,
        public int $ageDays,
        public bool $hasFirstResponse,
        public ?CarbonImmutable $lastActivityAt,
        public int $evidenceCount,
        public ?array $duplicate,
        public bool $isRecurrentPair,
    ) {}
}
