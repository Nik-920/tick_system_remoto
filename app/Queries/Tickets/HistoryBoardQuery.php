<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\Concerns\TicketBoardHelpers;
use App\ViewModels\Tickets\HistoryBoardViewModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the maintenance "Historial" board for a single technician.
 *
 * Security contract (mirrors AssignmentsBoardQuery / MaintenanceBoardQuery):
 * the ownership scope is applied FIRST and unconditionally — the technician
 * only ever sees their OWN closed-out tickets (assigned_to = them, state in
 * resolved/rejected). The result chip, filters, sort, date range and page are
 * all narrowed inside that boundary, so no request parameter can widen what a
 * technician may read.
 *
 * Domain note: the ticket lifecycle has no "closed"/"cancelled" state — only
 * open, in_progress, resolved, rejected. History therefore = resolved+rejected.
 * There is no rejected_at column, so the effective "closed at" used for the
 * period filter and ordering is COALESCE(resolved_at, updated_at), which is
 * portable across SQLite (tests) and PostgreSQL. The precise rejection moment
 * shown per row comes from state_history (to_state = rejected).
 *
 * Portability: counts use COUNT()/GROUP BY and durations are computed in PHP,
 * so the same code runs on SQLite and PostgreSQL.
 */
final class HistoryBoardQuery
{
    use TicketBoardHelpers;

    /** @var list<string> */
    public const RESULTS = ['all', 'resolved', 'rejected'];

    /** @var list<string> */
    public const SORTS = ['recent', 'oldest', 'priority'];

