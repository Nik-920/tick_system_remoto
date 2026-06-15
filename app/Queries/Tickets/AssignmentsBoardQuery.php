<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\Concerns\TicketBoardHelpers;
use App\ViewModels\Tickets\AssignmentsBoardViewModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the maintenance "Mis asignaciones" board for a single technician.
 *
 * Security contract (mirrors MaintenanceBoardQuery / TicketIndexQuery): the
 * ownership scope is applied FIRST and unconditionally — the technician only
 * ever sees tickets assigned to THEM (assigned_to = their id). Tabs, filters,
 * sort and the focused detail are all narrowed inside that boundary, so no
 * request parameter can widen what a technician may read.
 *
 * Portability: counts use COUNT()/GROUP BY and durations are computed in PHP,
 * so the same code runs on SQLite (tests) and PostgreSQL.
 */
final class AssignmentsBoardQuery
{
    use TicketBoardHelpers;

    /** @var list<string> */
    public const TABS = ['active', 'waiting', 'completed'];

    /** Tab → ticket states it surfaces (within the technician's own tickets). */
    private const TAB_STATES = [
        'active' => [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS],
        'waiting' => [Ticket::STATE_OPEN],
        'completed' => [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED],
    ];

    /** @var list<string> */
    public const SORTS = ['recent', 'oldest', 'priority', 'elapsed'];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    /** Rows rendered in the center list (cap; `all=1` lifts it to the wide cap). */
    private const LIST_LIMIT = 25;

    private const LIST_LIMIT_ALL = 200;

    /** Activity entries shown in the detail rail. */
    private const ACTIVITY_LIMIT = 8;

    /** Formato de fecha-hora 12 h mostrado en el board. */
    private const DISPLAY_DATETIME_12H_FORMAT = 'd/m/Y h:i A';

    /** SLA-ish overdue thresholds (days) for an active ticket, by priority. */
    private const OVERDUE_DAYS = ['critical' => 1, 'high' => 1, 'medium' => 3, 'low' => 7];

    /** @var array<string, CarbonInterface>  ticket_id → moment it entered in_progress (latest). */
    private array $inProgressStarts = [];

    /** @var array<string, int>|null  Cached per-state counts for the technician's own tickets. */
    private ?array $stateCountsCache = null;

