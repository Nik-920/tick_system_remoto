<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard;

use App\Support\Dashboard\DateRange;
use App\ViewModels\Dashboard\MaintenanceReport\AssignmentRow;
use App\ViewModels\Dashboard\MaintenanceReport\CategoryBreakdownRow;
use App\ViewModels\Dashboard\MaintenanceReport\ClosedTicketRow;
use App\ViewModels\Dashboard\MaintenanceReport\DuplicateAlertRow;
use App\ViewModels\Dashboard\MaintenanceReport\KpiRow;
use App\ViewModels\Dashboard\MaintenanceReport\LocationBreakdownRow;
use App\ViewModels\Dashboard\MaintenanceReport\RecurrenceInsightRow;
use App\ViewModels\Dashboard\MaintenanceReport\ResolvedTicketRow;
use App\ViewModels\Dashboard\MaintenanceReport\RiskRow;
use App\ViewModels\Dashboard\MaintenanceReport\ScopeUniverseRow;
use Carbon\CarbonInterface;

/**
 * Read-only data carrier for the professional maintenance report (PDF +
 * HTML preview). Built once by MaintenanceReportQuery.
 *
 * This layer is deliberately separate from MaintenanceDashboardViewModel: the
 * live dashboard keeps its own query/view model and neither depends on the
 * other.
 *
 * Contracts honoured here:
 * - Every personal metric is scoped to assigned_to = technician.
 * - Every figure declares its clock (snapshot vs periodo); see KpiRow.
 * - globalQueueContext is area context only; it never feeds personal KPIs.
 * - duplicateAlerts are operational signals (quality of attention), never
 *   productivity metrics, and never penalise the technician.
 */
final class MaintenanceReportViewModel
{
    /**
     * @param  array{id: string, name: string, email: string}  $technician
     * @param  array{headlines: list<array{label: string, value: string}>, narrative: list<string>}  $executiveSummary
     * @param  list<ScopeUniverseRow>  $scope
     * @param  list<KpiRow>  $kpis
     * @param  list<AssignmentRow>  $currentAssignments
     * @param  array{critical: list<AssignmentRow>, staleOpen: list<AssignmentRow>, staleInProgress: list<AssignmentRow>}  $criticalAndDelayed
     * @param  list<LocationBreakdownRow>  $locationBreakdown
     * @param  list<CategoryBreakdownRow>  $categoryBreakdown
     * @param  list<ResolvedTicketRow>  $resolvedTickets
     * @param  list<ClosedTicketRow>  $rejectedTickets
     * @param  list<ClosedTicketRow>  $cancelledTickets
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $timeMetrics
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidenceSummary
     * @param  list<RecurrenceInsightRow>  $recurrenceInsights
     * @param  list<DuplicateAlertRow>  $duplicateAlerts
     * @param  list<RiskRow>  $risks
     * @param  list<string>  $recommendations
     * @param  array{count: int, preview: list<array{idShort: string, title: string, locationName: string, priority: string, ageDays: int}>}  $globalQueueContext
     * @param  array{active: int, pendingReview: int, suggestsRecurrence: int, legacyWithoutStrategy: int, allZero: bool}  $aiSummary
     * @param  array{rows: list<array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}>, truncated: bool, totalRows: int}  $appendixTickets
     * @param  list<array{term: string, definition: string}>  $definitions
     * @param  list<array{check: string, detail: string, count: int}>  $dataQuality
     */
    public function __construct(
        public readonly array $technician,
        public readonly DateRange $period,
        public readonly CarbonInterface $generatedAt,
        public readonly array $executiveSummary,
        public readonly array $scope,
        public readonly array $kpis,
        public readonly array $currentAssignments,
        public readonly array $criticalAndDelayed,
        public readonly array $locationBreakdown,
        public readonly array $categoryBreakdown,
        public readonly array $resolvedTickets,
        public readonly array $rejectedTickets,
        public readonly array $cancelledTickets,
        public readonly array $timeMetrics,
        public readonly array $evidenceSummary,
        public readonly array $recurrenceInsights,
        public readonly array $duplicateAlerts,
        public readonly array $risks,
        public readonly array $recommendations,
        public readonly array $globalQueueContext,
        public readonly array $aiSummary,
        public readonly array $appendixTickets,
        public readonly array $definitions,
        public readonly array $dataQuality,
    ) {}

    /** KPIs whose clock is the current snapshot. @return list<KpiRow> */
    public function snapshotKpis(): array
    {
        return array_values(array_filter(
            $this->kpis,
            static fn (KpiRow $kpi): bool => $kpi->clock === KpiRow::CLOCK_SNAPSHOT,
        ));
    }

    /** KPIs bounded by the selected period. @return list<KpiRow> */
    public function periodKpis(): array
    {
        return array_values(array_filter(
            $this->kpis,
            static fn (KpiRow $kpi): bool => $kpi->clock === KpiRow::CLOCK_PERIOD,
        ));
    }
}
