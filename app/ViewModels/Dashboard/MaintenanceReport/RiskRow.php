<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * One deterministic operational risk derived from the report data. No AI and
 * no hidden scoring: every risk is reproducible from the same inputs.
 */
final class RiskRow
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /**
     * @param  list<string>  $relatedTickets  Display ids ("#TIC-xxxxxxxx") of affected tickets.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly int $affectedCount,
        public readonly string $detail,
        public readonly array $relatedTickets = [],
    ) {}

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'Crítico',
            self::SEVERITY_WARNING => 'Advertencia',
            default => 'Informativo',
        };
    }
}