    /**
     * @param  array<string, mixed>  $filters  search, state, priority, location_id, category_id, sort, focus, all
     */
    public function __construct(
        private readonly User $user,
        private readonly array $filters,
        private readonly string $tab,
        private readonly string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function for(User $user, array $filters, string $tab, string $sort): AssignmentsBoardViewModel
    {
        return (new self($user, $filters, $tab, $sort))->build();
    }

    public function build(): AssignmentsBoardViewModel
    {
        $rows = $this->rows();
        $total = $this->scopedForTab()->count();

        return new AssignmentsBoardViewModel(
            technicianId: $this->userId(),
            activeTab: $this->tab,
            tabs: $this->tabs(),
            filters: $this->filters,
            sort: $this->sort,
            locations: Location::query()->active()->orderBy('name')->get(),
            categories: Category::query()->orderBy('name')->get(),
            summary: $this->summary(),
            assignments: $rows->map(fn (Ticket $t): array => $this->shapeRow($t))->all(),
            shownCount: $rows->count(),
            totalCount: $total,
            hasMore: $total > $rows->count(),
            focused: $this->focused($rows),
        );
    }

    // ── Security boundary ────────────────────────────────────────

    /**
     * The ownership scope: ONLY tickets assigned to this technician.
     *
     * @return Builder<Ticket>
     */
    private function mineBase(): Builder
    {
        return Ticket::query()->where('assigned_to', $this->userId());
    }

    /**
     * Mine + active tab states + user filters, ordered by the chosen sort.
     *
     * @return Builder<Ticket>
     */
    private function scopedForTab(): Builder
    {
        $query = $this->mineBase()->whereIn('state', self::TAB_STATES[$this->tab]);

        $this->applyFilters($query);

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applyFilters(Builder $query): void
    {
        // A state filter narrows WITHIN the tab; if it conflicts with the tab
        // it simply empties the list (consistent with the maintenance board).
        if (! empty($this->filters['state'])) {
            $query->where('state', $this->filters['state']);
        }

        if (! empty($this->filters['priority'])) {
            $query->where('priority', $this->filters['priority']);
        }

        if (! empty($this->filters['location_id'])) {
            $query->where('location_id', $this->filters['location_id']);
        }

        if (! empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }

        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $this->applySort($query);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            'oldest' => $query->oldest('created_at'),
            'priority' => $query->orderByRaw(self::PRIORITY_ORDER)->latest('created_at'),
            'elapsed' => $query->orderByRaw('assigned_at is null')->oldest('assigned_at')->latest('created_at'),
            default => $query->latest('created_at'),
        };
    }

    // ── Center list ──────────────────────────────────────────────

    /**
     * @return Collection<int, Ticket>
     */
    private function rows(): Collection
    {
        $limit = ! empty($this->filters['all']) ? self::LIST_LIMIT_ALL : self::LIST_LIMIT;

        $rows = $this->scopedForTab()
            ->with(['location', 'category'])
            ->limit($limit)
            ->get();

        $this->inProgressStarts = $this->inProgressStartsFor($rows->pluck('id')->all());

        return $rows;
    }

    /**
     * One grouped query: latest in_progress timestamp per ticket. Used to show
     * the live "time elapsed" without an N+1 over state history.
     *
     * @param  list<string>  $ids
     * @return array<string, CarbonInterface>
     */
    private function inProgressStartsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return StateHistory::query()
            ->whereIn('ticket_id', $ids)
            ->where('to_state', Ticket::STATE_IN_PROGRESS)
            ->selectRaw('ticket_id, MAX(created_at) as started_at')
            ->groupBy('ticket_id')
            ->get()
            ->mapWithKeys(fn (StateHistory $h): array => [
                (string) $h->ticket_id => Carbon::parse((string) $h->getAttribute('started_at')),
            ])
            ->all();
    }

    // ── Tabs & summary ───────────────────────────────────────────

    /**
     * One GROUP BY query that returns counts for all states at once.
     * Called by both tabs() and summary(); result is cached for the lifetime
     * of this build() call so the 6 individual COUNT queries collapse to 1.
     *
     * @return array<string, int>
     */
    private function resolvedStateCounts(): array
    {
        if ($this->stateCountsCache !== null) {
            return $this->stateCountsCache;
        }

        $this->stateCountsCache = $this->mineBase()
            ->selectRaw('state, COUNT(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')
            ->map(fn ($v): int => (int) $v)
            ->all();

        return $this->stateCountsCache;
    }

