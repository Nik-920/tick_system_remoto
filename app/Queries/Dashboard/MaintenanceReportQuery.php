<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use App\Support\Tickets\DuplicateExplanationPresenter;
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
use App\ViewModels\Dashboard\MaintenanceReportViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the professional maintenance report for the authenticated technician.
 *
 * Dedicated layer: it never touches MaintenanceDashboardQuery/ViewModel, so
 * the live dashboard keeps working unchanged.
 *
 * Security contract (mirrors MaintenanceDashboardQuery): every personal
 * metric is bounded by assigned_to = technician. The only global figures are
 * the "cola global disponible" (open, unassigned, unlocked), surfaced as area
 * context and never mixed into personal KPIs.
 *
 * Two clocks: current assignments and AI-duplicate signals are a snapshot at
 * generation time and ignore the date range; resolved/rejected/cancelled/
 * created figures and time metrics are bounded by the range. Each KpiRow and
 * ScopeUniverseRow declares its clock explicitly.
 *
 * Portability: aggregates use plain Eloquent/Query Builder; medians,
 * durations, last transitions and groupings are computed in PHP from bounded
 * rows, so the same code runs on SQLite (tests) and PostgreSQL/Supabase. No
 * stored procedures, no SQL views, no Postgres-only syntax.
 *
 * AI duplicates: explanations are read from the persisted Strategy metadata
 * (ticket_embeddings.strategy_results) through DuplicateExplanationPresenter.
 * Nothing is recalculated here and raw JSON is never exposed. Duplicates are
 * operational signals: they never feed the close rate nor penalise the
 * technician.
 */
final class MaintenanceReportQuery
{
    /** @var list<string> */
    private const ACTIVE_STATES = [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS];

    /** @var list<string> */
    private const URGENT_PRIORITIES = ['critical', 'high'];

