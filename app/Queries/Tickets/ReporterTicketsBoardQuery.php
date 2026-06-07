<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\ViewModels\Tickets\ReporterTicketsBoardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the reporter "Mis tickets" board for a single reporter.
 *
 * Security contract (mirrors the maintenance boards): the ownership scope is
 * applied FIRST and unconditionally — the reporter only ever sees tickets they
 * filed (reporter_id = them). The status chip, search, filters, sort, date range
 * and page are all narrowed INSIDE that boundary, so no request parameter can
 * widen what a reporter may read. This is the same boundary TicketIndexQuery
 * applies for the reporter role (Ticket::scopeReportedBy), re-expressed here as
 * an explicit, self-contained board query with chips/donut/labs analytics.
 *
 * Domain note: the lifecycle has exactly four states — open, in_progress,
 * resolved, rejected (no "review"/"closed"). The chips/donut use only those;
 * "Posible duplicado" is derived from the AI embedding (effectiveDuplicates).
 *
 * Portability: counts use COUNT()/GROUP BY and durations are computed in PHP,
 * so the same code runs on SQLite (tests) and PostgreSQL.
 */
final class ReporterTicketsBoardQuery
{
    /** @var list<string> */
    public const STATUSES = ['all', 'open', 'in_progress', 'resolved', 'rejected', 'duplicate'];

    /** @var list<string> */
    public const SORTS = ['recent', 'oldest', 'priority'];

