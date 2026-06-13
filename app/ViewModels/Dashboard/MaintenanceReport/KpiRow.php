<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * One key indicator of the professional maintenance report.
 *
 * Every KPI declares its clock explicitly: 'snapshot' (state at generation
 * time) or 'periodo' (bounded by the selected date range). The PDF renders
 * the clock next to the value so snapshot and period figures are never mixed
 * silently.
 */
final class KpiRow
{
    public const CLOCK_SNAPSHOT = 'snapshot';

    public const CLOCK_PERIOD = 'periodo';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $value,
        public readonly string $clock,
        public readonly string $hint,
        public readonly string $tone = 'neutral',
        public readonly bool $lowSample = false,
    ) {}

    public function clockLabel(): string
    {
        return $this->clock === self::CLOCK_SNAPSHOT ? 'Snapshot' : 'Periodo';
    }
}