    /** @var list<string> Final states that make up the history universe. */
    private const HISTORY_STATES = [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    /** Portable "closed at" expression: resolved_at for resolved, else updated_at. */
    private const CLOSED_AT = 'COALESCE(resolved_at, updated_at)';

    private const PER_PAGE = 8;

    private const LABS_LIMIT = 5;

    private const ACTIVITY_LIMIT = 6;

    /** Resolved tickets sampled to compute the average resolution time. */
    private const AVG_POOL_LIMIT = 200;

    /** @var array<string, CarbonInterface> ticket_id → rejection moment (latest), filled by rows(). */
    private array $rejectionTimes = [];

    /**
     * @param  array<string, mixed>  $filters  result, search, priority, location_id, category_id, from, to, page
     */
    public function __construct(
        private readonly User $user,
        private readonly array $filters,
        private readonly string $result,
        private readonly string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function for(User $user, array $filters, string $result, string $sort): HistoryBoardViewModel
    {
        return (new self($user, $filters, $result, $sort))->build();
    }

    public function build(): HistoryBoardViewModel
    {
        $total = $this->scopedForList()->count();
        $page = $this->currentPage($total);
        $rows = $this->rows($page);

        return new HistoryBoardViewModel(
            technicianId: $this->userId(),
            activeResult: $this->result,
            chips: $this->chips(),
            filters: $this->filters,
            sort: $this->sort,
            locations: Location::query()->active()->orderBy('name')->get(),
            categories: Category::query()->orderBy('name')->get(),
            summary: $this->summary(),
            tickets: $rows->map(fn (Ticket $t): array => $this->shapeRow($t))->all(),
            pagination: $this->pagination($page, $rows->count(), $total),
            donut: $this->donut(),
            topLabs: $this->topLabs(),
            activity: $this->activity(),
        );
    }

    // ── Security boundary ────────────────────────────────────────

    /**
     * The ownership scope: ONLY this technician's closed-out tickets.
     *
     * @return Builder<Ticket>
     */
    private function mineHistoryBase(): Builder
    {
        return Ticket::query()
            ->where('assigned_to', $this->userId())
            ->whereIn('state', self::HISTORY_STATES);
    }

    /**
     * Mine + the selected date range (the "period"). Drives the stable
     * analytics (summary, chips, donut, labs) regardless of the active chip.
     *
     * @return Builder<Ticket>
     */
    private function periodBase(): Builder
    {
        $query = $this->mineHistoryBase();
        $this->applyPeriod($query);

        return $query;
    }

    /**
     * Period + result chip + user filters, ordered by the chosen sort.
     *
     * @return Builder<Ticket>
     */
    private function scopedForList(): Builder
    {
        $query = $this->periodBase();

        if (in_array($this->result, [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED], true)) {
            $query->where('state', $this->result);
        }

        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applyPeriod(Builder $query): void
    {
        $from = $this->parseDate((string) ($this->filters['from'] ?? ''));
        $to = $this->parseDate((string) ($this->filters['to'] ?? ''));

        if ($from !== null) {
            $query->whereRaw(self::CLOSED_AT.' >= ?', [$from->startOfDay()->format('Y-m-d H:i:s')]);
        }

        if ($to !== null) {
            $query->whereRaw(self::CLOSED_AT.' <= ?', [$to->endOfDay()->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applyFilters(Builder $query): void
    {
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
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "{$search}%");
            });
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            'oldest' => $query->orderByRaw(self::CLOSED_AT.' asc'),
            'priority' => $query->orderByRaw(self::PRIORITY_ORDER)->orderByRaw(self::CLOSED_AT.' desc'),
            default => $query->orderByRaw(self::CLOSED_AT.' desc'),
        };
    }

    // ── Center list ──────────────────────────────────────────────

    /**
     * @return Collection<int, Ticket>
     */
    private function rows(int $page): Collection
    {
        $rows = $this->scopedForList()
            ->with(['location', 'category'])
            ->forPage($page, self::PER_PAGE)
            ->get();

        $this->rejectionTimes = $this->rejectionTimesFor(
            $rows->where('state', Ticket::STATE_REJECTED)->pluck('id')->all()
        );

        return $rows;
    }

    /**
     * One grouped query: latest rejection timestamp per ticket, so the row can
     * show a precise rejection date without an N+1 over state history.
     *
     * @param  list<string>  $ids
     * @return array<string, CarbonInterface>
     */
    private function rejectionTimesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return StateHistory::query()
            ->whereIn('ticket_id', $ids)
            ->where('to_state', Ticket::STATE_REJECTED)
            ->selectRaw('ticket_id, MAX(created_at) as rejected_at')
            ->groupBy('ticket_id')
            ->get()
            ->mapWithKeys(fn (StateHistory $h): array => [
                (string) $h->ticket_id => Carbon::parse((string) $h->getAttribute('rejected_at')),
            ])
            ->all();
    }

    // ── Chips & summary (period-scoped, stable across the active chip) ─

    /**
     * @return list<array{key: string, label: string, count: int, tone: string}>
     */
    private function chips(): array
    {
        $resolved = (clone $this->periodBase())->where('state', Ticket::STATE_RESOLVED)->count();
        $rejected = (clone $this->periodBase())->where('state', Ticket::STATE_REJECTED)->count();

        return [
            ['key' => 'all', 'label' => 'Todos', 'count' => $resolved + $rejected, 'tone' => 'neutral'],
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $resolved, 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $rejected, 'tone' => 'high'],
        ];
    }

    /**
     * @return array{total: int, resolved: int, rejected: int, resolved_pct: int, rejected_pct: int, resolution_rate: int, avg_label: string, avg_has_data: bool}
     */
    private function summary(): array
    {
        $resolved = (clone $this->periodBase())->where('state', Ticket::STATE_RESOLVED)->count();
        $rejected = (clone $this->periodBase())->where('state', Ticket::STATE_REJECTED)->count();
        $total = $resolved + $rejected;

        $pct = static fn (int $count): int => $total > 0 ? (int) round($count / $total * 100) : 0;
        $avg = $this->averageResolution();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'rejected' => $rejected,
            'resolved_pct' => $pct($resolved),
            'rejected_pct' => $pct($rejected),
            'resolution_rate' => $pct($resolved),
            'avg_label' => $avg['label'],
            'avg_has_data' => $avg['has_data'],
        ];
    }

    // ── Right rail analytics ─────────────────────────────────────

    /**
     * Donut by final result: resolved vs rejected, with cumulative percents the
     * view turns into a conic-gradient and a text legend (never colour-only).
     *
     * @return array{total: int, segments: list<array{key: string, label: string, count: int, percent: float, start: float, end: float, color: string, tone: string}>}
     */
    private function donut(): array
    {
        $resolved = (clone $this->periodBase())->where('state', Ticket::STATE_RESOLVED)->count();
        $rejected = (clone $this->periodBase())->where('state', Ticket::STATE_REJECTED)->count();
        $total = $resolved + $rejected;

        $bands = [
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $resolved, 'color' => '#16a34a', 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $rejected, 'color' => '#ef4444', 'tone' => 'high'],
        ];

        $cursor = 0.0;
        $segments = [];
        foreach ($bands as $band) {
            $percent = $total > 0 ? round($band['count'] / $total * 100, 1) : 0.0;
            $start = $cursor;
            $cursor += $percent;
            $segments[] = [...$band, 'percent' => $percent, 'start' => round($start, 1), 'end' => round($cursor, 1)];
        }

        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * Average resolution time over the technician's resolved tickets in the
     * period. Computed in PHP minutes (not truncated hours) for portability.
     *
     * @return array{label: string, has_data: bool}
     */
    private function averageResolution(): array
    {
        $minutes = (clone $this->periodBase())
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->limit(self::AVG_POOL_LIMIT)
            ->get(['created_at', 'resolved_at'])
            ->map(fn (Ticket $t): ?float => $t->created_at !== null && $t->resolved_at !== null
                ? (float) $t->created_at->diffInMinutes($t->resolved_at)
                : null)
            ->filter(fn (?float $v): bool => $v !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return ['label' => 'Sin datos', 'has_data' => false];
        }

        $average = (int) round(((float) $minutes->sum()) / $minutes->count());

        return ['label' => $this->formatDuration($average), 'has_data' => true];
    }