    /** Statuses that map directly to a real ticket state column value. */
    private const STATE_STATUSES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
        Ticket::STATE_REJECTED,
    ];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    private const PER_PAGE = 5;

    private const LABS_LIMIT = 5;

    /** Tickets sampled to compute the average first-response time. */
    private const AVG_POOL_LIMIT = 200;

    /** Category name → Lucide icon (same vocabulary as the other boards). */
    private const CATEGORY_ICONS = [
        'hardware' => 'monitor', 'software' => 'cpu', 'seguridad' => 'shield-alert',
        'mobiliario' => 'armchair', 'equipos' => 'projector', 'conectividad' => 'cable',
        'redes' => 'cable', 'red' => 'cable', 'electricidad' => 'zap', 'servicios' => 'droplet',
    ];

    /**
     * @param  array<string, mixed>  $filters  search, priority, location_id, category_id, from, to, page
     */
    public function __construct(
        private readonly User $user,
        private readonly array $filters,
        private readonly string $status,
        private readonly string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function for(User $user, array $filters, string $status, string $sort): ReporterTicketsBoardViewModel
    {
        return (new self($user, $filters, $status, $sort))->build();
    }

    public function build(): ReporterTicketsBoardViewModel
    {
        $counts = $this->stateCounts();
        $duplicates = $this->duplicateCount();
        $total = array_sum($counts);

        $listTotal = $this->scopedForList()->count();
        $page = $this->currentPage($listTotal);
        $rows = $this->scopedForList()
            ->with(['location', 'category'])
            ->forPage($page, self::PER_PAGE)
            ->get();

        return new ReporterTicketsBoardViewModel(
            reporterId: $this->userId(),
            activeStatus: $this->status,
            sort: $this->sort,
            filters: $this->filters,
            chips: $this->chips($counts, $duplicates, $total),
            locations: Location::query()->active()->orderBy('name')->get(),
            categories: Category::query()->orderBy('name')->get(),
            tickets: $rows->map(fn (Ticket $t): array => $this->shapeRow($t))->all(),
            pagination: $this->pagination($page, $rows->count(), $listTotal),
            summary: $this->summary($counts, $total),
            donut: $this->donut($counts, $total),
            labs: $this->labs(),
            totalReported: $total,
        );
    }

    // ── Security boundary ────────────────────────────────────────

    /**
     * The ownership scope: ONLY tickets this reporter filed. Applied first by
     * every counter and the list, so nothing below can widen the boundary.
     *
     * @return Builder<Ticket>
     */
    private function mineBase(): Builder
    {
        return Ticket::query()->where('reporter_id', $this->userId());
    }

    /**
     * Mine + active status chip + user filters + sort. Drives the center list.
     *
     * @return Builder<Ticket>
     */
    private function scopedForList(): Builder
    {
        $query = $this->mineBase();
        $this->applyStatus($query);
        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applyStatus(Builder $query): void
    {
        if (in_array($this->status, self::STATE_STATUSES, true)) {
            $query->where('state', $this->status);

            return;
        }

        if ($this->status === 'duplicate') {
            $query->whereHas('embedding', function (Builder $q): void {
                /** @phpstan-ignore-next-line scope defined on TicketEmbedding */
                $q->effectiveDuplicates();
            });
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

        $from = $this->date((string) ($this->filters['from'] ?? ''));
        $to = $this->date((string) ($this->filters['to'] ?? ''));
        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
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
            'oldest' => $query->oldest('created_at'),
            'priority' => $query->orderByRaw(self::PRIORITY_ORDER)->latest('created_at'),
            default => $query->latest('updated_at'),
        };
    }

    // ── Counts (mine only — stable across the active chip) ────────

    /**
     * @return array<string, int> state => count, with every state present.
     */
    private function stateCounts(): array
    {
        $raw = $this->mineBase()
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $counts = [];
        foreach (self::STATE_STATUSES as $state) {
            $counts[$state] = (int) $raw->get($state, 0);
        }

        return $counts;
    }

    private function duplicateCount(): int
    {
        return $this->mineBase()
            ->whereHas('embedding', function (Builder $q): void {
                /** @phpstan-ignore-next-line scope defined on TicketEmbedding */
                $q->effectiveDuplicates();
            })
            ->count();
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int, tone: string, active: bool}>
     */
    private function chips(array $counts, int $duplicates, int $total): array
    {
        $chips = [
            ['key' => 'all', 'label' => 'Todos', 'count' => $total, 'tone' => 'neutral'],
            ['key' => 'open', 'label' => 'Abiertos', 'count' => $counts[Ticket::STATE_OPEN], 'tone' => 'purple'],
            ['key' => 'in_progress', 'label' => 'En progreso', 'count' => $counts[Ticket::STATE_IN_PROGRESS], 'tone' => 'primary'],
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $counts[Ticket::STATE_RESOLVED], 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $counts[Ticket::STATE_REJECTED], 'tone' => 'high'],
            ['key' => 'duplicate', 'label' => 'Posible duplicado', 'count' => $duplicates, 'tone' => 'info'],
        ];

        return array_map(function (array $chip): array {
            $chip['active'] = $chip['key'] === $this->status || ($chip['key'] === 'all' && $this->status === 'all');

            return $chip;
        }, $chips);
    }

    // ── Right rail analytics (mine only) ─────────────────────────

    /**
     * @param  array<string, int>  $counts
     * @return array{total: int, avg_value: string, avg_note: string, avg_has_data: bool, donut: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>}
     */
    private function summary(array $counts, int $total): array
    {
        $avg = $this->averageFirstResponse();
        $donut = $this->donut($counts, $total);

        return [
            'total' => $total,
            'avg_value' => $avg['label'],
            'avg_note' => $avg['has_data']
                ? 'Desde la creación hasta la primera atención'
                : 'Aún no hay tickets atendidos',
            'avg_has_data' => $avg['has_data'],
            'donut' => $donut['segments'],
        ];
    }

    /**
     * Donut by state with cumulative percents the view turns into a
     * conic-gradient + a text legend (never colour-only).
     *
     * @param  array<string, int>  $counts
     * @return array{total: int, segments: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>}
     */
    private function donut(array $counts, int $total): array
    {
        $bands = [
            ['key' => 'open', 'label' => 'Abiertos', 'count' => $counts[Ticket::STATE_OPEN], 'color' => '#9333ea', 'tone' => 'purple'],
            ['key' => 'in_progress', 'label' => 'En progreso', 'count' => $counts[Ticket::STATE_IN_PROGRESS], 'color' => '#2563eb', 'tone' => 'primary'],
            ['key' => 'resolved', 'label' => 'Resueltos', 'count' => $counts[Ticket::STATE_RESOLVED], 'color' => '#16a34a', 'tone' => 'success'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => $counts[Ticket::STATE_REJECTED], 'color' => '#ef4444', 'tone' => 'high'],
        ];

        $cursor = 0.0;
        $segments = [];
        foreach ($bands as $band) {
            $percent = $total > 0 ? (int) round($band['count'] / $total * 100) : 0;
            $start = $cursor;
            $cursor += $total > 0 ? $band['count'] / $total * 100 : 0;
            $segments[] = [...$band, 'percent' => $percent, 'start' => round($start, 2), 'end' => round($cursor, 2)];
        }

        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * Average first-response time = first transition to in_progress minus
     * created_at, averaged over the reporter's tickets that have been attended.
     * Computed in PHP minutes (portable). Honest "Sin datos" when none qualify.
     *
     * @return array{label: string, has_data: bool}
     */
    private function averageFirstResponse(): array
    {
        $tickets = $this->mineBase()
            ->latest('created_at')
            ->limit(self::AVG_POOL_LIMIT)
            ->get(['id', 'created_at']);

        if ($tickets->isEmpty()) {
            return ['label' => 'Sin datos', 'has_data' => false];
        }

        $firstAttended = StateHistory::query()
            ->whereIn('ticket_id', $tickets->pluck('id')->all())
            ->where('to_state', Ticket::STATE_IN_PROGRESS)
            ->selectRaw('ticket_id, MIN(created_at) as first_at')
            ->groupBy('ticket_id')
            ->pluck('first_at', 'ticket_id');

        $minutes = $tickets
            ->map(function (Ticket $t) use ($firstAttended): ?float {
                $first = $firstAttended->get((string) $t->id);
                if ($first === null || $t->created_at === null) {
                    return null;
                }

                return abs((float) $t->created_at->diffInMinutes(Carbon::parse((string) $first)));
            })
            ->filter(fn (?float $v): bool => $v !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return ['label' => 'Sin datos', 'has_data' => false];
        }

        $average = (int) round(((float) $minutes->sum()) / $minutes->count());

        return ['label' => $this->formatDuration($average), 'has_data' => true];
    }

    /**
     * Tickets grouped by lab (own only). No N+1: one grouped count + one names.
     *
     * @return array{peak: int, items: list<array{name: string, count: int}>}
     */
    private function labs(): array
    {
        $counts = $this->mineBase()
            ->whereNotNull('location_id')
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

    // ── Row shaping ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function shapeRow(Ticket $ticket): array
    {
        $state = (string) $ticket->state;
        $active = in_array($state, [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS], true);

        // Reporter row actions. The rule lives in TicketPolicy (single source of
        // truth); the view only reads these flags, never re-derives them. Both
        // resolve to: own + open + unassigned + unlocked. Edit/cancel are gated
        // here so the kebab is hidden the moment maintenance touches the ticket.
        $canEdit = $this->user->can('update', $ticket);
        $canCancel = $this->user->can('cancelAsReporter', $ticket);

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->reference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'location' => $ticket->location?->name ?? 'Sin ubicación',
            'category' => $ticket->category?->name ?? 'Sin categoría',
            'type' => (string) ($ticket->location?->room_code ?? ''),
            'icon' => $this->iconFor($ticket->category?->name),
            'status' => $state,
            'status_label' => $this->stateLabel($state),
            'status_tone' => $this->stateTone($state),
            'priority' => $this->priorityTone((string) $ticket->priority),
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'updated' => $ticket->updated_at?->diffForHumans() ?? '—',
            'action' => $active ? 'follow' : 'detail',
            'action_label' => $active ? 'Ver seguimiento' : 'Ver detalle',
            'can_edit' => $canEdit,
            'can_cancel' => $canCancel,
            'show_actions_menu' => $canEdit || $canCancel,
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
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $from = $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + 1;
        $to = $total === 0 ? 0 : $from + $shown - 1;

        $start = max(1, min($page - 2, $last - 4));
        $end = min($last, $start + 4);

        return [
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'current' => $page,
            'last' => $last,
            'pages' => array_values(range($start, $end)),
        ];
    }

    // ── Small helpers ────────────────────────────────────────────

    private function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $rest > 0 ? "{$hours}h {$rest}m" : "{$hours}h";
        }

        return "{$rest}m";
    }

    private function reference(string $id): string
    {
        return '#'.strtoupper(substr($id, 0, 8));
    }

    private function iconFor(?string $category): string
    {
        return self::CATEGORY_ICONS[strtolower(trim((string) $category))] ?? 'wrench';
    }

    private function stateTone(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'purple',
            Ticket::STATE_IN_PROGRESS => 'primary',
            Ticket::STATE_RESOLVED => 'success',
            Ticket::STATE_REJECTED => 'high',
            default => 'neutral',
        };
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

    private function priorityTone(string $priority): string
    {
        return match ($priority) {
            'critical', 'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
            default => ucfirst($priority),
        };
    }

    private function userId(): string
    {
        return (string) $this->user->id;
    }
}