    /** @var list<string> */
    private const KNOWN_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
        Ticket::STATE_REJECTED,
        Ticket::STATE_CANCELLED,
    ];

    /** @var list<string> */
    private const KNOWN_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const STALE_OPEN_DAYS = 3;

    private const STALE_IN_PROGRESS_DAYS = 5;

    private const DUPLICATE_REVIEW_OVERDUE_DAYS = 5;

    private const LOW_SAMPLE_THRESHOLD = 5;

    private const HIGH_SIMILARITY_THRESHOLD = 0.90;

    private const HIGH_FIRST_RESPONSE_HOURS = 24.0;

    private const LOW_CLOSURE_RATE_PCT = 25.0;

    private const LARGE_GLOBAL_QUEUE = 5;

    private const RELEVANT_RECURRENCE_COUNT = 2;

    private const GLOBAL_QUEUE_PREVIEW_LIMIT = 8;

    private const APPENDIX_LIMIT = 60;

    private const RECOMMENDATIONS_LIMIT = 8;

    private const ID_SHORT_LENGTH = 8;

    // ── Shared UI labels (KPIs, summary, glossary) ───────────────────────────

    private const LABEL_UNCATEGORIZED = 'Sin categoría';

    private const LABEL_NO_LOCATION = 'Sin ubicación';

    private const TICKET_REFERENCE_PREFIX = '#TIC-';

    private const LABEL_ACTIVE_ASSIGNED = 'Asignados activos';

    private const LABEL_IN_PROGRESS = 'En progreso';

    private const LABEL_GLOBAL_QUEUE_AVAILABLE = 'Cola global disponible';

    private const LABEL_RESOLVED_IN_PERIOD = 'Resueltos en periodo';

    private const SUFFIX_DAYS = ' días';

    private readonly CarbonImmutable $now;

    public function __construct(
        private readonly User $technician,
        private readonly DateRange $range,
    ) {
        $this->now = CarbonImmutable::now();
    }

    public static function for(User $technician, DateRange $range): MaintenanceReportViewModel
    {
        return (new self($technician, $range))->build();
    }

    public function build(): MaintenanceReportViewModel
    {
        // ── Universe B: active assignments (snapshot, ignores the range) ─────
        $activeTickets = $this->mine()
            ->whereIn('state', self::ACTIVE_STATES)
            ->with(['location', 'category', 'embedding.matchedTicket'])
            ->withCount('media')
            ->get();

        // ── Universe D: resolved within the period (resolved_at) ─────────────
        $resolvedModels = $this->mine()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$this->range->from, $this->range->to])
            ->with(['location', 'category'])
            ->withCount('media')
            ->orderByDesc('resolved_at')
            ->get();

        // ── Universe G: created within the period (created_at) ───────────────
        $createdModels = $this->mine()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->with(['location', 'category'])
            ->orderByDesc('created_at')
            ->get();

        $historyByTicket = $this->historyByTicket(
            $activeTickets->pluck('id')
                ->merge($resolvedModels->pluck('id'))
                ->unique()
                ->values()
                ->all()
        );

        // ── Universes E/F: administrative closures (real transitions) ────────
        $rejectedClosures = $this->administrativeClosures(Ticket::STATE_REJECTED);
        $cancelledClosures = $this->administrativeClosures(Ticket::STATE_CANCELLED);

        // Recurrences look at every (location, category) pair relevant to the
        // report — active, created in period, resolved and administrative
        // closures — not only active assignments.
        $recurrenceBase = $activeTickets->toBase()
            ->concat($createdModels)
            ->concat($resolvedModels)
            ->concat($rejectedClosures['models'])
            ->concat($cancelledClosures['models'])
            ->unique('id')
            ->values();
        $recurrenceHistories = $this->recurrenceHistories($recurrenceBase);

        // AI duplicate signal per active ticket (persisted metadata only).
        $duplicateInfoByTicket = [];
        foreach ($activeTickets as $ticket) {
            $info = $this->duplicateInfoFor($ticket);
            if ($info !== null) {
                $duplicateInfoByTicket[(string) $ticket->id] = $info;
            }
        }

        $assignments = $this->assignmentRows($activeTickets, $historyByTicket, $recurrenceHistories, $duplicateInfoByTicket);
        $resolvedRows = $this->resolvedRows($resolvedModels);
        $rejectedRows = $rejectedClosures['rows'];
        $cancelledRows = $cancelledClosures['rows'];
        $duplicateAlerts = $this->duplicateAlerts($activeTickets, $duplicateInfoByTicket);

        // ── Counters ─────────────────────────────────────────────────────────
        $activeCount = $assignments === [] ? 0 : count($assignments);
        $openCount = $this->countAssignments($assignments, Ticket::STATE_OPEN);
        $inProgressCount = $this->countAssignments($assignments, Ticket::STATE_IN_PROGRESS);
        $criticalHigh = array_values(array_filter(
            $assignments,
            static fn (AssignmentRow $row): bool => in_array($row->priority, self::URGENT_PRIORITIES, true),
        ));
        $staleOpen = array_values(array_filter(
            $assignments,
            fn (AssignmentRow $row): bool => $row->state === Ticket::STATE_OPEN
                && $row->ageDays > self::STALE_OPEN_DAYS,
        ));
        $staleInProgress = array_values(array_filter(
            $assignments,
            fn (AssignmentRow $row): bool => $row->state === Ticket::STATE_IN_PROGRESS
                && $this->lastActivityAt($row)->lessThan($this->now->subDays(self::STALE_IN_PROGRESS_DAYS)),
        ));

        $resolvedCount = count($resolvedRows);
        $rejectedCount = count($rejectedRows);
        $cancelledCount = count($cancelledRows);
        $closedCount = $resolvedCount + $rejectedCount; // cancelled stays out on purpose.
        $createdInPeriod = $createdModels->count();
        $assignedAllTime = $this->mine()->count();

        // Close rate: resolved / (resolved + active). Cancelled and AI
        // duplicates never enter this formula. With an empty denominator the
        // metric does not apply (null → "Sin datos"), never a misleading 0 %.
        $closeDenominator = $resolvedCount + $activeCount;
        $closeRate = $closeDenominator > 0 ? round($resolvedCount / $closeDenominator * 100, 1) : null;

        $timeMetrics = $this->timeMetrics($resolvedRows);
        $evidenceSummary = $this->evidenceSummary($assignments);
        $globalQueue = $this->globalQueueContext();

        // AI signal counters (operational, not productivity).
        $duplicatesActive = count($duplicateAlerts);
        $duplicatesPending = $this->countDuplicates($duplicateInfoByTicket, 'pendingReview');
        $recurrenceSuggested = $this->countDuplicates($duplicateInfoByTicket, 'suggestsRecurrence');
        $duplicatesLegacy = count(array_filter(
            $duplicateInfoByTicket,
            static fn (array $info): bool => ! $info['hasStrategyMetadata'],
        ));

        $recurrenceInsights = $this->recurrenceInsights($recurrenceHistories);
        $dataQuality = $this->dataQuality($activeTickets, $historyByTicket, $duplicateInfoByTicket);

        $locationBreakdown = $this->locationBreakdown(
            $activeTickets,
            $createdModels,
            $resolvedModels,
            $rejectedClosures['models'],
            $cancelledClosures['models'],
            $duplicateInfoByTicket,
        );
        $categoryBreakdown = $this->categoryBreakdown(
            $activeTickets,
            $createdModels,
            $resolvedModels,
            $rejectedClosures['models'],
            $cancelledClosures['models'],
            $duplicateInfoByTicket,
        );

        $risks = $this->risks(
            $assignments,
            $staleOpen,
            $staleInProgress,
            $evidenceSummary,
            $closeRate,
            $activeCount,
            $timeMetrics,
            $recurrenceInsights,
            $globalQueue['count'],
            $duplicateInfoByTicket,
            $dataQuality,
        );

        $recommendations = $this->recommendations(
            count($criticalHigh),
            count($staleOpen),
            $duplicatesPending,
            $recurrenceSuggested,
            $duplicatesLegacy,
            $evidenceSummary,
            $timeMetrics,
            $resolvedCount,
            $locationBreakdown,
            $categoryBreakdown,
            $recurrenceInsights,
            $globalQueue['count'],
            $activeCount,
        );

        $aiSummary = [
            'active' => $duplicatesActive,
            'pendingReview' => $duplicatesPending,
            'suggestsRecurrence' => $recurrenceSuggested,
            'legacyWithoutStrategy' => $duplicatesLegacy,
            'allZero' => ($duplicatesActive + $duplicatesPending + $recurrenceSuggested + $duplicatesLegacy) === 0,
        ];

        return new MaintenanceReportViewModel(
            technician: [
                'id' => $this->userId(),
                'name' => $this->technicianName(),
                'email' => (string) ($this->technician->email ?? ''),
            ],
            period: $this->range,
            generatedAt: $this->now,
            executiveSummary: $this->executiveSummary(
                $activeCount,
                $openCount,
                $inProgressCount,
                count($criticalHigh),
                count($staleOpen),
                $resolvedCount,
                $rejectedCount,
                $cancelledCount,
                $evidenceSummary,
                $duplicatesActive,
                $duplicatesPending,
                $globalQueue['count'],
                $assignedAllTime,
                $createdInPeriod,
            ),
            scope: $this->scopeRows(
                $assignedAllTime,
                $activeCount,
                $globalQueue['count'],
                $resolvedCount,
                $rejectedCount,
                $cancelledCount,
                $createdInPeriod,
                $closedCount,
                $duplicatesActive,
            ),
            kpis: $this->kpis(
                $activeCount,
                $openCount,
                $inProgressCount,
                count($criticalHigh),
                count($staleOpen),
                $evidenceSummary,
                $globalQueue['count'],
                $duplicatesActive,
                $resolvedCount,
                $rejectedCount,
                $cancelledCount,
                $createdInPeriod,
                $closedCount,
                $timeMetrics,
                $closeRate,
            ),
            currentAssignments: $assignments,
            criticalAndDelayed: [
                'critical' => $criticalHigh,
                'staleOpen' => $staleOpen,
                'staleInProgress' => $staleInProgress,
            ],
            locationBreakdown: $locationBreakdown,
            categoryBreakdown: $categoryBreakdown,
            resolvedTickets: $resolvedRows,
            rejectedTickets: $rejectedRows,
            cancelledTickets: $cancelledRows,
            timeMetrics: $timeMetrics,
            evidenceSummary: $evidenceSummary,
            recurrenceInsights: $recurrenceInsights,
            duplicateAlerts: $duplicateAlerts,
            risks: $risks,
            recommendations: $recommendations,
            globalQueueContext: $globalQueue,
            aiSummary: $aiSummary,
            appendixTickets: $this->appendix($assignments, $resolvedRows, $rejectedRows, $cancelledRows, $createdModels),
            definitions: $this->definitions(),
            dataQuality: $dataQuality,
        );
    }

    // ── Base scopes ──────────────────────────────────────────────────────────

    /** @return Builder<Ticket> */
    private function mine(): Builder
    {
        return Ticket::query()->where('assigned_to', $this->userId());
    }

    private function userId(): string
    {
        return (string) $this->technician->id;
    }

    private function technicianName(): string
    {
        $name = trim((string) ($this->technician->name ?? '').' '.(string) ($this->technician->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $email = (string) ($this->technician->email ?? '');

        return $email !== '' ? $email : $this->userId();
    }

    // ── State history (portable: no DISTINCT ON, grouped in PHP) ─────────────

    /**
     * Full transition history for the given tickets, ascending by date.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>
     */
    private function historyByTicket(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $rows = DB::table('state_history')
            ->whereIn('ticket_id', $ticketIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['ticket_id', 'to_state', 'comment', 'created_at']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->ticket_id][] = [
                'toState' => (string) $row->to_state,
                'at' => CarbonImmutable::parse((string) $row->created_at),
                'comment' => (string) ($row->comment ?? ''),
            ];
        }

        return $grouped;
    }

    /**
     * @param  array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>  $history
     * @return array{toState: string, at: CarbonImmutable, comment: string}|null
     */
    private function lastTransitionFor(string $ticketId, array $history): ?array
    {
        $entries = $history[$ticketId] ?? [];

        return $entries === [] ? null : $entries[count($entries) - 1];
    }

    /**
     * First real transition to in_progress = first response.
     *
     * @param  array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>  $history
     */
    private function firstResponseFor(string $ticketId, array $history): ?CarbonImmutable
    {
        foreach ($history[$ticketId] ?? [] as $entry) {
            if ($entry['toState'] === Ticket::STATE_IN_PROGRESS) {
                return $entry['at'];
            }
        }

        return null;
    }

    // ── Assignments (Universe B) ─────────────────────────────────────────────

    /**
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>  $history
     * @param  array<string, LocationIncidentHistory>  $recurrenceHistories
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<AssignmentRow>
     */
    private function assignmentRows(
        EloquentCollection $activeTickets,
        array $history,
        array $recurrenceHistories,
        array $duplicateInfoByTicket,
    ): array {
        $rows = [];

        foreach ($activeTickets as $ticket) {
            $duplicate = $duplicateInfoByTicket[(string) $ticket->id] ?? null;
            $isRecurrentPair = isset($recurrenceHistories[$ticket->location_id.'|'.$ticket->category_id]);

            $rows[] = $this->assignmentRowFor($ticket, $history, $duplicate, $isRecurrentPair);
        }

        // Deterministic operational order: critical > high > (open, oldest,
        // no-first-response first) > in_progress oldest > medium > low.
        // Global tiebreak: created_at ASC, then id for full determinism.
        usort($rows, static function (AssignmentRow $a, AssignmentRow $b): int {
            if ($a->sortRank !== $b->sortRank) {
                return $a->sortRank <=> $b->sortRank;
            }

            $aCreated = $a->createdAt?->getTimestamp() ?? PHP_INT_MAX;
            $bCreated = $b->createdAt?->getTimestamp() ?? PHP_INT_MAX;

            if ($aCreated !== $bCreated) {
                return $aCreated <=> $bCreated;
            }

            return strcmp($a->id, $b->id);
        });

        return array_values($rows);
    }

    /**
     * @param  array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>  $history
     * @param  array<string, mixed>|null  $duplicate
     */
    private function assignmentRowFor(
        Ticket $ticket,
        array $history,
        ?array $duplicate,
        bool $isRecurrentPair,
    ): AssignmentRow {
        $ticketId = (string) $ticket->id;
        $createdAt = $ticket->created_at !== null ? CarbonImmutable::parse($ticket->created_at) : null;
        $ageDays = $createdAt !== null ? (int) floor($createdAt->diffInDays($this->now)) : 0;
        $lastTransition = $this->lastTransitionFor($ticketId, $history);
        $firstResponseAt = $this->firstResponseFor($ticketId, $history);
        $evidenceCount = (int) $ticket->getAttribute('media_count');

        return new AssignmentRow(
            id: $ticketId,
            idShort: $this->idShort($ticketId),
            title: (string) $ticket->title,
            locationName: (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
            building: (string) ($ticket->location->building ?? ''),
            floor: (string) ($ticket->location->floor ?? ''),
            roomCode: (string) ($ticket->location->room_code ?? ''),
            categoryName: (string) ($ticket->category->name ?? self::LABEL_UNCATEGORIZED),
            priority: (string) $ticket->priority,
            state: (string) $ticket->state,
            createdAt: $createdAt,
            ageDays: $ageDays,
            assignedAt: $ticket->assigned_at !== null ? CarbonImmutable::parse($ticket->assigned_at) : null,
            lastTransition: $lastTransition,
            firstResponseAt: $firstResponseAt,
            hasFirstResponse: $firstResponseAt !== null,
            evidenceCount: $evidenceCount,
            hasEvidence: $evidenceCount > 0,
            recommendedAction: $this->recommendedActionFor(
                (string) $ticket->state,
                (string) $ticket->priority,
                $ageDays,
                $firstResponseAt !== null,
                $lastTransition['at'] ?? $createdAt,
                $evidenceCount,
                $duplicate,
                $isRecurrentPair,
            ),
            sortRank: $this->sortRankFor((string) $ticket->priority, (string) $ticket->state, $firstResponseAt !== null),
            hasDuplicateWarning: $duplicate !== null,
            duplicateSimilarity: $duplicate !== null ? $this->floatOrNull($duplicate['similarity']) : null,
            duplicateStrategyScore: $duplicate !== null ? $this->intOrNull($duplicate['strategyScore']) : null,
            duplicateMatchedTicketId: $duplicate !== null ? $this->stringOrNull($duplicate['matchedTicketId']) : null,
            duplicateMatchedTicketTitle: $duplicate !== null ? $this->stringOrNull($duplicate['matchedTicketTitle']) : null,
            duplicateReviewStatus: $duplicate !== null ? $this->stringOrNull($duplicate['reviewStatus']) : null,
            duplicateSuggestsRecurrence: $duplicate !== null && (bool) $duplicate['suggestsRecurrence'],
            duplicateTopReasons: $duplicate !== null ? $this->reasonsList($duplicate) : [],
            duplicateExplanationSummary: $duplicate !== null ? $this->stringOrNull($duplicate['summary']) : null,
        );
    }

    /**
     * Explainable composite rank (lower runs first):
     * priority bucket (critical=1, high=2, medium=3, low=4), then state
     * bucket (open before in_progress), then missing first response first.
     */
    private function sortRankFor(string $priority, string $state, bool $hasFirstResponse): int
    {
        $priorityBucket = match ($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5,
        };

        $stateBucket = $state === Ticket::STATE_OPEN ? 1 : 2;

        return $priorityBucket * 100 + $stateBucket * 10 + ($hasFirstResponse ? 1 : 0);
    }

    /**
     * Deterministic per-ticket recommendation (Fase 13 priority order).
     *
     * @param  array<string, mixed>|null  $duplicate
     */
    private function recommendedActionFor(
        string $state,
        string $priority,
        int $ageDays,
        bool $hasFirstResponse,
        ?CarbonImmutable $lastActivityAt,
        int $evidenceCount,
        ?array $duplicate,
        bool $isRecurrentPair,
    ): string {
        // Ordered rule list (Fase 13): the FIRST matching rule wins.
        return $this->urgentOrDuplicateAction($state, $priority, $duplicate)
            ?? $this->followUpAction($state, $ageDays, $hasFirstResponse, $lastActivityAt, $evidenceCount, $duplicate, $isRecurrentPair);
    }

    /**
     * Highest-priority rules: urgency and AI-duplicate review (Fase 13 order).
     *
     * @param  array<string, mixed>|null  $duplicate
     */
    private function urgentOrDuplicateAction(string $state, string $priority, ?array $duplicate): ?string
    {
        if ($priority === 'critical') {
            $action = 'Atención inmediata';
        } elseif ($priority === 'high' && $state === Ticket::STATE_OPEN) {
            $action = 'Priorizar hoy';
        } elseif ($duplicate !== null && (bool) $duplicate['pendingReview']) {
            $action = 'Revisar posible duplicado antes de continuar';
        } elseif ($duplicate !== null && (bool) $duplicate['suggestsRecurrence']) {
            $action = 'Evaluar recurrencia y revisión preventiva del laboratorio';
        } else {
            $action = null;
        }

        return $action;
    }

    /**
     * Remaining ordered rules when no urgent/duplicate rule matched.
     *
     * @param  array<string, mixed>|null  $duplicate
     */
    private function followUpAction(
        string $state,
        int $ageDays,
        bool $hasFirstResponse,
        ?CarbonImmutable $lastActivityAt,
        int $evidenceCount,
        ?array $duplicate,
        bool $isRecurrentPair,
    ): string {
        if ($state === Ticket::STATE_OPEN && $ageDays > self::STALE_OPEN_DAYS) {
            $action = 'Iniciar atención atrasada';
        } elseif ($state === Ticket::STATE_IN_PROGRESS
            && $lastActivityAt !== null
            && $lastActivityAt->lessThan($this->now->subDays(self::STALE_IN_PROGRESS_DAYS))) {
            $action = 'Retomar y actualizar estado';
        } elseif (! $hasFirstResponse && $state === Ticket::STATE_OPEN) {
            $action = 'Registrar avance inicial';
        } elseif ($evidenceCount === 0) {
            $action = 'Adjuntar evidencia';
        } elseif ($isRecurrentPair) {
            $action = 'Revisión preventiva del laboratorio';
        } elseif ($duplicate !== null && ! (bool) $duplicate['hasStrategyMetadata']) {
            $action = 'Revisar duplicado manualmente (registro antiguo)';
        } else {
            $action = 'En seguimiento';
        }

        return $action;
    }

    private function lastActivityAt(AssignmentRow $row): CarbonImmutable
    {
        if ($row->lastTransition !== null) {
            return CarbonImmutable::parse($row->lastTransition['at']);
        }

        return $row->createdAt !== null ? CarbonImmutable::parse($row->createdAt) : $this->now;
    }

    // ── AI duplicates (Universe I, snapshot, operational signal only) ────────

    /**
     * Persisted AI-duplicate signal for one active ticket, or null when the
     * ticket has no effective duplicate (includes human-dismissed ones).
     * Reads strategy_results/strategy_metadata via the presenter; never
     * recalculates the Strategy engine.
     *
     * @return array<string, mixed>|null
     */
    private function duplicateInfoFor(Ticket $ticket): ?array
    {
        $embedding = $ticket->embedding;

        if ($embedding === null
            || $embedding->matched_ticket_id === null
            || ! $embedding->effective_duplicate) {
            return null;
        }

        $matched = $embedding->matchedTicket;
        $hasStrategyMetadata = $this->strategyResultsUsable($embedding->strategy_results);

        if ($matched !== null) {
            $explanation = DuplicateExplanationPresenter::present($ticket);
            $topReasons = array_map(
                static fn (array $reason): array => [
                    'label' => $reason['label'],
                    'detail' => $reason['detail'],
                ],
                $explanation['topReasons'],
            );
            $summary = $explanation['summary'];
            $isFallback = $explanation['isFallback'];
            $matchedTitle = (string) ($matched->title ?? 'Ticket relacionado');
        } else {
            // matched_ticket_id points to a missing ticket: don't break the
            // report; surface a manual-review fallback (also a data-quality flag).
            $topReasons = [];
            $summary = 'La IA detectó similitud con un ticket que ya no está disponible; se requiere revisión manual.';
            $isFallback = true;
            $matchedTitle = null;
        }

        return [
            'similarity' => $embedding->similarity_score,
            'strategyScore' => $embedding->strategy_score,
            'matchedTicketId' => $embedding->matched_ticket_id !== null ? (string) $embedding->matched_ticket_id : null,
            'matchedTicketTitle' => $matchedTitle,
            'matchedExists' => $matched !== null,
            'reviewStatus' => $embedding->review_status,
            'pendingReview' => $embedding->isPendingReview(),
            'suggestsRecurrence' => (bool) $embedding->strategy_suggests_recurrence,
            'hasStrategyMetadata' => $hasStrategyMetadata,
            'isFallback' => $isFallback,
            'topReasons' => $topReasons,
            'summary' => $summary,
            'flaggedAt' => $embedding->created_at !== null ? CarbonImmutable::parse($embedding->created_at) : null,
        ];
    }

    /**
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<DuplicateAlertRow>
     */
    private function duplicateAlerts(EloquentCollection $activeTickets, array $duplicateInfoByTicket): array
    {
        $rows = [];

        foreach ($activeTickets as $ticket) {
            $ticketId = (string) $ticket->id;
            $info = $duplicateInfoByTicket[$ticketId] ?? null;

            if ($info === null) {
                continue;
            }

            $matchedId = $this->stringOrNull($info['matchedTicketId']);

            $rows[] = new DuplicateAlertRow(
                ticketId: $ticketId,
                ticketIdShort: $this->idShort($ticketId),
                title: (string) $ticket->title,
                matchedTicketId: $matchedId,
                matchedTicketIdShort: $matchedId !== null ? $this->idShort($matchedId) : null,
                matchedTicketTitle: $this->stringOrNull($info['matchedTicketTitle']),
                similarity: $this->floatOrNull($info['similarity']),
                strategyScore: $this->intOrNull($info['strategyScore']),
                reviewStatus: $this->stringOrNull($info['reviewStatus']),
                reviewStatusLabel: $this->reviewStatusLabel($this->stringOrNull($info['reviewStatus'])),
                suggestsRecurrence: (bool) $info['suggestsRecurrence'],
                topReasons: $this->reasonsList($info),
                summary: (string) $info['summary'],
                recommendedAction: $this->duplicateRecommendedAction($info),
                isFallback: (bool) $info['isFallback'],
            );
        }

        // Pending reviews first, then highest similarity: the operational
        // reading order for a supervisor.
        usort($rows, static function (DuplicateAlertRow $a, DuplicateAlertRow $b): int {
            $aPending = $a->reviewStatus === null ? 0 : 1;
            $bPending = $b->reviewStatus === null ? 0 : 1;

            if ($aPending !== $bPending) {
                return $aPending <=> $bPending;
            }

            return ($b->similarity ?? 0.0) <=> ($a->similarity ?? 0.0);
        });

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function duplicateRecommendedAction(array $info): string
    {
        if ((bool) $info['pendingReview']) {
            $action = 'Revisar duplicidad antes de iniciar trabajo duplicado';
        } elseif ((bool) $info['suggestsRecurrence']) {
            $action = 'Evaluar recurrencia y revisión preventiva del laboratorio';
        } elseif (! (bool) $info['hasStrategyMetadata']) {
            $action = 'Revisión manual: registro antiguo sin desglose Strategy';
        } else {
            $action = 'Duplicado confirmado: coordinar consolidación con el ticket similar';
        }

        return $action;
    }

    private function reviewStatusLabel(?string $status): string
    {
        return match ($status) {
            null => 'Pendiente de revisión',
            'confirmed' => 'Confirmado por revisión humana',
            'dismissed' => 'Descartado por revisión humana',
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<int, array{label: string, detail: string}>
     */
    private function reasonsList(array $info): array
    {
        $reasons = $info['topReasons'];

        return is_array($reasons) ? array_values($reasons) : [];
    }

    /** True when persisted strategy_results contains at least one readable entry. */
    private function strategyResultsUsable(mixed $results): bool
    {
        if (! is_array($results) || $results === []) {
            return false;
        }

        foreach ($results as $result) {
            if (is_array($result) && isset($result['strategy']) && is_string($result['strategy'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     */
    private function countDuplicates(array $duplicateInfoByTicket, string $flag): int
    {
        return count(array_filter(
            $duplicateInfoByTicket,
            static fn (array $info): bool => (bool) $info[$flag],
        ));
    }

    // ── Resolved / administrative closures (Universes D, E, F) ───────────────

    /**
     * @param  EloquentCollection<int, Ticket>  $resolvedModels
     * @return list<ResolvedTicketRow>
     */
    private function resolvedRows(EloquentCollection $resolvedModels): array
    {
        $rows = [];

        foreach ($resolvedModels as $ticket) {
            $createdAt = $ticket->created_at !== null ? CarbonImmutable::parse($ticket->created_at) : null;
            $resolvedAt = $ticket->resolved_at !== null ? CarbonImmutable::parse($ticket->resolved_at) : null;

            $rows[] = new ResolvedTicketRow(
                id: (string) $ticket->id,
                idShort: $this->idShort((string) $ticket->id),
                title: (string) $ticket->title,
                locationName: (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
                categoryName: (string) ($ticket->category->name ?? self::LABEL_UNCATEGORIZED),
                priority: (string) $ticket->priority,
                createdAt: $createdAt,
                resolvedAt: $resolvedAt,
                durationHours: $createdAt !== null && $resolvedAt !== null
                    ? round(((float) $createdAt->diffInMinutes($resolvedAt)) / 60, 1)
                    : null,
                evidenceCount: (int) $ticket->getAttribute('media_count'),
            );
        }

        return $rows;
    }

    /**
     * Closures sourced from real state_history transitions within the period.
     * One row per ticket (first matching transition in range). Models are
     * returned alongside the rows so breakdowns and recurrences can reuse
     * them without re-querying.
     *
     * @return array{rows: list<ClosedTicketRow>, models: EloquentCollection<int, Ticket>}
     */
    private function administrativeClosures(string $toState): array
    {
        $transitions = DB::table('state_history')
            ->join('tickets', 'tickets.id', '=', 'state_history.ticket_id')
            ->where('tickets.assigned_to', $this->userId())
            ->where('state_history.to_state', $toState)
            ->whereBetween('state_history.created_at', [$this->range->from, $this->range->to])
            ->orderBy('state_history.created_at')
            ->get(['state_history.ticket_id as ticket_id', 'state_history.created_at as closed_at']);

        $closedAtByTicket = [];
        foreach ($transitions as $transition) {
            $ticketId = (string) $transition->ticket_id;
            if (! array_key_exists($ticketId, $closedAtByTicket)) {
                $closedAtByTicket[$ticketId] = CarbonImmutable::parse((string) $transition->closed_at);
            }
        }

        if ($closedAtByTicket === []) {
            return ['rows' => [], 'models' => new EloquentCollection];
        }

        $tickets = Ticket::query()
            ->whereIn('id', array_keys($closedAtByTicket))
            ->with(['location', 'category'])
            ->get()
            ->keyBy('id');

        $note = $toState === Ticket::STATE_REJECTED
            ? 'Cierre administrativo'
            : 'Cancelado por el reporter; no penaliza al técnico';

        $rows = [];
        foreach ($closedAtByTicket as $ticketId => $closedAt) {
            $ticket = $tickets->get($ticketId);
            if ($ticket === null) {
                continue;
            }

            $rows[] = new ClosedTicketRow(
                id: $ticketId,
                idShort: $this->idShort($ticketId),
                title: (string) $ticket->title,
                locationName: (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
                categoryName: (string) ($ticket->category->name ?? self::LABEL_UNCATEGORIZED),
                priority: (string) $ticket->priority,
                kind: $toState === Ticket::STATE_REJECTED ? ClosedTicketRow::KIND_REJECTED : ClosedTicketRow::KIND_CANCELLED,
                createdAt: $ticket->created_at !== null ? CarbonImmutable::parse($ticket->created_at) : null,
                closedAt: $closedAt,
                note: $note,
            );
        }

        return ['rows' => $rows, 'models' => $tickets->values()];
    }

    // ── Time metrics (clock: periodo) ─────────────────────────────────────────

    /**
     * @param  list<ResolvedTicketRow>  $resolvedRows
     * @return array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}
     */
    private function timeMetrics(array $resolvedRows): array
    {
        $durations = [];
        foreach ($resolvedRows as $row) {
            if ($row->durationHours !== null) {
                $durations[] = $row->durationHours;
            }
        }

        $avg = $durations === [] ? null : round(array_sum($durations) / count($durations), 1);
        $median = $this->median($durations);

        [$firstResponseAvg, $firstResponseSample] = $this->firstResponseAverage();

        return [
            'avgResolutionHours' => $avg,
            'medianResolutionHours' => $median,
            'resolutionSample' => count($durations),
            'resolutionLowSample' => count($durations) > 0 && count($durations) < self::LOW_SAMPLE_THRESHOLD,
            'avgFirstResponseHours' => $firstResponseAvg,
            'firstResponseSample' => $firstResponseSample,
            'firstResponseLowSample' => $firstResponseSample > 0 && $firstResponseSample < self::LOW_SAMPLE_THRESHOLD,
        ];
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        $median = $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];

        return round($median, 1);
    }

    /**
     * Average hours from ticket creation to its first in_progress transition,
     * over transitions registered within the period (first per ticket).
     *
     * @return array{0: float|null, 1: int}
     */
    private function firstResponseAverage(): array
    {
        $rows = DB::table('state_history')
            ->join('tickets', 'tickets.id', '=', 'state_history.ticket_id')
            ->where('tickets.assigned_to', $this->userId())
            ->where('state_history.to_state', Ticket::STATE_IN_PROGRESS)
            ->whereBetween('state_history.created_at', [$this->range->from, $this->range->to])
            ->orderBy('state_history.created_at')
            ->get([
                'state_history.ticket_id as ticket_id',
                'tickets.created_at as ticket_created_at',
                'state_history.created_at as responded_at',
            ]);

        $minutesByTicket = [];
        foreach ($rows as $row) {
            $ticketId = (string) $row->ticket_id;
            if (array_key_exists($ticketId, $minutesByTicket)) {
                continue;
            }

            $created = CarbonImmutable::parse((string) $row->ticket_created_at);
            $responded = CarbonImmutable::parse((string) $row->responded_at);
            $minutesByTicket[$ticketId] = (float) $created->diffInMinutes($responded);
        }

        if ($minutesByTicket === []) {
            return [null, 0];
        }

        return [
            round(array_sum($minutesByTicket) / count($minutesByTicket) / 60, 1),
            count($minutesByTicket),
        ];
    }

    // ── Evidence (ticket_media over active assignments) ──────────────────────

    /**
     * @param  list<AssignmentRow>  $assignments
     * @return array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}
     */
    private function evidenceSummary(array $assignments): array
    {
        $with = 0;
        $missing = [];

        foreach ($assignments as $row) {
            if ($row->hasEvidence) {
                $with++;
            } else {
                $missing[] = ['idShort' => $row->idShort, 'title' => $row->title];
            }
        }

        $total = count($assignments);

        return [
            'withEvidence' => $with,
            'withoutEvidence' => count($missing),
            'coveragePct' => $total > 0 ? round($with / $total * 100, 1) : null,
            'missing' => $missing,
        ];
    }

    // ── Breakdowns (snapshot + periodo, labelled apart) ──────────────────────

    /**
     * Per-location workload. Snapshot columns (active/open/in progress/
     * critical/duplicates) plus period columns (created/resolved/rejected/
     * cancelled). `totalRelevant` counts distinct tickets in the bucket, so a
     * ticket created in the period that is still active counts once.
     *
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  EloquentCollection<int, Ticket>  $createdModels
     * @param  EloquentCollection<int, Ticket>  $resolvedModels
     * @param  EloquentCollection<int, Ticket>  $rejectedModels
     * @param  EloquentCollection<int, Ticket>  $cancelledModels
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<LocationBreakdownRow>
     */
    private function locationBreakdown(
        EloquentCollection $activeTickets,
        EloquentCollection $createdModels,
        EloquentCollection $resolvedModels,
        EloquentCollection $rejectedModels,
        EloquentCollection $cancelledModels,
        array $duplicateInfoByTicket,
    ): array {
        $buckets = [];

        foreach ($activeTickets as $ticket) {
            $key = (string) ($ticket->location_id ?? 'none');
            $bucket = $buckets[$key] ?? $this->emptyLocationBucket($ticket);

            $info = $duplicateInfoByTicket[(string) $ticket->id] ?? null;
            $buckets[$key] = $this->accumulateActiveLocationBucket($bucket, $ticket, $info);
        }

        foreach (['created' => $createdModels, 'resolved' => $resolvedModels, 'rejected' => $rejectedModels, 'cancelled' => $cancelledModels] as $counter => $models) {
            foreach ($models as $ticket) {
                $key = (string) ($ticket->location_id ?? 'none');
                $bucket = $buckets[$key] ?? $this->emptyLocationBucket($ticket);
                $bucket[$counter]++;
                $bucket['ids'][(string) $ticket->id] = true;
                $buckets[$key] = $bucket;
            }
        }

        $rows = array_map(
            static fn (array $bucket): LocationBreakdownRow => new LocationBreakdownRow(
                locationName: $bucket['name'],
                building: $bucket['building'],
                floor: $bucket['floor'],
                roomCode: $bucket['roomCode'],
                activeCount: $bucket['active'],
                openCount: $bucket['open'],
                inProgressCount: $bucket['inProgress'],
                createdInPeriod: $bucket['created'],
                resolvedInPeriod: $bucket['resolved'],
                rejectedInPeriod: $bucket['rejected'],
                cancelledInPeriod: $bucket['cancelled'],
                totalRelevant: count($bucket['ids']),
                highCriticalActive: $bucket['highCritical'],
                possibleDuplicateActive: $bucket['duplicates'],
                possibleRecurrenceActive: $bucket['recurrences'],
            ),
            array_values($buckets),
        );

        usort($rows, static function (LocationBreakdownRow $a, LocationBreakdownRow $b): int {
            if ($a->totalRelevant !== $b->totalRelevant) {
                return $b->totalRelevant <=> $a->totalRelevant;
            }

            if ($a->activeCount !== $b->activeCount) {
                return $b->activeCount <=> $a->activeCount;
            }

            return strcmp($a->locationName, $b->locationName);
        });

        return array_values($rows);
    }

    /**
     * Accumulate ONE active ticket into its location bucket (counts by state,
     * urgency and persisted AI-duplicate signal).
     *
     * @param  array<string, mixed>  $bucket
     * @param  array<string, mixed>|null  $info
     * @return array<string, mixed>
     */
    private function accumulateActiveLocationBucket(array $bucket, Ticket $ticket, ?array $info): array
    {
        $bucket['active']++;
        $bucket['ids'][(string) $ticket->id] = true;
        if ($ticket->state === Ticket::STATE_OPEN) {
            $bucket['open']++;
        } else {
            $bucket['inProgress']++;
        }
        if (in_array($ticket->priority, self::URGENT_PRIORITIES, true)) {
            $bucket['highCritical']++;
        }

        if ($info !== null) {
            $bucket['duplicates']++;
            if ((bool) $info['suggestsRecurrence']) {
                $bucket['recurrences']++;
            }
        }

        return $bucket;
    }

    /**
     * @return array{name: string, building: string, floor: string, roomCode: string, active: int, open: int, inProgress: int, created: int, resolved: int, rejected: int, cancelled: int, highCritical: int, duplicates: int, recurrences: int, ids: array<string, true>}
     */
    private function emptyLocationBucket(Ticket $ticket): array
    {
        return [
            'name' => (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
            'building' => (string) ($ticket->location->building ?? ''),
            'floor' => (string) ($ticket->location->floor ?? ''),
            'roomCode' => (string) ($ticket->location->room_code ?? ''),
            'active' => 0,
            'open' => 0,
            'inProgress' => 0,
            'created' => 0,
            'resolved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'highCritical' => 0,
            'duplicates' => 0,
            'recurrences' => 0,
            'ids' => [],
        ];
    }

    /**
     * Per-category frequency over every relevant universe of the report.
     * `totalRelevant` and `percentage` count distinct tickets per bucket.
     *
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  EloquentCollection<int, Ticket>  $createdModels
     * @param  EloquentCollection<int, Ticket>  $resolvedModels
     * @param  EloquentCollection<int, Ticket>  $rejectedModels
     * @param  EloquentCollection<int, Ticket>  $cancelledModels
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<CategoryBreakdownRow>
     */
    private function categoryBreakdown(
        EloquentCollection $activeTickets,
        EloquentCollection $createdModels,
        EloquentCollection $resolvedModels,
        EloquentCollection $rejectedModels,
        EloquentCollection $cancelledModels,
        array $duplicateInfoByTicket,
    ): array {
        $buckets = [];

        foreach ($activeTickets as $ticket) {
            $key = (string) ($ticket->category_id ?? 'none');
            $bucket = $buckets[$key] ?? $this->emptyCategoryBucket($ticket);

            $bucket['active']++;
            $bucket['ids'][(string) $ticket->id] = true;

            $info = $duplicateInfoByTicket[(string) $ticket->id] ?? null;
            if ($info !== null) {
                $bucket['duplicates']++;
                if ((bool) $info['suggestsRecurrence']) {
                    $bucket['recurrences']++;
                }
            }

            $buckets[$key] = $bucket;
        }

        foreach (['created' => $createdModels, 'resolved' => $resolvedModels, 'rejected' => $rejectedModels, 'cancelled' => $cancelledModels] as $counter => $models) {
            foreach ($models as $ticket) {
                $key = (string) ($ticket->category_id ?? 'none');
                $bucket = $buckets[$key] ?? $this->emptyCategoryBucket($ticket);
                $bucket[$counter]++;
                $bucket['ids'][(string) $ticket->id] = true;
                $buckets[$key] = $bucket;
            }
        }

        $grandTotal = 0;
        foreach ($buckets as $bucket) {
            $grandTotal += count($bucket['ids']);
        }

        $rows = array_map(
            static fn (array $bucket): CategoryBreakdownRow => new CategoryBreakdownRow(
                categoryName: $bucket['name'],
                activeCount: $bucket['active'],
                createdInPeriod: $bucket['created'],
                resolvedInPeriod: $bucket['resolved'],
                rejectedInPeriod: $bucket['rejected'],
                cancelledInPeriod: $bucket['cancelled'],
                totalRelevant: count($bucket['ids']),
                percentage: $grandTotal > 0
                    ? round(count($bucket['ids']) / $grandTotal * 100, 1)
                    : 0.0,
                possibleDuplicateActive: $bucket['duplicates'],
                possibleRecurrenceActive: $bucket['recurrences'],
            ),
            array_values($buckets),
        );

        usort($rows, static function (CategoryBreakdownRow $a, CategoryBreakdownRow $b): int {
            if ($a->totalRelevant !== $b->totalRelevant) {
                return $b->totalRelevant <=> $a->totalRelevant;
            }

            return strcmp($a->categoryName, $b->categoryName);
        });

        return array_values($rows);
    }

    /**
     * @return array{name: string, active: int, created: int, resolved: int, rejected: int, cancelled: int, duplicates: int, recurrences: int, ids: array<string, true>}
     */
    private function emptyCategoryBucket(Ticket $ticket): array
    {
        return [
            'name' => (string) ($ticket->category->name ?? self::LABEL_UNCATEGORIZED),
            'active' => 0,
            'created' => 0,
            'resolved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'duplicates' => 0,
            'recurrences' => 0,
            'ids' => [],
        ];
    }

    // ── Historical recurrences (location_incident_history, no AI) ────────────

    /**
     * Incident-history rows matching exact (location, category) pairs of any
     * ticket relevant to the report (active, created in period, resolved,
     * rejected or cancelled), not only active assignments.
     *
     * @param  SupportCollection<int, Ticket>  $relevantTickets
     * @return array<string, LocationIncidentHistory> keyed "locationId|categoryId"
     */
    private function recurrenceHistories(SupportCollection $relevantTickets): array
    {
        if ($relevantTickets->isEmpty()) {
            return [];
        }

        $locationIds = $relevantTickets->pluck('location_id')->filter()->unique()->values()->all();
        $categoryIds = $relevantTickets->pluck('category_id')->filter()->unique()->values()->all();

        if ($locationIds === [] || $categoryIds === []) {
            return [];
        }

        $pairs = [];
        foreach ($relevantTickets as $ticket) {
            $pairs[$ticket->location_id.'|'.$ticket->category_id] = true;
        }

        $map = [];

        LocationIncidentHistory::query()
            ->whereIn('location_id', $locationIds)
            ->whereIn('category_id', $categoryIds)
            ->with(['location', 'category'])
            ->get()
            ->each(function (LocationIncidentHistory $history) use (&$map, $pairs): void {
                $key = $history->location_id.'|'.$history->category_id;
                // whereIn x whereIn is a cross product: keep exact pairs only.
                if (isset($pairs[$key]) && (int) $history->recurrence_count >= self::RELEVANT_RECURRENCE_COUNT) {
                    $map[$key] = $history;
                }
            });

        return $map;
    }

    /**
     * @param  array<string, LocationIncidentHistory>  $recurrenceHistories
     * @return list<RecurrenceInsightRow>
     */
    private function recurrenceInsights(array $recurrenceHistories): array
    {
        $rows = [];

        foreach ($recurrenceHistories as $history) {
            $rows[] = new RecurrenceInsightRow(
                locationName: (string) ($history->location->name ?? self::LABEL_NO_LOCATION),
                categoryName: (string) ($history->category->name ?? self::LABEL_UNCATEGORIZED),
                recurrenceCount: (int) $history->recurrence_count,
                lastResolvedAt: $history->last_resolved_at !== null
                    ? CarbonImmutable::parse($history->last_resolved_at)
                    : null,
                // Free-form string in the schema: reference text, no arithmetic.
                avgResolutionLabel: trim((string) ($history->avg_resolution_time ?? '')) !== ''
                    ? (string) $history->avg_resolution_time
                    : 'Sin dato histórico',
                recommendation: 'Evaluar revisión preventiva del laboratorio para esta categoría.',
            );
        }

        usort($rows, static function (RecurrenceInsightRow $a, RecurrenceInsightRow $b): int {
            if ($a->recurrenceCount !== $b->recurrenceCount) {
                return $b->recurrenceCount <=> $a->recurrenceCount;
            }

            return strcmp($a->locationName.$a->categoryName, $b->locationName.$b->categoryName);
        });

        return array_values($rows);
    }

    // ── Global queue (Universe C: context only) ──────────────────────────────

    /**
     * @return array{count: int, preview: list<array{idShort: string, title: string, locationName: string, priority: string, ageDays: int}>}
     */
    private function globalQueueContext(): array
    {
        $count = Ticket::availableForClaim()->count();

        $preview = Ticket::availableForClaim()
            ->with('location')
            ->oldest('created_at')
            ->limit(self::GLOBAL_QUEUE_PREVIEW_LIMIT)
            ->get()
            ->map(fn (Ticket $ticket): array => [
                'idShort' => $this->idShort((string) $ticket->id),
                'title' => (string) $ticket->title,
                'locationName' => (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
                'priority' => (string) $ticket->priority,
                'ageDays' => $ticket->created_at !== null
                    ? (int) floor(CarbonImmutable::parse($ticket->created_at)->diffInDays($this->now))
                    : 0,
            ])
            ->values()
            ->all();

        return ['count' => $count, 'preview' => $preview];
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    /**
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $time
     * @return list<KpiRow>
     */
    private function kpis(
        int $active,
        int $open,
        int $inProgress,
        int $criticalHigh,
        int $staleOpen,
        array $evidence,
        int $globalQueue,
        int $duplicatesActive,
        int $resolved,
        int $rejected,
        int $cancelled,
        int $created,
        int $closed,
        array $time,
        ?float $closeRate,
    ): array {
        $snapshot = KpiRow::CLOCK_SNAPSHOT;
        $period = KpiRow::CLOCK_PERIOD;

        return [
            new KpiRow('assigned_active', self::LABEL_ACTIVE_ASSIGNED, (string) $active, $snapshot,
                'Abiertos y en progreso bajo responsabilidad del técnico.', 'neutral'),
            new KpiRow('open_active', 'Abiertos', (string) $open, $snapshot,
                'Asignados aún sin iniciar.', 'info'),
            new KpiRow('in_progress', self::LABEL_IN_PROGRESS, (string) $inProgress, $snapshot,
                'Trabajo técnico ya iniciado.', 'info'),
            new KpiRow('critical_high_active', 'Críticos / alta activos', (string) $criticalHigh, $snapshot,
                'Prioridad crítica o alta todavía sin cerrar.', $criticalHigh > 0 ? 'critical' : 'neutral'),
            new KpiRow('stale_open', 'Sin iniciar +'.self::STALE_OPEN_DAYS.self::SUFFIX_DAYS, (string) $staleOpen, $snapshot,
                'Abiertos hace más de '.self::STALE_OPEN_DAYS.' días sin primera respuesta.', $staleOpen > 0 ? 'warning' : 'neutral'),
            new KpiRow('evidence_with', 'Activos con evidencia', (string) $evidence['withEvidence'], $snapshot,
                'Asignaciones activas con al menos un adjunto.', 'neutral'),
            new KpiRow('evidence_without', 'Activos sin evidencia', (string) $evidence['withoutEvidence'], $snapshot,
                'Asignaciones activas sin respaldo documental.', $evidence['withoutEvidence'] > 0 ? 'warning' : 'neutral'),
            new KpiRow('evidence_coverage', 'Cobertura de evidencia', $this->formatPercentOrNa($evidence['coveragePct']), $snapshot,
                'Porcentaje de asignaciones activas con evidencia.', 'neutral'),
            new KpiRow('global_queue', self::LABEL_GLOBAL_QUEUE_AVAILABLE, (string) $globalQueue, $snapshot,
                'Contexto del área: abiertos sin asignar. No es responsabilidad personal.', 'neutral'),
            // Single AI row in the KPI table: the detailed AI counters live in
            // the "Alertas IA y posibles duplicados" section (aiSummary).
            new KpiRow('ai_duplicates_active', 'Posibles duplicados IA activos', (string) $duplicatesActive, $snapshot,
                'Señal operativa de calidad de atención. No mide productividad. Detalle en la sección de alertas IA.', $duplicatesActive > 0 ? 'warning' : 'neutral'),
            new KpiRow('resolved_period', self::LABEL_RESOLVED_IN_PERIOD, (string) $resolved, $period,
                'Cierres por resolved_at dentro del rango.', 'positive'),
            new KpiRow('rejected_period', 'Rechazados en periodo', (string) $rejected, $period,
                'Cierre administrativo (transición real a rejected).', 'neutral'),
            new KpiRow('cancelled_period', 'Cancelados en periodo', (string) $cancelled, $period,
                'Retiro del reporter. No penaliza al técnico ni entra en la tasa de cierre.', 'neutral'),
            new KpiRow('created_period', 'Creados en periodo', (string) $created, $period,
                'Tickets de mi carga creados dentro del rango.', 'neutral'),
            new KpiRow('closed_period', 'Cerrados en periodo', (string) $closed, $period,
                'Resueltos + rechazados. Excluye cancelados.', 'neutral'),
            new KpiRow('avg_resolution', 'Promedio resolución', $this->formatHours($time['avgResolutionHours']), $period,
                'Tiempo medio desde creación hasta cierre.', 'neutral', $time['resolutionLowSample']),
            new KpiRow('median_resolution', 'Mediana resolución', $this->formatHours($time['medianResolutionHours']), $period,
                'Valor central, robusto ante casos extremos.', 'neutral', $time['resolutionLowSample']),
            new KpiRow('first_response', 'Primera respuesta promedio', $this->formatHours($time['avgFirstResponseHours']), $period,
                'Promedio hasta la primera transición a en progreso.', 'neutral', $time['firstResponseLowSample']),
            new KpiRow('close_rate', 'Tasa de cierre operativa', $this->formatPercentOrNa($closeRate), $period,
                $closeRate !== null
                    ? 'Resueltos / (resueltos + activos). Excluye cancelados y señales IA.'
                    : 'No aplica: sin resueltos en el periodo ni carga activa, la tasa no tiene denominador.', 'neutral'),
        ];
    }

    // ── Scope (Universes A–I) ────────────────────────────────────────────────

    /**
     * @return list<ScopeUniverseRow>
     */
    private function scopeRows(
        int $assignedAllTime,
        int $active,
        int $globalQueue,
        int $resolved,
        int $rejected,
        int $cancelled,
        int $created,
        int $closed,
        int $duplicates,
    ): array {
        return [
            new ScopeUniverseRow('A', 'Asignados al técnico', 'histórico', $assignedAllTime,
                'Todos los tickets con assigned_to = técnico, sin filtro de fecha.'),
            new ScopeUniverseRow('B', 'Activos del técnico', 'snapshot', $active,
                'Asignados en estado abierto o en progreso al momento de generar.'),
            new ScopeUniverseRow('C', self::LABEL_GLOBAL_QUEUE_AVAILABLE, 'snapshot', $globalQueue,
                'Abiertos sin asignar y sin bloqueo. Contexto del área, no responsabilidad personal.'),
            new ScopeUniverseRow('D', 'Resueltos por el técnico', 'periodo', $resolved,
                'Asignados con resolved_at dentro del rango.'),
            new ScopeUniverseRow('E', 'Rechazados', 'periodo', $rejected,
                'Transición real a rejected dentro del rango. Cierre administrativo.'),
            new ScopeUniverseRow('F', 'Cancelados', 'periodo', $cancelled,
                'Transición real a cancelled dentro del rango. Retiro del reporter; no penaliza.'),
            new ScopeUniverseRow('G', 'Creados en periodo (mi carga)', 'periodo', $created,
                'Asignados al técnico con created_at dentro del rango.'),
            new ScopeUniverseRow('H', 'Cerrados en periodo', 'periodo', $closed,
                'Resueltos + rechazados. Excluye cancelados.'),
            new ScopeUniverseRow('I', 'Posibles duplicados IA activos', 'snapshot', $duplicates,
                'Activos marcados por IA como posible duplicado. Señal operativa, no productividad.'),
        ];
    }

    // ── Risks (deterministic) ────────────────────────────────────────────────

    /**
     * @param  list<AssignmentRow>  $assignments
     * @param  list<AssignmentRow>  $staleOpen
     * @param  list<AssignmentRow>  $staleInProgress
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $time
     * @param  list<RecurrenceInsightRow>  $recurrenceInsights
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @param  list<array{check: string, detail: string, count: int}>  $dataQuality
     * @return list<RiskRow>
     */
    private function risks(
        array $assignments,
        array $staleOpen,
        array $staleInProgress,
        array $evidence,
        ?float $closeRate,
        int $active,
        array $time,
        array $recurrenceInsights,
        int $globalQueue,
        array $duplicateInfoByTicket,
        array $dataQuality,
    ): array {
        $risks = [
            ...$this->assignmentRisks($assignments, $staleOpen, $staleInProgress),
            ...$this->duplicateWarningRisks($duplicateInfoByTicket),
            ...$this->operationalRisks($evidence, $closeRate, $active, $dataQuality),
            ...$this->informativeRisks($time, $duplicateInfoByTicket, $recurrenceInsights, $globalQueue),
        ];

        // Stable order: critical, then warning, then info (rule order preserved).
        usort($risks, static function (RiskRow $a, RiskRow $b): int {
            $order = [RiskRow::SEVERITY_CRITICAL => 0, RiskRow::SEVERITY_WARNING => 1, RiskRow::SEVERITY_INFO => 2];

            return ($order[$a->severity] ?? 3) <=> ($order[$b->severity] ?? 3);
        });

        return array_values($risks);
    }

    /**
     * Priority and staleness risks over the active assignments.
     *
     * @param  list<AssignmentRow>  $assignments
     * @param  list<AssignmentRow>  $staleOpen
     * @param  list<AssignmentRow>  $staleInProgress
     * @return list<RiskRow>
     */
    private function assignmentRisks(array $assignments, array $staleOpen, array $staleInProgress): array
    {
        $risks = [];

        $criticalActive = $this->filterByPriority($assignments, 'critical');
        if ($criticalActive !== []) {
            $risks[] = new RiskRow('critical_active', RiskRow::SEVERITY_CRITICAL, count($criticalActive),
                'Tickets de prioridad crítica sin cerrar. Requieren atención inmediata.',
                $this->displayIds($criticalActive));
        }

        $highActive = $this->filterByPriority($assignments, 'high');
        if ($highActive !== []) {
            $risks[] = new RiskRow('high_active', RiskRow::SEVERITY_WARNING, count($highActive),
                'Tickets de prioridad alta sin cerrar.',
                $this->displayIds($highActive));
        }

        if ($staleOpen !== []) {
            $risks[] = new RiskRow('stale_open', RiskRow::SEVERITY_WARNING, count($staleOpen),
                'Tickets abiertos con más de '.self::STALE_OPEN_DAYS.' días sin iniciar atención.',
                $this->displayIds($staleOpen));
        }

        if ($staleInProgress !== []) {
            $risks[] = new RiskRow('stale_in_progress', RiskRow::SEVERITY_WARNING, count($staleInProgress),
                'Tickets en progreso sin transiciones recientes (más de '.self::STALE_IN_PROGRESS_DAYS.' días).',
                $this->displayIds($staleInProgress));
        }

        return $risks;
    }

    /**
     * Warning-level risks derived from the AI-duplicate snapshot.
     *
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<RiskRow>
     */
    private function duplicateWarningRisks(array $duplicateInfoByTicket): array
    {
        $risks = [];

        $pendingDuplicates = $this->duplicateDisplayIds($duplicateInfoByTicket, 'pendingReview');
        if ($pendingDuplicates !== []) {
            $risks[] = new RiskRow('possible_duplicate_pending_review', RiskRow::SEVERITY_WARNING, count($pendingDuplicates),
                'Posibles duplicados IA activos sin revisión humana. Revisar antes de duplicar trabajo.',
                $pendingDuplicates);
        }

        $highSimilarity = [];
        foreach ($duplicateInfoByTicket as $ticketId => $info) {
            $similarity = $this->floatOrNull($info['similarity']);
            if ($similarity !== null && $similarity >= self::HIGH_SIMILARITY_THRESHOLD) {
                $highSimilarity[] = self::TICKET_REFERENCE_PREFIX.$this->idShort($ticketId);
            }
        }
        if ($highSimilarity !== []) {
            $risks[] = new RiskRow('duplicate_with_high_similarity', RiskRow::SEVERITY_WARNING, count($highSimilarity),
                'Duplicados con similitud muy alta (≥ '.number_format(self::HIGH_SIMILARITY_THRESHOLD, 2).').',
                $highSimilarity);
        }

        $overdue = [];
        foreach ($duplicateInfoByTicket as $ticketId => $info) {
            $flaggedAt = $info['flaggedAt'];
            if ((bool) $info['pendingReview']
                && $flaggedAt instanceof CarbonImmutable
                && $flaggedAt->lessThan($this->now->subDays(self::DUPLICATE_REVIEW_OVERDUE_DAYS))) {
                $overdue[] = self::TICKET_REFERENCE_PREFIX.$this->idShort($ticketId);
            }
        }
        if ($overdue !== []) {
            $risks[] = new RiskRow('duplicate_review_overdue', RiskRow::SEVERITY_WARNING, count($overdue),
                'Posibles duplicados marcados hace más de '.self::DUPLICATE_REVIEW_OVERDUE_DAYS.' días sin revisión.',
                $overdue);
        }

        return $risks;
    }

    /**
     * Evidence, closure-rate and data-quality risks.
     *
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @param  list<array{check: string, detail: string, count: int}>  $dataQuality
     * @return list<RiskRow>
     */
    private function operationalRisks(array $evidence, ?float $closeRate, int $active, array $dataQuality): array
    {
        $risks = [];

        if ($evidence['withoutEvidence'] > 0) {
            $risks[] = new RiskRow('missing_evidence', RiskRow::SEVERITY_WARNING, $evidence['withoutEvidence'],
                'Asignaciones activas sin evidencia adjunta.',
                array_map(static fn (array $row): string => self::TICKET_REFERENCE_PREFIX.$row['idShort'], $evidence['missing']));
        }

        if ($active > 0 && $closeRate !== null && $closeRate < self::LOW_CLOSURE_RATE_PCT) {
            $risks[] = new RiskRow('low_closure', RiskRow::SEVERITY_WARNING, $active,
                'Tasa de cierre operativa baja ('.$this->formatPercent($closeRate).') frente a la carga activa.');
        }

        if ($dataQuality !== []) {
            $total = 0;
            foreach ($dataQuality as $issue) {
                $total += $issue['count'];
            }
            $risks[] = new RiskRow('data_inconsistency', RiskRow::SEVERITY_WARNING, $total,
                'Inconsistencias de datos detectadas; ver sección de calidad de datos.');
        }

        return $risks;
    }

    /**
     * Info-level risks: context signals that do not require immediate action.
     *
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $time
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @param  list<RecurrenceInsightRow>  $recurrenceInsights
     * @return list<RiskRow>
     */
    private function informativeRisks(
        array $time,
        array $duplicateInfoByTicket,
        array $recurrenceInsights,
        int $globalQueue,
    ): array {
        $risks = [];

        if ($time['avgFirstResponseHours'] !== null && $time['avgFirstResponseHours'] > self::HIGH_FIRST_RESPONSE_HOURS) {
            $risks[] = new RiskRow('high_first_response', RiskRow::SEVERITY_INFO, $time['firstResponseSample'],
                'Primera respuesta promedio elevada ('.$this->formatHours($time['avgFirstResponseHours']).').');
        }

        $recurrenceSuggestedIds = $this->duplicateDisplayIds($duplicateInfoByTicket, 'suggestsRecurrence');
        if ($recurrenceSuggestedIds !== []) {
            $risks[] = new RiskRow('possible_recurrence_detected', RiskRow::SEVERITY_INFO, count($recurrenceSuggestedIds),
                'El motor Strategy sugiere recurrencia (no duplicado inmediato) en tickets activos.',
                $recurrenceSuggestedIds);
        }

        $legacyIds = [];
        foreach ($duplicateInfoByTicket as $ticketId => $info) {
            if (! (bool) $info['hasStrategyMetadata']) {
                $legacyIds[] = self::TICKET_REFERENCE_PREFIX.$this->idShort($ticketId);
            }
        }
        if ($legacyIds !== []) {
            $risks[] = new RiskRow('duplicate_metadata_missing', RiskRow::SEVERITY_INFO, count($legacyIds),
                'Duplicados IA sin desglose Strategy persistido (registros antiguos); revisión manual sugerida.',
                $legacyIds);
        }

        if ($recurrenceInsights !== []) {
            $risks[] = new RiskRow('recurring_location', RiskRow::SEVERITY_INFO, count($recurrenceInsights),
                'Laboratorios asignados con recurrencias históricas registradas.');
        }

        if ($globalQueue >= self::LARGE_GLOBAL_QUEUE) {
            $risks[] = new RiskRow('large_global_queue', RiskRow::SEVERITY_INFO, $globalQueue,
                'Cola global del área con volumen alto de tickets sin asignar (contexto, no responsabilidad personal).');
        }

        $lowSamples = ($time['resolutionLowSample'] ? 1 : 0) + ($time['firstResponseLowSample'] ? 1 : 0);
        if ($lowSamples > 0) {
            $risks[] = new RiskRow('low_sample_metrics', RiskRow::SEVERITY_INFO, $lowSamples,
                'Métricas de tiempo calculadas con muestra baja (n < '.self::LOW_SAMPLE_THRESHOLD.'); interpretar con cautela.');
        }

        return $risks;
    }

    /**
     * @param  list<AssignmentRow>  $assignments
     * @return list<AssignmentRow>
     */
    private function filterByPriority(array $assignments, string $priority): array
    {
        return array_values(array_filter(
            $assignments,
            static fn (AssignmentRow $row): bool => $row->priority === $priority,
        ));
    }

    /**
     * @param  list<AssignmentRow>  $rows
     * @return list<string>
     */
    private function displayIds(array $rows): array
    {
        return array_map(static fn (AssignmentRow $row): string => $row->displayId(), $rows);
    }

    /**
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<string>
     */
    private function duplicateDisplayIds(array $duplicateInfoByTicket, string $flag): array
    {
        $ids = [];
        foreach ($duplicateInfoByTicket as $ticketId => $info) {
            if ((bool) $info[$flag]) {
                $ids[] = self::TICKET_REFERENCE_PREFIX.$this->idShort($ticketId);
            }
        }

        return $ids;
    }

    // ── Aggregated recommendations (deterministic) ───────────────────────────

    /**
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $time
     * @param  list<LocationBreakdownRow>  $locationBreakdown
     * @param  list<CategoryBreakdownRow>  $categoryBreakdown
     * @param  list<RecurrenceInsightRow>  $recurrenceInsights
     * @return list<string>
     */
    private function recommendations(
        int $criticalHigh,
        int $staleOpen,
        int $duplicatesPending,
        int $recurrenceSuggested,
        int $duplicatesLegacy,
        array $evidence,
        array $time,
        int $resolved,
        array $locationBreakdown,
        array $categoryBreakdown,
        array $recurrenceInsights,
        int $globalQueue,
        int $active,
    ): array {
        $items = [
            ...$this->actionRecommendations($criticalHigh, $staleOpen, $duplicatesPending, $recurrenceSuggested, $duplicatesLegacy, $evidence, $time),
            ...$this->contextRecommendations($resolved, $active, $globalQueue, $locationBreakdown, $categoryBreakdown, $recurrenceInsights),
        ];

        if ($items === []) {
            $items[] = 'Carga bajo control. Mantener el ritmo de cierres y la documentación de evidencias.';
        }

        return array_slice($items, 0, self::RECOMMENDATIONS_LIMIT);
    }

    /**
     * Direct operational actions over the technician's own load.
     *
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @param  array{avgResolutionHours: float|null, medianResolutionHours: float|null, resolutionSample: int, resolutionLowSample: bool, avgFirstResponseHours: float|null, firstResponseSample: int, firstResponseLowSample: bool}  $time
     * @return list<string>
     */
    private function actionRecommendations(
        int $criticalHigh,
        int $staleOpen,
        int $duplicatesPending,
        int $recurrenceSuggested,
        int $duplicatesLegacy,
        array $evidence,
        array $time,
    ): array {
        $items = [];

        if ($criticalHigh > 0) {
            $items[] = "Atender primero {$criticalHigh} ticket(s) críticos/alta sin cerrar.";
        }

        if ($staleOpen > 0) {
            $items[] = "Iniciar {$staleOpen} ticket(s) abiertos con más de ".self::STALE_OPEN_DAYS.' días de antigüedad.';
        }

        if ($duplicatesPending > 0) {
            $items[] = "Revisar {$duplicatesPending} ticket(s) marcados como posibles duplicados por IA antes de iniciar trabajo duplicado.";
        }

        if ($recurrenceSuggested > 0) {
            $items[] = "Evaluar posible recurrencia en {$recurrenceSuggested} ticket(s) señalados por el motor Strategy.";
        }

        if ($duplicatesLegacy > 0) {
            $items[] = "Revisar manualmente {$duplicatesLegacy} duplicado(s) antiguos sin desglose Strategy.";
        }

        if ($evidence['withoutEvidence'] > 0) {
            $items[] = 'Adjuntar evidencia en '.$evidence['withoutEvidence'].' ticket(s) activos sin respaldo documental.';
        }

        if ($time['avgFirstResponseHours'] !== null && $time['avgFirstResponseHours'] > self::HIGH_FIRST_RESPONSE_HOURS) {
            $items[] = 'Reducir el tiempo de primera respuesta (promedio actual '.$this->formatHours($time['avgFirstResponseHours']).').';
        }

        return $items;
    }

    /**
     * Context recommendations: period closures, breakdown leaders and area queue.
     *
     * @param  list<LocationBreakdownRow>  $locationBreakdown
     * @param  list<CategoryBreakdownRow>  $categoryBreakdown
     * @param  list<RecurrenceInsightRow>  $recurrenceInsights
     * @return list<string>
     */
    private function contextRecommendations(
        int $resolved,
        int $active,
        int $globalQueue,
        array $locationBreakdown,
        array $categoryBreakdown,
        array $recurrenceInsights,
    ): array {
        $items = [];

        // Without closures in the period, the advice depends on the real load:
        // never suggest closing active tickets when there are none.
        if ($resolved === 0) {
            if ($active > 0) {
                $items[] = 'No se registran cierres en el periodo; priorizar el avance y cierre de las asignaciones activas.';
            } elseif ($globalQueue > 0) {
                $items[] = 'No hay carga activa asignada ni cierres en el periodo; evaluar tomar tickets disponibles de la cola global si corresponde.';
            } else {
                $items[] = 'No hay carga activa asignada ni cierres en el periodo; no se registran acciones operativas pendientes al momento de generar el informe. Mantener disponibilidad o revisar nuevas asignaciones.';
            }
        }

        $leader = $locationBreakdown[0] ?? null;
        if ($leader !== null && $leader->activeCount >= 2) {
            $items[] = "El laboratorio {$leader->locationName} concentra {$leader->activeCount} incidencias activas; evaluar revisión preventiva.";
        }

        $topCategory = $categoryBreakdown[0] ?? null;
        if ($topCategory !== null && $topCategory->totalRelevant >= 2) {
            $items[] = "La categoría {$topCategory->categoryName} se repite en {$topCategory->totalRelevant} tickets relevantes del informe.";
        }

        if ($recurrenceInsights !== []) {
            $items[] = 'Existen recurrencias históricas en laboratorios asignados; ver sección de incidencias recurrentes.';
        }

        if ($globalQueue > 0) {
            $items[] = "La cola global del área tiene {$globalQueue} ticket(s) disponibles (solo contexto, no responsabilidad personal).";
        }

        return $items;
    }

    // ── Executive summary ────────────────────────────────────────────────────

    /**
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @return array{headlines: list<array{label: string, value: string}>, narrative: list<string>}
     */
    private function executiveSummary(
        int $active,
        int $open,
        int $inProgress,
        int $criticalHigh,
        int $staleOpen,
        int $resolved,
        int $rejected,
        int $cancelled,
        array $evidence,
        int $duplicatesActive,
        int $duplicatesPending,
        int $globalQueue,
        int $assignedAllTime,
        int $createdInPeriod,
    ): array {
        $headlines = [
            ['label' => self::LABEL_ACTIVE_ASSIGNED, 'value' => (string) $active],
            ['label' => 'Críticos / alta activos', 'value' => (string) $criticalHigh],
            ['label' => self::LABEL_RESOLVED_IN_PERIOD, 'value' => (string) $resolved],
            ['label' => 'Abiertos +'.self::STALE_OPEN_DAYS.self::SUFFIX_DAYS, 'value' => (string) $staleOpen],
            ['label' => 'Duplicados IA activos', 'value' => (string) $duplicatesActive],
            ['label' => 'Cola global (contexto)', 'value' => (string) $globalQueue],
        ];

        $narrative = $this->summaryNarrative(
            $active, $open, $inProgress, $criticalHigh, $staleOpen,
            $resolved, $rejected, $cancelled, $evidence,
            $duplicatesActive, $duplicatesPending, $globalQueue,
            $assignedAllTime, $createdInPeriod,
        );

        return [
            'headlines' => $headlines,
            'narrative' => array_slice($narrative, 0, 6),
        ];
    }

    /**
     * Narrative sentences for the executive summary, in fixed rule order.
     *
     * @param  array{withEvidence: int, withoutEvidence: int, coveragePct: float|null, missing: list<array{idShort: string, title: string}>}  $evidence
     * @return list<string>
     */
    private function summaryNarrative(
        int $active,
        int $open,
        int $inProgress,
        int $criticalHigh,
        int $staleOpen,
        int $resolved,
        int $rejected,
        int $cancelled,
        array $evidence,
        int $duplicatesActive,
        int $duplicatesPending,
        int $globalQueue,
        int $assignedAllTime,
        int $createdInPeriod,
    ): array {
        $narrative = [];

        $narrative[] = $active > 0
            ? "El técnico mantiene {$active} ticket(s) activos asignados: {$open} abiertos y {$inProgress} en progreso."
            : 'El técnico no tiene tickets activos asignados al momento de generar este informe.';

        $narrative[] = $criticalHigh > 0
            ? "Existen {$criticalHigh} ticket(s) de prioridad alta o crítica pendientes de cierre."
            : 'No hay tickets de prioridad alta o crítica pendientes.';

        $periodSentence = "Durante el periodo se resolvieron {$resolved} ticket(s)";
        if ($rejected > 0) {
            $periodSentence .= ", con {$rejected} cierre(s) administrativo(s) por rechazo";
        }
        if ($cancelled > 0) {
            $periodSentence .= " y {$cancelled} cancelación(es) del reporter que no penalizan al técnico";
        }
        $narrative[] = $periodSentence.'.';

        if ($staleOpen > 0) {
            $narrative[] = "{$staleOpen} ticket(s) abiertos acumulan más de ".self::STALE_OPEN_DAYS.' días sin iniciar atención.';
        }

        if ($duplicatesActive > 0) {
            $narrative[] = "Hay {$duplicatesActive} ticket(s) activos marcados como posibles duplicados por IA"
                .($duplicatesPending > 0 ? ", {$duplicatesPending} pendiente(s) de revisión operativa" : '').'.';
        } elseif ($evidence['coveragePct'] !== null) {
            $narrative[] = 'La cobertura de evidencia en asignaciones activas es del '.$this->formatPercent($evidence['coveragePct']).'.';
        }

        // Low-data narrative: explain why scope counts exist while there is no
        // active load, instead of leaving contradictory sections.
        if ($active === 0 && $globalQueue === 0 && $resolved === 0) {
            $narrative[] = 'No se registra carga operativa activa al momento de generación.';

            if ($assignedAllTime > 0 || $createdInPeriod > 0) {
                $narrative[] = 'Existe actividad histórica o del periodo asociada al técnico, detallada en el anexo.';
            }
        }

        if (count($narrative) < 6 && $globalQueue > 0) {
            $narrative[] = "La cola global disponible contiene {$globalQueue} ticket(s) sin asignar, mostrada solo como contexto del área.";
        }

        return $narrative;
    }

    // ── Appendix ─────────────────────────────────────────────────────────────

    private const REASON_ACTIVE = 'Activo';

    private const REASON_CREATED = 'Creado en periodo';

    private const REASON_HISTORICAL = 'Asignado histórico';

    private const REASON_RESOLVED = 'Resuelto en periodo';

    private const REASON_REJECTED = 'Rechazado en periodo';

    private const REASON_CANCELLED = 'Cancelado en periodo';

    /**
     * Appendix: one deduplicated row per relevant ticket with the reasons that
     * put it in the report. Covers active assignments, created in period,
     * resolved/rejected/cancelled in period, and remaining historical
     * assignments, so a report with universe A or G > 0 never shows an empty
     * appendix.
     *
     * @param  list<AssignmentRow>  $assignments
     * @param  list<ResolvedTicketRow>  $resolved
     * @param  list<ClosedTicketRow>  $rejected
     * @param  list<ClosedTicketRow>  $cancelled
     * @param  EloquentCollection<int, Ticket>  $createdModels
     * @return array{rows: list<array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}>, truncated: bool, totalRows: int}
     */
    private function appendix(
        array $assignments,
        array $resolved,
        array $rejected,
        array $cancelled,
        EloquentCollection $createdModels,
    ): array {
        $reasons = $this->appendixInclusionReasons($assignments, $resolved, $rejected, $cancelled, $createdModels);
        $reasonLabel = static fn (string $ticketId): string => implode(' · ', $reasons[$ticketId] ?? []);

        $rows = [];
        $included = [];

        foreach ($assignments as $row) {
            $included[$row->id] = true;
            $rows[] = $this->activeAppendixRow($row, $reasonLabel($row->id));
        }

        foreach ($resolved as $row) {
            $included[$row->id] = true;
            $rows[] = $this->resolvedAppendixRow($row, $reasonLabel($row->id));
        }

        foreach ([...$rejected, ...$cancelled] as $row) {
            $included[$row->id] = true;
            $rows[] = $this->closedAppendixRow($row, $reasonLabel($row->id));
        }

        // Created in the period but not active/closed yet (e.g. resolved
        // outside the range or never started): still relevant for universe G.
        foreach ($createdModels as $ticket) {
            $ticketId = (string) $ticket->id;
            if (isset($included[$ticketId])) {
                continue;
            }

            $included[$ticketId] = true;
            $rows[] = $this->genericAppendixRow($ticket, $reasonLabel($ticketId));
        }

        // Remaining historical assignments (universe A) so the appendix never
        // contradicts the scope counts. Bounded to the appendix limit.
        if (count($rows) < self::APPENDIX_LIMIT) {
            $historicalModels = $this->mine()
                ->whereNotIn('id', array_keys($included))
                ->with(['location', 'category'])
                ->orderByDesc('created_at')
                ->limit(self::APPENDIX_LIMIT - count($rows))
                ->get();

            foreach ($historicalModels as $ticket) {
                $rows[] = $this->genericAppendixRow($ticket, self::REASON_HISTORICAL);
            }
        }

        $total = count($rows);
        $truncated = $total > self::APPENDIX_LIMIT;

        return [
            'rows' => array_slice($rows, 0, self::APPENDIX_LIMIT),
            'truncated' => $truncated,
            'totalRows' => $total,
        ];
    }

    /**
     * Reasons per ticket, in presentation order.
     *
     * @param  list<AssignmentRow>  $assignments
     * @param  list<ResolvedTicketRow>  $resolved
     * @param  list<ClosedTicketRow>  $rejected
     * @param  list<ClosedTicketRow>  $cancelled
     * @param  EloquentCollection<int, Ticket>  $createdModels
     * @return array<string, list<string>>
     */
    private function appendixInclusionReasons(
        array $assignments,
        array $resolved,
        array $rejected,
        array $cancelled,
        EloquentCollection $createdModels,
    ): array {
        $reasons = [];
        $push = static function (string $ticketId, string $reason) use (&$reasons): void {
            $reasons[$ticketId] ??= [];
            if (! in_array($reason, $reasons[$ticketId], true)) {
                $reasons[$ticketId][] = $reason;
            }
        };

        foreach ($assignments as $row) {
            $push($row->id, self::REASON_ACTIVE);
        }
        foreach ($createdModels as $ticket) {
            $push((string) $ticket->id, self::REASON_CREATED);
        }
        foreach ($resolved as $row) {
            $push($row->id, self::REASON_RESOLVED);
        }
        foreach ($rejected as $row) {
            $push($row->id, self::REASON_REJECTED);
        }
        foreach ($cancelled as $row) {
            $push($row->id, self::REASON_CANCELLED);
        }

        return $reasons;
    }

    /**
     * @return array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}
     */
    private function activeAppendixRow(AssignmentRow $row, string $inclusionReason): array
    {
        return [
            'displayId' => $row->displayId(),
            'title' => $row->title,
            'locationName' => $row->locationName,
            'categoryName' => $row->categoryName,
            'priority' => $this->priorityLabel($row->priority),
            'stateLabel' => $this->stateLabel($row->state),
            'createdAt' => $row->createdAt?->format('Y-m-d') ?? '—',
            'assignedAt' => $row->assignedAt?->format('Y-m-d') ?? '—',
            'closedAt' => '—',
            'ageOrDuration' => $row->ageDays.' día(s)',
            'lastTransition' => $row->lastTransition !== null
                ? $this->stateLabel($row->lastTransition['toState']).' · '.$row->lastTransition['at']->format('Y-m-d')
                : 'Sin transiciones',
            'evidence' => $row->hasEvidence ? 'Sí ('.$row->evidenceCount.')' : 'No',
            'duplicate' => $row->hasDuplicateWarning
                ? 'Sí · '.($row->duplicateReviewStatus === null ? 'pendiente' : $row->duplicateReviewStatus)
                : 'No',
            'duplicateReasons' => $this->joinReasonLabels($row->duplicateTopReasons),
            'inclusionReason' => $inclusionReason,
        ];
    }

    /**
     * @return array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}
     */
    private function resolvedAppendixRow(ResolvedTicketRow $row, string $inclusionReason): array
    {
        return [
            'displayId' => $row->displayId(),
            'title' => $row->title,
            'locationName' => $row->locationName,
            'categoryName' => $row->categoryName,
            'priority' => $this->priorityLabel($row->priority),
            'stateLabel' => 'Resuelto',
            'createdAt' => $row->createdAt?->format('Y-m-d') ?? '—',
            'assignedAt' => '—',
            'closedAt' => $row->resolvedAt?->format('Y-m-d') ?? '—',
            'ageOrDuration' => $row->durationHours !== null ? $this->formatHours($row->durationHours) : '—',
            'lastTransition' => 'Resuelto · '.($row->resolvedAt?->format('Y-m-d') ?? '—'),
            'evidence' => $row->evidenceCount > 0 ? 'Sí ('.$row->evidenceCount.')' : 'No',
            'duplicate' => 'No',
            'duplicateReasons' => '',
            'inclusionReason' => $inclusionReason,
        ];
    }

    /**
     * @return array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}
     */
    private function closedAppendixRow(ClosedTicketRow $row, string $inclusionReason): array
    {
        return [
            'displayId' => $row->displayId(),
            'title' => $row->title,
            'locationName' => $row->locationName,
            'categoryName' => $row->categoryName,
            'priority' => $this->priorityLabel($row->priority),
            'stateLabel' => $row->kindLabel(),
            'createdAt' => $row->createdAt?->format('Y-m-d') ?? '—',
            'assignedAt' => '—',
            'closedAt' => $row->closedAt !== null ? $row->closedAt->format('Y-m-d') : '—',
            'ageOrDuration' => '—',
            'lastTransition' => $row->kindLabel().' · '.($row->closedAt !== null ? $row->closedAt->format('Y-m-d') : '—'),
            'evidence' => '—',
            'duplicate' => 'No',
            'duplicateReasons' => '',
            'inclusionReason' => $inclusionReason,
        ];
    }

    /**
     * Appendix row for tickets that are not in the active/resolved/closed row
     * sets (created in period without closure, or historical assignments).
     *
     * @return array{displayId: string, title: string, locationName: string, categoryName: string, priority: string, stateLabel: string, createdAt: string, assignedAt: string, closedAt: string, ageOrDuration: string, lastTransition: string, evidence: string, duplicate: string, duplicateReasons: string, inclusionReason: string}
     */
    private function genericAppendixRow(Ticket $ticket, string $inclusionReason): array
    {
        $resolvedAt = $ticket->resolved_at !== null ? CarbonImmutable::parse($ticket->resolved_at) : null;

        return [
            'displayId' => self::TICKET_REFERENCE_PREFIX.$this->idShort((string) $ticket->id),
            'title' => (string) $ticket->title,
            'locationName' => (string) ($ticket->location->name ?? self::LABEL_NO_LOCATION),
            'categoryName' => (string) ($ticket->category->name ?? self::LABEL_UNCATEGORIZED),
            'priority' => $this->priorityLabel((string) $ticket->priority),
            'stateLabel' => $this->stateLabel((string) $ticket->state),
            'createdAt' => $ticket->created_at !== null ? CarbonImmutable::parse($ticket->created_at)->format('Y-m-d') : '—',
            'assignedAt' => $ticket->assigned_at !== null ? CarbonImmutable::parse($ticket->assigned_at)->format('Y-m-d') : '—',
            'closedAt' => $resolvedAt?->format('Y-m-d') ?? '—',
            'ageOrDuration' => '—',
            'lastTransition' => '—',
            'evidence' => '—',
            'duplicate' => 'No',
            'duplicateReasons' => '',
            'inclusionReason' => $inclusionReason,
        ];
    }

    /**
     * @param  array<int, array{label: string, detail: string}>  $reasons
     */
    private function joinReasonLabels(array $reasons): string
    {
        return implode('; ', array_map(static fn (array $reason): string => $reason['label'], $reasons));
    }

    // ── Data quality (warn, never block) ─────────────────────────────────────

    /**
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  array<string, list<array{toState: string, at: CarbonImmutable, comment: string}>>  $history
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<array{check: string, detail: string, count: int}>
     */
    private function dataQuality(EloquentCollection $activeTickets, array $history, array $duplicateInfoByTicket): array
    {
        $issues = [];

        $inProgressWithoutTransition = 0;
        $assignedWithoutDate = 0;
        $missingLocationOrCategory = 0;

        foreach ($activeTickets as $ticket) {
            $ticketId = (string) $ticket->id;

            if ($ticket->state === Ticket::STATE_IN_PROGRESS
                && $this->firstResponseFor($ticketId, $history) === null) {
                $inProgressWithoutTransition++;
            }

            if ($ticket->assigned_at === null) {
                $assignedWithoutDate++;
            }

            if ($ticket->location === null || $ticket->category === null) {
                $missingLocationOrCategory++;
            }
        }

        $this->pushIssue($issues, $inProgressWithoutTransition, 'in_progress_sin_transicion',
            'Tickets en progreso sin transición registrada a en progreso en state_history.');
        $this->pushIssue($issues, $assignedWithoutDate, 'asignado_sin_fecha',
            'Tickets asignados sin fecha de asignación (assigned_at).');
        $this->pushIssue($issues, $missingLocationOrCategory, 'ubicacion_categoria_faltante',
            'Tickets activos sin ubicación o categoría válida.');

        $resolvedWithoutDate = $this->mine()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNull('resolved_at')
            ->count();
        $this->pushIssue($issues, $resolvedWithoutDate, 'resuelto_sin_fecha',
            'Tickets en estado resuelto sin fecha de resolución (resolved_at).');

        $unknownState = $this->mine()->whereNotIn('state', self::KNOWN_STATES)->count();
        $this->pushIssue($issues, $unknownState, 'estado_desconocido',
            'Tickets asignados con un estado fuera del catálogo conocido.');

        $unknownPriority = $this->mine()->whereNotIn('priority', self::KNOWN_PRIORITIES)->count();
        $this->pushIssue($issues, $unknownPriority, 'prioridad_desconocida',
            'Tickets asignados con una prioridad fuera del catálogo conocido.');

        $activeIds = $activeTickets->pluck('id')->all();
        if ($activeIds !== []) {
            $mediaWithoutUrl = DB::table('ticket_media')
                ->whereIn('ticket_id', $activeIds)
                ->where(function ($query): void {
                    $query->whereNull('file_url')->orWhere('file_url', '');
                })
                ->count();
            $this->pushIssue($issues, $mediaWithoutUrl, 'evidencia_sin_url',
                'Evidencias registradas sin URL de archivo.');
        }

        return array_merge($issues, $this->duplicateDataQualityIssues($activeTickets, $duplicateInfoByTicket));
    }

    /**
     * AI-duplicate consistency (read-only; the report never blocks on these).
     *
     * @param  EloquentCollection<int, Ticket>  $activeTickets
     * @param  array<string, array<string, mixed>>  $duplicateInfoByTicket
     * @return list<array{check: string, detail: string, count: int}>
     */
    private function duplicateDataQualityIssues(EloquentCollection $activeTickets, array $duplicateInfoByTicket): array
    {
        $issues = [];
        $dupWithoutMatch = 0;
        $dupWithoutSimilarity = 0;
        $dupBrokenMatch = 0;
        $dupCorruptResults = 0;
        $recurrenceWithoutMetadata = 0;

        foreach ($activeTickets as $ticket) {
            $embedding = $ticket->embedding;
            if ($embedding === null || ! $embedding->effective_duplicate) {
                continue;
            }

            if ($embedding->matched_ticket_id === null) {
                $dupWithoutMatch++;

                continue;
            }

            $info = $duplicateInfoByTicket[(string) $ticket->id] ?? null;

            if ($embedding->similarity_score === null) {
                $dupWithoutSimilarity++;
            }

            if ($info !== null && ! (bool) $info['matchedExists']) {
                $dupBrokenMatch++;
            }

            $raw = $embedding->getAttribute('strategy_results');
            if ($raw !== null && $raw !== [] && ! $this->strategyResultsUsable($raw)) {
                $dupCorruptResults++;
            }

            if ((bool) $embedding->strategy_suggests_recurrence && ! $this->strategyResultsUsable($raw)) {
                $recurrenceWithoutMetadata++;
            }
        }

        $this->pushIssue($issues, $dupWithoutMatch, 'duplicado_sin_match',
            'Tickets marcados como duplicado sin ticket similar referenciado (matched_ticket_id).');
        $this->pushIssue($issues, $dupWithoutSimilarity, 'duplicado_sin_similitud',
            'Duplicados IA sin similarity_score registrado.');
        $this->pushIssue($issues, $dupBrokenMatch, 'duplicado_match_inexistente',
            'Duplicados IA cuyo ticket similar referenciado ya no existe.');
        $this->pushIssue($issues, $dupCorruptResults, 'strategy_results_corrupto',
            'Duplicados IA con desglose Strategy corrupto o ilegible; se aplicó fallback.');
        $this->pushIssue($issues, $recurrenceWithoutMetadata, 'recurrencia_sin_metadata',
            'Señal de recurrencia Strategy sin metadata asociada.');

        return $issues;
    }

    /**
     * @param  list<array{check: string, detail: string, count: int}>  $issues
     */
    private function pushIssue(array &$issues, int $count, string $check, string $detail): void
    {
        if ($count > 0) {
            $issues[] = ['check' => $check, 'detail' => $detail, 'count' => $count];
        }
    }

    // ── Definitions ──────────────────────────────────────────────────────────

    /**
     * @return list<array{term: string, definition: string}>
     */
    private function definitions(): array
    {
        return [
            ['term' => 'Snapshot', 'definition' => 'Dato medido al momento exacto de generar el informe; ignora el rango de fechas.'],
            ['term' => 'Periodo', 'definition' => 'Dato acotado al rango de fechas seleccionado.'],
            ['term' => self::LABEL_ACTIVE_ASSIGNED, 'definition' => 'Tickets asignados al técnico en estado abierto o en progreso (snapshot).'],
            ['term' => 'Abiertos', 'definition' => 'Asignados en estado abierto, aún sin iniciar.'],
            ['term' => self::LABEL_IN_PROGRESS, 'definition' => 'Asignados con trabajo técnico iniciado.'],
            ['term' => 'Críticos / alta', 'definition' => 'Activos con prioridad crítica o alta.'],
            ['term' => 'Sin iniciar +'.self::STALE_OPEN_DAYS.self::SUFFIX_DAYS, 'definition' => 'Abiertos creados hace más de '.self::STALE_OPEN_DAYS.' días sin primera respuesta.'],
            ['term' => self::LABEL_RESOLVED_IN_PERIOD, 'definition' => 'Asignados con fecha de resolución (resolved_at) dentro del rango.'],
            ['term' => 'Rechazados', 'definition' => 'Cierre administrativo: transición real a rechazado en state_history dentro del rango. No es un resuelto.'],
            ['term' => 'Cancelados', 'definition' => 'Retiro voluntario del reporter: transición real a cancelado dentro del rango. No penaliza al técnico ni entra en la tasa de cierre.'],
            ['term' => 'Primera respuesta', 'definition' => 'Tiempo desde la creación del ticket hasta su primera transición a en progreso (state_history).'],
            ['term' => 'Promedio resolución', 'definition' => 'Media de horas entre creación y resolución de los resueltos del periodo.'],
            ['term' => 'Mediana resolución', 'definition' => 'Valor central de las duraciones de resolución; robusto ante casos extremos.'],
            ['term' => 'Tasa de cierre operativa', 'definition' => 'Resueltos del periodo / (resueltos del periodo + activos actuales) × 100. Excluye cancelados. Si no hay resueltos ni activos se muestra "Sin datos" porque la métrica no aplica.'],
            ['term' => 'Cobertura de evidencia', 'definition' => 'Porcentaje de asignaciones activas con al menos un archivo adjunto en ticket_media.'],
            ['term' => self::LABEL_GLOBAL_QUEUE_AVAILABLE, 'definition' => 'Tickets abiertos, sin asignar y sin bloqueo de asignación. Contexto del área; no es responsabilidad personal del técnico.'],
            ['term' => 'Muestra baja', 'definition' => 'Métrica calculada con menos de '.self::LOW_SAMPLE_THRESHOLD.' casos; debe interpretarse con cautela.'],
            ['term' => 'Posible duplicado IA', 'definition' => 'Ticket marcado por el sistema como similar a otro ticket existente. Señal operativa, no métrica de productividad.'],
            ['term' => 'Similitud', 'definition' => 'Valor numérico (0 a 1) que representa la cercanía semántica entre las descripciones de dos tickets.'],
            ['term' => 'Score Strategy', 'definition' => 'Puntaje agregado por reglas independientes de detección. Sirve para explicación y trazabilidad; no es una decisión absoluta.'],
            ['term' => 'Razones Strategy', 'definition' => 'Lista de reglas que aportaron puntos o advertencias a la detección del posible duplicado.'],
            ['term' => 'Posible recurrencia', 'definition' => 'Señal de que el incidente podría repetirse en el tiempo, no necesariamente ser un duplicado inmediato.'],
            ['term' => 'Registro antiguo sin desglose Strategy', 'definition' => 'Duplicado detectado antes de la persistencia de metadata Strategy; se muestra con explicación reducida (fallback).'],
        ];
    }

    // ── Formatting helpers ───────────────────────────────────────────────────

    private function idShort(string $id): string
    {
        return substr($id, 0, self::ID_SHORT_LENGTH);
    }

    private function formatHours(?float $hours): string
    {
        if ($hours === null) {
            return 'Sin datos';
        }

        return $this->trimNumber($hours).' h';
    }

    private function formatPercent(float $value): string
    {
        return $this->trimNumber($value).' %';
    }

    private function formatPercentOrNa(?float $value): string
    {
        return $value === null ? 'Sin datos' : $this->formatPercent($value);
    }

    private function trimNumber(float $value): string
    {
        $formatted = number_format(round($value, 1), 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Abierto',
            Ticket::STATE_IN_PROGRESS => self::LABEL_IN_PROGRESS,
            Ticket::STATE_RESOLVED => 'Resuelto',
            Ticket::STATE_REJECTED => 'Rechazado',
            Ticket::STATE_CANCELLED => 'Cancelado',
            default => $state,
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
            default => $priority,
        };
    }

    /**
     * @param  list<AssignmentRow>  $assignments
     */
    private function countAssignments(array $assignments, string $state): int
    {
        return count(array_filter(
            $assignments,
            static fn (AssignmentRow $row): bool => $row->state === $state,
        ));
    }
}
