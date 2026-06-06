<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tickets\TicketDifficultyScore;
use App\ViewModels\Tickets\MaintenanceBoardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Builds the maintenance "Tickets V2" board for a single technician.
 *
 * Security contract (mirrors TicketIndexQuery / MaintenanceDashboardQuery):
 * the role scope is applied FIRST and unconditionally. The technician only
 * ever sees tickets assigned to them OR open tickets available to claim;
 * user-supplied filters are applied AFTER that boundary.
 *
 * Portability: counts use COUNT()/GROUP BY and durations are computed in PHP,
 * so the same code runs on SQLite (tests) and PostgreSQL.
 */
final class MaintenanceBoardQuery
{
    /** @var list<string> */
    public const VIEWS = ['mine', 'available', 'all'];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    /** Rows eager-loaded and scored in PHP for the prioritised queue. */
    private const SCORE_POOL_LIMIT = 80;

    /** Tickets shown in the center column. */
    private const QUEUE_LIMIT = 20;

    private const LABS_LIMIT = 5;

    /** Resolved tickets sampled to compute the average attention time. */
    private const AVG_POOL_LIMIT = 100;

    /** @var list<string> */
    private const URGENT_PRIORITIES = ['critical', 'high'];

    /**
     * @param  array<string, mixed>  $filters  Validated ListTicketsRequest filters.
     * @param  string  $view  One of self::VIEWS.
     */
    public function __construct(
        private readonly User $user,
        private readonly array $filters,
        private readonly string $view,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function for(User $user, array $filters, string $view): MaintenanceBoardViewModel
    {
        return (new self($user, $filters, $view))->build();
    }

    public function build(): MaintenanceBoardViewModel
    {
        return new MaintenanceBoardViewModel(
            technicianId: $this->userId(),
            view: $this->view,
            filters: $this->filters,
            queue: $this->queue(),
            queueTotal: $this->scopedForView()->count(),
            summary: $this->summary(),
            chipCounts: $this->chipCounts(),
            insights: $this->insights(),
        );
    }

    // ── Base scopes ──────────────────────────────────────────────

    /**
     * Everything the technician may see: assigned to them OR claimable.
     *
     * @return Builder<Ticket>
     */
    private function visibleBase(): Builder
    {
        return Ticket::query()->visibleToMaintenance($this->userId());
    }

    /**
     * Open, unassigned, unlocked tickets in the global claim queue.
     *
     * @return Builder<Ticket>
     */
    private function availableBase(): Builder
    {
        return Ticket::query()->availableForClaim();
    }

    /**
     * Tickets assigned to the technician (any state).
     *
     * @return Builder<Ticket>
     */
    private function mineBase(): Builder
    {
        return Ticket::query()->where('assigned_to', $this->userId());
    }

    /**
     * Base for the active tab, with user-supplied filters applied.
     *
     * @return Builder<Ticket>
     */
    private function scopedForView(): Builder
    {
        $query = match ($this->view) {
            'mine' => $this->mineBase(),
            'available' => $this->availableBase(),
            default => $this->visibleBase(),
        };

        $this->applyFilters($query);

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applyFilters(Builder $query): void
    {
        // 'available' already forces state = open; a conflicting state filter
        // would only empty the list, so it is intentionally skipped there.
        if ($this->view !== 'available' && ! empty($this->filters['state'])) {
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

        if (! empty($this->filters['from'])) {
            $query->whereDate('created_at', '>=', $this->filters['from']);
        }

        if (! empty($this->filters['to'])) {
            $query->whereDate('created_at', '<=', $this->filters['to']);
        }

        if (! empty($this->filters['duplicates'])) {
            $query->whereHas('embedding', function (Builder $inner): void {
                /** @phpstan-ignore-next-line scope is dynamic */
                $inner->effectiveDuplicates();
            });
        }
    }

    // ── Center column: prioritised queue ─────────────────────────

    /**
     * @return Collection<int, Ticket>
     */
    private function queue(): Collection
    {
        $pool = $this->scopedForView()
            ->with(['location', 'category', 'assignee', 'embedding.matchedTicket'])
            ->withCount('media')
            ->orderByRaw(self::PRIORITY_ORDER)
            ->oldest('created_at')
            ->limit(self::SCORE_POOL_LIMIT)
            ->get();

        $recurrence = $this->recurrenceMap($pool);

        return $pool
            ->sortByDesc(fn (Ticket $ticket): int => $this->scoreFor($ticket, $recurrence))
            ->take(self::QUEUE_LIMIT)
            ->values();
    }

    // ── Stat cards ───────────────────────────────────────────────

    /**
     * @return array{visible: int, in_progress: int, mine: int, available: int}
     */
    private function summary(): array
    {
        return [
            'visible' => $this->visibleBase()->count(),
            'in_progress' => $this->visibleBase()->where('state', Ticket::STATE_IN_PROGRESS)->count(),
            'mine' => $this->mineBase()->count(),
            'available' => $this->availableBase()->count(),
        ];
    }

    // ── Quick chips ──────────────────────────────────────────────

    /**
     * @return array{all: int, in_progress: int, high: int, available: int, duplicates: int}
     */
    private function chipCounts(): array
    {
        return [
            'all' => $this->visibleBase()->count(),
            'in_progress' => $this->visibleBase()->where('state', Ticket::STATE_IN_PROGRESS)->count(),
            'high' => $this->visibleBase()->whereIn('priority', self::URGENT_PRIORITIES)->count(),
            'available' => $this->availableBase()->count(),
            'duplicates' => $this->duplicatesCount(),
        ];
    }

    // ── Right rail: insights ─────────────────────────────────────

    /**
     * @return array{
     *     priority: array{total: int, bands: list<array{key: string, label: string, count: int, percent: int}>},
     *     labs: array{peak: int, items: list<array{name: string, count: int}>},
     *     states: array{open: int, in_progress: int, resolved_today: int, rejected: int},
     *     duplicates: int,
     *     avg_time: array{label: string, has_data: bool}
     * }
     */
    private function insights(): array
    {
        return [
            'priority' => $this->priorityBreakdown(),
            'labs' => $this->labsActivity(),
            'states' => $this->stateBreakdown(),
            'duplicates' => $this->duplicatesCount(),
            'avg_time' => $this->averageAttentionTime(),
        ];
    }

    /**
     * @return array{total: int, bands: list<array{key: string, label: string, count: int, percent: int}>}
     */
    private function priorityBreakdown(): array
    {
        $byPriority = $this->visibleBase()
            ->selectRaw('priority, COUNT(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');

        $alta = (int) $byPriority->get('critical', 0) + (int) $byPriority->get('high', 0);
        $media = (int) $byPriority->get('medium', 0);
        $baja = (int) $byPriority->get('low', 0);
        $total = $alta + $media + $baja;

        $percent = static fn (int $count): int => $total > 0 ? (int) round($count / $total * 100) : 0;

        return [
            'total' => $total,
            'bands' => [
                ['key' => 'high', 'label' => 'Alta', 'count' => $alta, 'percent' => $percent($alta)],
                ['key' => 'medium', 'label' => 'Media', 'count' => $media, 'percent' => $percent($media)],
                ['key' => 'low', 'label' => 'Baja', 'count' => $baja, 'percent' => $percent($baja)],
            ],
        ];
    }

    /**
     * @return array{peak: int, items: list<array{name: string, count: int}>}
     */
    private function labsActivity(): array
    {
        $counts = $this->visibleBase()
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

        return [
            'peak' => (int) $counts->max(),
            'items' => $items,
        ];
    }

    /**
     * @return array{open: int, in_progress: int, resolved_today: int, rejected: int}
     */
    private function stateBreakdown(): array
    {
        $byState = $this->visibleBase()
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $resolvedToday = $this->mineBase()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereDate('resolved_at', CarbonImmutable::today())
            ->count();

        return [
            'open' => (int) $byState->get(Ticket::STATE_OPEN, 0),
            'in_progress' => (int) $byState->get(Ticket::STATE_IN_PROGRESS, 0),
            'resolved_today' => $resolvedToday,
            'rejected' => (int) $byState->get(Ticket::STATE_REJECTED, 0),
        ];
    }

    private function duplicatesCount(): int
    {
        return $this->visibleBase()
            ->whereHas('embedding', function (Builder $inner): void {
                /** @phpstan-ignore-next-line scope is dynamic */
                $inner->effectiveDuplicates();
            })
            ->count();
    }

    /**
     * @return array{label: string, has_data: bool}
     */
    private function averageAttentionTime(): array
    {
        $minutes = $this->mineBase()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->latest('resolved_at')
            ->limit(self::AVG_POOL_LIMIT)
            ->get(['created_at', 'resolved_at'])
            ->map(fn (Ticket $ticket): ?float => $ticket->created_at !== null && $ticket->resolved_at !== null
                ? (float) $ticket->created_at->diffInMinutes($ticket->resolved_at)
                : null)
            ->filter(fn (?float $value): bool => $value !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return ['label' => 'Sin datos', 'has_data' => false];
        }

        $average = (int) round(((float) $minutes->sum()) / $minutes->count());

        return ['label' => $this->formatDuration($average), 'has_data' => true];
    }

    // ── Difficulty scoring (shared with the dashboard) ───────────

    /**
     * @param  EloquentCollection<int, Ticket>  $tickets
     * @return array<string, int>
     */
    private function recurrenceMap(EloquentCollection $tickets): array
    {
        if ($tickets->isEmpty()) {
            return [];
        }

        $locationIds = $tickets->pluck('location_id')->filter()->unique()->values()->all();
        $categoryIds = $tickets->pluck('category_id')->filter()->unique()->values()->all();

        if ($locationIds === [] || $categoryIds === []) {
            return [];
        }

        $map = [];

        LocationIncidentHistory::query()
            ->whereIn('location_id', $locationIds)
            ->whereIn('category_id', $categoryIds)
            ->get(['location_id', 'category_id', 'recurrence_count'])
            ->each(function (LocationIncidentHistory $history) use (&$map): void {
                $map[$history->location_id.'|'.$history->category_id] = (int) $history->recurrence_count;
            });

        return $map;
    }

    /**
     * @param  array<string, int>  $recurrence
     */
    private function scoreFor(Ticket $ticket, array $recurrence): int
    {
        $daysOpen = $ticket->created_at !== null
            ? (int) floor($ticket->created_at->diffInDays(CarbonImmutable::now()))
            : 0;

        $embedding = $ticket->embedding;

        return TicketDifficultyScore::calculate(
            (string) $ticket->priority,
            $daysOpen,
            $recurrence[$ticket->location_id.'|'.$ticket->category_id] ?? 0,
            $embedding !== null && $embedding->effective_duplicate,
            ((int) $ticket->getAttribute('media_count')) > 0,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours > 0 && $rest > 0) {
            return "{$hours}h {$rest}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$rest}m";
    }

    private function userId(): string
    {
        return (string) $this->user->id;
    }
}