    /**
     * @param  list<string>  $states
     */
    private function countStates(array $states): int
    {
        $counts = $this->resolvedStateCounts();

        return (int) collect($states)->sum(fn (string $s): int => $counts[$s] ?? 0);
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function tabs(): array
    {
        return [
            ['key' => 'active', 'label' => 'Activas', 'count' => $this->countStates(self::TAB_STATES['active'])],
            ['key' => 'waiting', 'label' => 'En espera', 'count' => $this->countStates(self::TAB_STATES['waiting'])],
            ['key' => 'completed', 'label' => 'Completadas', 'count' => $this->countStates(self::TAB_STATES['completed'])],
        ];
    }

    /**
     * Summary cards describe the technician's whole active universe and stay
     * stable across tabs/filters (so the counters don't jump around).
     *
     * @return array{active: int, in_progress: int, waiting: int, overdue: int, compliance: int}
     */
    private function summary(): array
    {
        $active = $this->countStates(self::TAB_STATES['active']);
        $overdue = $this->overdueCount();

        return [
            'active' => $active,
            'in_progress' => $this->countStates([Ticket::STATE_IN_PROGRESS]),
            'waiting' => $this->countStates([Ticket::STATE_OPEN]),
            'overdue' => $overdue,
            'compliance' => $active > 0 ? (int) round(($active - $overdue) / $active * 100) : 100,
        ];
    }

    private function overdueCount(): int
    {
        $now = CarbonImmutable::now();

        return $this->mineBase()
            ->whereIn('state', self::TAB_STATES['active'])
            ->get(['priority', 'created_at'])
            ->filter(fn (Ticket $t): bool => $this->isOverdue(
                (string) $t->priority,
                $t->created_at,
                $now,
            ))
            ->count();
    }

    // ── Focused detail (right rail) ──────────────────────────────

    /**
     * Pick the focused assignment: the `focus` ticket if it belongs to the
     * technician, otherwise the first row of the active list. Returns the full
     * shaped detail (incl. stepper + activity) or null when nothing to show.
     *
     * @param  Collection<int, Ticket>  $rows
     * @return array<string, mixed>|null
     */
    private function focused(Collection $rows): ?array
    {
        $focusId = trim((string) ($this->filters['focus'] ?? ''));

        $ticket = null;
        if ($focusId !== '') {
            $ticket = $this->mineBase()
                ->whereKey($focusId)
                ->with(['location', 'category', 'assignedBy', 'stateHistory.changedBy'])
                ->first();
        }

        if ($ticket === null) {
            $first = $rows->first();
            if ($first === null) {
                return null;
            }
            $ticket = $this->mineBase()
                ->whereKey($first->id)
                ->with(['location', 'category', 'assignedBy', 'stateHistory.changedBy'])
                ->first();
        }

        return $ticket === null ? null : $this->shapeDetail($ticket);
    }

    // ── Shaping ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(Ticket $ticket): array
    {
        $state = (string) $ticket->state;
        $inProgressAt = $this->inProgressStarts[(string) $ticket->id] ?? null;
        $elapsed = $state === Ticket::STATE_IN_PROGRESS
            ? $this->elapsedLabel($inProgressAt ?? $ticket->assigned_at, CarbonImmutable::now())
            : null;

        [$action, $actionKind] = $this->actionFor($state);

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'location' => $ticket->location?->name ?? 'Sin ubicación',
            'category' => $ticket->category?->name ?? 'Sin categoría',
            'type' => $ticket->location?->room_code ?? '',
            'icon' => $this->iconFor($ticket->category?->name),
            'tone' => $this->priorityTone((string) $ticket->priority),
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'state_note' => $this->stateNote($state, $ticket->assignment_source),
            'priority' => $this->priorityTone((string) $ticket->priority),
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'created' => $ticket->created_at?->format('d/m/Y') ?? '—',
            'elapsed' => $elapsed,
            'overdue' => $this->isOverdue((string) $ticket->priority, $ticket->created_at, CarbonImmutable::now())
                && in_array($state, self::TAB_STATES['active'], true),
            'action' => $action,
            'action_kind' => $actionKind,
            'can_start' => $this->canStart($ticket),
            'can_release' => $this->canRelease($ticket),
            'selected' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeDetail(Ticket $ticket): array
    {
        $state = (string) $ticket->state;
        $history = $ticket->stateHistory->sortByDesc('created_at')->values();
        $stateTimes = $this->stateTimes($history);
        $inProgressAt = $stateTimes[Ticket::STATE_IN_PROGRESS] ?? null;

        $elapsedFrom = match ($state) {
            Ticket::STATE_IN_PROGRESS => $inProgressAt ?? $ticket->assigned_at,
            default => null,
        };
        if ($elapsedFrom !== null) {
            $elapsed = $this->elapsedLabel($elapsedFrom, CarbonImmutable::now());
        } elseif ($state === Ticket::STATE_RESOLVED && $ticket->created_at !== null && $ticket->resolved_at !== null) {
            $elapsed = $this->elapsedLabel($ticket->created_at, $ticket->resolved_at);
        } else {
            $elapsed = '—';
        }

        $context = collect([
            $ticket->location?->name,
            $ticket->category?->name,
            $ticket->location?->room_code,
        ])->filter()->implode(' · ');

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'priority' => $this->priorityTone((string) $ticket->priority),
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'context' => $context !== '' ? $context : 'Sin ubicación',
            'description' => (string) ($ticket->description ?? ''),
            'assigned_at' => $ticket->assigned_at?->format(self::DISPLAY_DATETIME_12H_FORMAT) ?? '—',
            'assigned_by' => $this->displayName($ticket->assignedBy),
            'taken_at' => $inProgressAt?->format(self::DISPLAY_DATETIME_12H_FORMAT) ?? ($ticket->assigned_at?->format(self::DISPLAY_DATETIME_12H_FORMAT) ?? '—'),
            'elapsed' => $elapsed,
            'can_start' => $this->canStart($ticket),
            'can_release' => $this->canRelease($ticket),
            'steps' => $this->stepsFor($ticket, $stateTimes),
            'activity' => $this->activityFor($history),
        ];
    }

    /**
     * @param  array<string, CarbonInterface>  $stateTimes
     * @return list<array{key: string, label: string, status: string, at: string}>
     */
    private function stepsFor(Ticket $ticket, array $stateTimes): array
    {
        $state = (string) $ticket->state;
        $closed = in_array($state, [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED], true);
        $assigned = $ticket->assigned_at !== null;
        $short = static fn (?CarbonInterface $d): string => $d?->format('d/m H:i') ?? '—';

        $inProgressAt = $stateTimes[Ticket::STATE_IN_PROGRESS] ?? null;
        $inProgressStatus = match (true) {
            $state === Ticket::STATE_IN_PROGRESS => 'current',
            $closed => 'done',
            default => 'pending',
        };

        $isRejected = $state === Ticket::STATE_REJECTED;
        $finalAt = $stateTimes[$isRejected ? Ticket::STATE_REJECTED : Ticket::STATE_RESOLVED]
            ?? ($ticket->resolved_at instanceof CarbonInterface ? $ticket->resolved_at : null);

        if ($inProgressAt !== null) {
            $inProgressTime = $short($inProgressAt);
        } elseif ($state === Ticket::STATE_IN_PROGRESS) {
            $inProgressTime = 'Actual';
        } else {
            $inProgressTime = '—';
        }

        return [
            ['key' => 'open', 'label' => 'Abierto', 'status' => 'done', 'at' => $short($ticket->created_at)],
            [
                'key' => 'taken',
                'label' => $ticket->assignment_source === Ticket::ASSIGNMENT_SOURCE_ADMIN ? 'Asignado' : 'Tomado',
                'status' => $assigned ? 'done' : 'pending',
                'at' => $assigned ? $short($ticket->assigned_at) : '—',
            ],
            [
                'key' => 'in_progress',
                'label' => 'En progreso',
                'status' => $inProgressStatus,
                'at' => $inProgressTime,
            ],
            [
                'key' => 'closed',
                'label' => $isRejected ? 'Rechazado' : 'Resuelto',
                'status' => $closed ? 'done' : 'pending',
                'at' => $closed && $finalAt !== null ? $short($finalAt) : '—',
            ],
        ];
    }

    /**
     * @param  Collection<int, StateHistory>  $history  Already sorted newest first.
     * @return list<array{icon: string, tone: string, text: string, actor: string, at: string}>
     */
    private function activityFor(Collection $history): array
    {
        return $history
            ->take(self::ACTIVITY_LIMIT)
            ->map(function (StateHistory $h): array {
                $from = (string) $h->from_state;
                $to = (string) $h->to_state;
                $isStateChange = $from !== $to;

                if ($isStateChange) {
                    [$text, $icon, $tone] = match ($to) {
                        Ticket::STATE_IN_PROGRESS => ['Trabajo iniciado', 'loader', 'progress'],
                        Ticket::STATE_RESOLVED => ['Ticket resuelto', 'circle-check', 'available'],
                        Ticket::STATE_REJECTED => ['Ticket rechazado', 'x', 'high'],
                        Ticket::STATE_OPEN => ['Ticket reabierto', 'inbox', 'info'],
                        default => ['Estado actualizado', 'activity', 'neutral'],
                    };
                } else {
                    $text = trim((string) $h->comment) !== '' ? (string) $h->comment : 'Asignación actualizada';
                    $icon = 'user-check';
                    $tone = 'primary';
                }

                return [
                    'icon' => $icon,
                    'tone' => $tone,
                    'text' => $text,
                    'actor' => $this->displayName($h->changedBy),
                    'at' => $h->created_at?->format(self::DISPLAY_DATETIME_12H_FORMAT) ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    // ── Small helpers ────────────────────────────────────────────

    /**
     * @param  Collection<int, StateHistory>  $history
     * @return array<string, CarbonInterface> Latest timestamp seen for each to_state.
     */
    private function stateTimes(Collection $history): array
    {
        $times = [];
        foreach ($history as $h) {
            $to = (string) $h->to_state;
            $at = $h->created_at;
            if ($at instanceof CarbonInterface && ! isset($times[$to])) {
                // history is newest-first, so the first hit is the latest.
                $times[$to] = $at;
            }
        }

        return $times;
    }

    private function isOverdue(string $priority, ?CarbonInterface $createdAt, CarbonInterface $now): bool
    {
        if ($createdAt === null) {
            return false;
        }

        $threshold = self::OVERDUE_DAYS[$priority] ?? 3;

        return $createdAt->diffInDays($now) >= $threshold;
    }

    private function elapsedLabel(?CarbonInterface $from, CarbonInterface $to): ?string
    {
        if ($from === null) {
            return null;
        }

        return $this->formatDuration((int) round($from->diffInMinutes($to)));
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Abierto',
            Ticket::STATE_IN_PROGRESS => 'En progreso',
            Ticket::STATE_RESOLVED => 'Resuelto',
            Ticket::STATE_REJECTED => 'Rechazado',
            default => ucfirst($state),
        };
    }

    private function stateNote(string $state, ?string $source): string
    {
        return match ($state) {
            Ticket::STATE_IN_PROGRESS => $source === Ticket::ASSIGNMENT_SOURCE_ADMIN ? 'Asignado' : 'Tomado',
            Ticket::STATE_OPEN => 'Pendiente de iniciar',
            Ticket::STATE_RESOLVED => 'Completado',
            Ticket::STATE_REJECTED => 'Rechazado',
            default => '',
        };
    }

    /**
     * @return array{0: string, 1: string} [label, kind]
     */
    private function actionFor(string $state): array
    {
        return match ($state) {
            Ticket::STATE_IN_PROGRESS => ['Continuar', 'primary'],
            Ticket::STATE_OPEN => ['Iniciar', 'primary'],
            default => ['Ver detalle', 'ghost'],
        };
    }

    /**
     * May the technician start work (open → in_progress)? Mirrors the
     * TicketPolicy::updateState path for an own, open ticket. Rows are already
     * scoped to assigned_to = this user, so ownership is implicit.
     */
    private function canStart(Ticket $ticket): bool
    {
        return (string) $ticket->state === Ticket::STATE_OPEN;
    }

    /**
     * May the technician release the ticket? Mirrors TicketPolicy::release:
     * own, open, unlocked and self-claimed (admin-assigned tickets stay put).
     */
    private function canRelease(Ticket $ticket): bool
    {
        return (string) $ticket->state === Ticket::STATE_OPEN
            && ! $ticket->assignment_locked
            && $ticket->assignment_source === Ticket::ASSIGNMENT_SOURCE_SELF;
    }

    private function userId(): string
    {
        return (string) $this->user->id;
    }
}