    /**
     * Labs with the most resolved tickets (own, in period). No N+1: one grouped
     * count query + one names query.
     *
     * @return array{peak: int, items: list<array{name: string, count: int}>}
     */
    private function topLabs(): array
    {
        $counts = (clone $this->periodBase())
            ->where('state', Ticket::STATE_RESOLVED)
            ->selectRaw('location_id, COUNT(*) as aggregate')
            ->groupBy('location_id')
            ->orderByDesc('aggregate')
            ->limit(self::LABS_LIMIT)
            ->pluck('aggregate', 'location_id');

        if ($counts->isEmpty()) {
            return ['peak' => 0, 'items' => []];
        }

        $names = Location::query()
            ->whereIn('id', $counts->keys()->all())
            ->pluck('name', 'id');

        $items = $counts
            ->map(fn ($total, $locationId): array => [
                'name' => (string) $names->get((string) $locationId, 'Ubicación eliminada'),
                'count' => (int) $total,
            ])
            ->values()
            ->all();

        return ['peak' => (int) $counts->max(), 'items' => $items];
    }

    /**
     * Recent history activity: state_history rows of the technician's OWN
     * tickets that reached resolved/rejected. Scoped via the ticket relation,
     * so it never leaks another technician's history.
     *
     * @return list<array{ref: string, id: string, text: string, tone: string, icon: string, actor: string, at: string}>
     */
    private function activity(): array
    {
        $userId = $this->userId();
        $from = $this->parseDate((string) ($this->filters['from'] ?? ''));
        $to = $this->parseDate((string) ($this->filters['to'] ?? ''));

        return StateHistory::query()
            ->whereIn('to_state', self::HISTORY_STATES)
            ->whereHas('ticket', fn (Builder $q) => $q->where('assigned_to', $userId))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from?->startOfDay()))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to?->endOfDay()))
            ->with(['changedBy', 'ticket'])
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(function (StateHistory $h): array {
                $resolved = (string) $h->to_state === Ticket::STATE_RESOLVED;
                $id = (string) $h->ticket_id;

                return [
                    'ref' => $this->boardReference($id),
                    'id' => $id,
                    'text' => $resolved ? 'resuelto' : 'rechazado',
                    'tone' => $resolved ? 'success' : 'high',
                    'icon' => $resolved ? 'circle-check' : 'x',
                    'actor' => $this->displayName($h->changedBy),
                    'at' => $h->created_at?->format('d/m/Y h:i A') ?? '—',
                ];
            })
            ->all();
    }

    // ── Shaping ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(Ticket $ticket): array
    {
        $state = (string) $ticket->state;
        $resultDate = $state === Ticket::STATE_RESOLVED
            ? $ticket->resolved_at
            : ($this->rejectionTimes[(string) $ticket->id] ?? $ticket->updated_at);

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'location' => $ticket->location?->name ?? 'Sin ubicación',
            'category' => $ticket->category?->name ?? 'Sin categoría',
            'type' => $ticket->location?->room_code ?? '',
            'icon' => $this->iconFor($ticket->category?->name),
            'status' => $state,
            'status_label' => $this->stateLabel($state),
            'priority' => $this->priorityTone((string) $ticket->priority),
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'created' => $ticket->created_at?->format('d/m/Y') ?? '—',
            'result' => $resultDate?->format('d/m/Y') ?? '—',
        ];
    }

    // ── Pagination ───────────────────────────────────────────────

    private function currentPage(int $total): int
    {
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $requested = (int) ($this->filters['page'] ?? 1);

        return max(1, min($requested, $last));
    }

    /**
     * @return array{from: int, to: int, total: int, current: int, last: int, pages: list<int>}
     */
    private function pagination(int $page, int $shown, int $total): array
    {
        return $this->buildPagination($page, $shown, $total, self::PER_PAGE);
    }

    // ── Small helpers ────────────────────────────────────────────

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_RESOLVED => 'Resuelto',
            Ticket::STATE_REJECTED => 'Rechazado',
            default => ucfirst($state),
        };
    }

    private function userId(): string
    {
        return (string) $this->user->id;
    }
}
