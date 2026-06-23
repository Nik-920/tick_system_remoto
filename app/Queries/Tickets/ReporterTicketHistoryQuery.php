<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\Concerns\TicketBoardHelpers;
use App\Support\LocalTime;
use App\ViewModels\Tickets\ReporterTicketHistoryViewModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the reporter "Historial" board for a single reporter.
 *
 * Security contract (mirrors the maintenance HistoryBoardQuery, but scoped to
 * the REPORTER): the ownership scope is applied FIRST and unconditionally — the
 * reporter only ever sees their OWN closed-out tickets (reporter_id = them,
 * state in resolved/rejected). The result chip, search, filters, sort, date
 * range and page are all narrowed INSIDE that boundary, so no request parameter
 * can widen what a reporter may read.
 *
 * Domain note: a ticket is "closed out" when resolved, rejected, OR cancelled
 * (the reporter's own voluntary withdrawal — distinct from rejected). History
 * therefore = resolved + rejected + cancelled, with a "Cancelados" chip. There
 * is no rejected_at/cancelled_at column, so the portable "closed at" used for the
 * date filter / ordering is COALESCE(resolved_at, updated_at); the precise
 * rejection/cancellation moment per row comes from state_history.
 *
 * Portability: counts use COUNT()/GROUP BY and durations/months are computed in
 * PHP, so the same code runs on SQLite (tests) and PostgreSQL.
 */
final class ReporterTicketHistoryQuery
{
    use TicketBoardHelpers;

    /** @var list<string> */
    public const RESULTS = ['all', 'resolved', 'rejected', 'cancelled'];

    /** @var list<string> */
    public const SORTS = ['recent', 'oldest', 'priority'];

    /** @var list<string> Final states that make up the reporter's history. */
    private const HISTORY_STATES = [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED, Ticket::STATE_CANCELLED];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    /** Portable "closed at" expression: resolved_at for resolved, else updated_at. */
    private const CLOSED_AT = 'COALESCE(resolved_at, updated_at)';

    /** Datetime format used for SQL bound parameters (portable SQLite/PostgreSQL). */
    private const SQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const PER_PAGE = 5;

    /** Resolved tickets sampled for the average + monthly chart. */
    private const POOL_LIMIT = 500;

    /** Months shown in the "resolved per month" chart. */
    private const MONTHS = 6;

    private const MONTH_ABBR = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** @var array<string, CarbonInterface> ticket_id → close moment (latest rejected/cancelled transition), filled by rows(). */
    private array $closeTimes = [];

    /**
     * @param  array<string, mixed>  $filters  search, priority, location_id, category_id, from, to, page
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
    public static function for(User $user, array $filters, string $result, string $sort): ReporterTicketHistoryViewModel
    {
        return (new self($user, $filters, $result, $sort))->build();
    }

    public function build(): ReporterTicketHistoryViewModel
    {
        $resolved = (clone $this->mineHistoryBase())->where('state', Ticket::STATE_RESOLVED)->count();
        $rejected = (clone $this->mineHistoryBase())->where('state', Ticket::STATE_REJECTED)->count();
        $cancelled = (clone $this->mineHistoryBase())->where('state', Ticket::STATE_CANCELLED)->count();

        $listTotal = $this->scopedForList()->count();
        $page = $this->currentPage($listTotal);
        $rows = $this->rows($page);

        $viewerReacted = $rows->isEmpty()
            ? []
            : CommunityReaction::query()
                ->whereIn('ticket_id', $rows->pluck('id')->all())
                ->where('user_id', $this->userId())
                ->where('type', CommunityReaction::TYPE_INTERESTED)
                ->pluck('ticket_id')
                ->flip()
                ->all();

        return new ReporterTicketHistoryViewModel(
            reporterId: $this->userId(),
            activeResult: $this->result,
            sort: $this->sort,
            filters: $this->filters,
            chips: $this->chips($resolved, $rejected, $cancelled),
            locations: Location::query()->active()->orderBy('name')->get(),
            categories: Category::query()->orderBy('name')->get(),
            tickets: $rows->map(fn (Ticket $t): array => $this->shapeRow($t, isset($viewerReacted[(string) $t->id])))->all(),
            pagination: $this->pagination($page, $rows->count(), $listTotal),
            summary: $this->summary($resolved, $rejected, $cancelled),
        );
    }

    // ── Security boundary ────────────────────────────────────────

    /**
     * The ownership scope: ONLY this reporter's closed-out tickets.
     *
     * @return Builder<Ticket>
     */
    private function mineHistoryBase(): Builder
    {
        return Ticket::query()
            ->where('reporter_id', $this->userId())
            ->whereIn('state', self::HISTORY_STATES);
    }

    /**
     * Mine-history + result chip + user filters, ordered by the chosen sort.
     *
     * @return Builder<Ticket>
     */
    private function scopedForList(): Builder
    {
        $query = $this->mineHistoryBase();

        if (in_array($this->result, [Ticket::STATE_RESOLVED, Ticket::STATE_REJECTED, Ticket::STATE_CANCELLED], true)) {
            $query->where('state', $this->result);
        }

        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
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

        $from = $this->parseDate((string) ($this->filters['from'] ?? ''));
        $to = $this->parseDate((string) ($this->filters['to'] ?? ''));
        if ($from !== null) {
            $query->whereRaw(self::CLOSED_AT.' >= ?', [$from->startOfDay()->format(self::SQL_DATETIME_FORMAT)]);
        }
        if ($to !== null) {
            $query->whereRaw(self::CLOSED_AT.' <= ?', [$to->endOfDay()->format(self::SQL_DATETIME_FORMAT)]);
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
            ->withCount([
                'communityReactions as reactions_count' => fn ($q) => $q->where('type', CommunityReaction::TYPE_INTERESTED),
                'communityComments as comments_count' => fn ($q) => $q->where('status', CommunityComment::STATUS_VISIBLE),
            ])
            ->forPage($page, self::PER_PAGE)
            ->get();

        // Precise close moment per row from state_history: rejected and cancelled
        // both lack a dedicated timestamp column, so we read their transition time.
        $this->closeTimes = $this->latestTransitionTimes(
            Ticket::STATE_REJECTED,
            $rows->where('state', Ticket::STATE_REJECTED)->pluck('id')->all()
        ) + $this->latestTransitionTimes(
            Ticket::STATE_CANCELLED,
            $rows->where('state', Ticket::STATE_CANCELLED)->pluck('id')->all()
        );

        return $rows;
    }

    /**
     * One grouped query: latest timestamp of a given transition per ticket (no N+1).
     *
     * @param  list<string>  $ids
     * @return array<string, CarbonInterface>
     */
    private function latestTransitionTimes(string $toState, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return StateHistory::query()
            ->whereIn('ticket_id', $ids)
            ->where('to_state', $toState)
            ->selectRaw('ticket_id, MAX(created_at) as closed_at')
            ->groupBy('ticket_id')
            ->get()
            ->mapWithKeys(fn (StateHistory $h): array => [
                (string) $h->ticket_id => Carbon::parse((string) $h->getAttribute('closed_at')),
            ])
            ->all();
    }

    // ── Chips (mine-history only — stable across the active chip) ─

    /**
     * @return list<array{key: string, label: string, count: int, tone: string, active: bool}>
     */
    private function chips(int $resolved, int $rejected, int $cancelled): array
    {
        $chips = [
            ['key' => 'all', 'label' => 'Todos', 'count' => $resolved + $rejected + $cancelled, 'tone' => 'neutral'],
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $resolved, 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $rejected, 'tone' => 'high'],
            ['key' => 'cancelled', 'label' => 'Cancelados', 'count' => $cancelled, 'tone' => 'neutral'],
        ];

        return array_map(function (array $chip): array {
            $chip['active'] = $chip['key'] === $this->result || ($chip['key'] === 'all' && $this->result === 'all');

            return $chip;
        }, $chips);
    }

    // ── Right rail analytics (mine-history only) ─────────────────

    /**
     * @return array{
     *     total: int,
     *     donut: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>,
     *     avg_value: string, avg_note: string, avg_has_data: bool,
     *     monthly: array{peak: int, has_data: bool, items: list<array{label: string, count: int}>}
     * }
     */
    private function summary(int $resolved, int $rejected, int $cancelled): array
    {
        $avg = $this->averageResolution();

        return [
            'total' => $resolved + $rejected + $cancelled,
            'donut' => $this->donut($resolved, $rejected, $cancelled),
            'avg_value' => $avg['label'],
            'avg_note' => $avg['has_data'] ? 'Sobre tus tickets resueltos' : 'Aún no tienes tickets resueltos',
            'avg_has_data' => $avg['has_data'],
            'monthly' => $this->monthly(),
        ];
    }

    /**
     * Donut by final result: resolved vs rejected vs cancelled, with cumulative
     * percents the view turns into a conic-gradient + a text legend (never
     * colour-only).
     *
     * @return list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>
     */
    private function donut(int $resolved, int $rejected, int $cancelled): array
    {
        $total = $resolved + $rejected + $cancelled;
        $bands = [
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $resolved, 'color' => '#16a34a', 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $rejected, 'color' => '#ef4444', 'tone' => 'high'],
            ['key' => 'cancelled', 'label' => 'Cancelados', 'count' => $cancelled, 'color' => '#64748b', 'tone' => 'neutral'],
        ];

        $cursor = 0.0;
        $segments = [];
        foreach ($bands as $band) {
            $percent = $total > 0 ? (int) round($band['count'] / $total * 100) : 0;
            $start = $cursor;
            $cursor += $total > 0 ? $band['count'] / $total * 100 : 0;
            $segments[] = [...$band, 'percent' => $percent, 'start' => round($start, 2), 'end' => round($cursor, 2)];
        }

        return $segments;
    }

    /**
     * Average resolution time over the reporter's resolved tickets, computed in
     * PHP minutes (not truncated hours). Honest "Sin datos" when none.
     *
     * @return array{label: string, has_data: bool}
     */
    private function averageResolution(): array
    {
        $minutes = (clone $this->mineHistoryBase())
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->limit(self::POOL_LIMIT)
            ->get(['created_at', 'resolved_at'])
            ->map(fn (Ticket $t): ?float => $t->created_at !== null && $t->resolved_at !== null
                ? abs((float) $t->created_at->diffInMinutes($t->resolved_at))
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
     * Resolved tickets bucketed by month over the last MONTHS months (PHP
     * bucketing, portable). `has_data` is false when no resolved ticket falls in
     * the window, so the view can show an honest placeholder.
     *
     * @return array{peak: int, has_data: bool, items: list<array{label: string, count: int}>}
     */
    private function monthly(): array
    {
        $now = CarbonImmutable::now()->startOfMonth();

        // Seed an ordered bucket per month (oldest → newest), all zero.
        $buckets = [];
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $month = $now->subMonths($i);
            $buckets[$month->format('Y-m')] = ['label' => self::MONTH_ABBR[$month->month - 1], 'count' => 0];
        }

        $oldest = $now->subMonths(self::MONTHS - 1)->startOfMonth();

        $resolvedAt = (clone $this->mineHistoryBase())
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $oldest->format(self::SQL_DATETIME_FORMAT))
            ->limit(self::POOL_LIMIT)
            ->pluck('resolved_at');

        foreach ($resolvedAt as $date) {
            if ($date instanceof CarbonInterface) {
                $key = $date->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            }
        }

        $items = array_values($buckets);
        $peak = (int) max(1, ...array_map(fn (array $b): int => $b['count'], $items));
        $hasData = array_sum(array_map(fn (array $b): int => $b['count'], $items)) > 0;

        return ['peak' => $peak, 'has_data' => $hasData, 'items' => $items];
    }

    // ── Row shaping ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(Ticket $ticket, bool $viewerReacted = false): array
    {
        $state = (string) $ticket->state;
        // Resolved uses resolved_at; rejected/cancelled use their state_history
        // transition moment (fallback updated_at).
        $closedAt = $state === Ticket::STATE_RESOLVED
            ? $ticket->resolved_at
            : ($this->closeTimes[(string) $ticket->id] ?? $ticket->updated_at);

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'location' => $ticket->location?->name ?? 'Sin ubicación',
            'category' => $ticket->category?->name ?? 'Sin categoría',
            'type' => (string) ($ticket->location?->room_code ?? ''),
            'icon' => $this->iconFor($ticket->category?->name),
            'status' => $state,
            'status_label' => $this->stateLabel($state),
            'status_icon' => $this->stateIcon($state),
            'status_tone' => match ($state) {
                Ticket::STATE_RESOLVED => 'success',
                Ticket::STATE_REJECTED => 'high',
                default => 'neutral',
            },
            'priority' => $this->priorityTone((string) $ticket->priority),
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'created' => LocalTime::format($ticket->created_at, 'd/m/Y') ?? '—',
            'closed' => LocalTime::format($closedAt, 'd/m/Y') ?? '—',
            'reactions_count' => (int) ($ticket->reactions_count ?? 0),
            'comments_count' => (int) ($ticket->comments_count ?? 0),
            'viewer_reacted' => $viewerReacted,
            'reaction_store_url' => route('reporter.community.reactions.store', $ticket->id),
            'reaction_destroy_url' => route('reporter.community.reactions.destroy', ['ticket' => $ticket->id, 'type' => CommunityReaction::TYPE_INTERESTED]),
            'comments_url' => route('reporter.tickets.comments.index', $ticket->id),
            'comments_store_url' => route('reporter.tickets.comments.store', $ticket->id),
        ];
    }

    private function stateIcon(string $state): string
    {
        return match ($state) {
            Ticket::STATE_RESOLVED => 'check-circle',
            Ticket::STATE_REJECTED => 'x-circle',
            Ticket::STATE_CANCELLED => 'minus-circle',
            default => 'circle',
        };
    }

    // ── Pagination ───────────────────────────────────────────────

    private function currentPage(int $total): int
    {
        $last = max(1, (int) ceil($total / self::PER_PAGE));

        return max(1, min((int) ($this->filters['page'] ?? 1), $last));
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
            Ticket::STATE_CANCELLED => 'Cancelado',
            default => ucfirst($state),
        };
    }

    private function userId(): string
    {
        return (string) $this->user->id;
    }
}
