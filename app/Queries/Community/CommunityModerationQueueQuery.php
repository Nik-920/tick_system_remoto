<?php

declare(strict_types=1);

namespace App\Queries\Community;

use App\Models\Category;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\Concerns\TicketBoardHelpers;
use App\Support\LocalTime;
use App\ViewModels\Community\CommunityModerationQueueViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Builds the admin community moderation queue.
 *
 * Security contract:
 * - Only called from routes gated by role:admin|super_admin middleware.
 * - Does NOT load reporter_id, assigned_to, or assigned_by.
 * - communityHiddenBy (the moderating admin) IS loaded — this is admin-only data.
 * - Returned ViewModel contains pre-mapped arrays, not raw Eloquent models.
 */
final class CommunityModerationQueueQuery
{
    use TicketBoardHelpers;

    private const PER_PAGE = 20;

    /** @var list<string> */
    private const ALL_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
        Ticket::STATE_CANCELLED,
        Ticket::STATE_REJECTED,
    ];

    /** @var list<string> */
    private const PUBLIC_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    /** @var list<string> */
    private const PERIODS = ['24h', '7d', '30d'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function forAdmin(User $admin, array $filters): CommunityModerationQueueViewModel
    {
        unset($admin);

        $base = $this->baseQuery($filters);

        $paginator = $base
            ->with([
                'category:id,name',
                'location:id,name,building,floor,room_code',
                'communityHiddenBy:id,name,last_name',
            ])
            ->paginate(self::PER_PAGE, [
                'id', 'title', 'state', 'priority',
                'location_id', 'category_id',
                'community_visible', 'community_hidden_at',
                'community_hidden_by', 'community_visibility_reason',
                'created_at', 'updated_at',
            ])
            ->withQueryString();

        /** @var list<string> $ticketIds */
        $ticketIds = collect($paginator->items())->pluck('id')->all();
        [$pendingCountMap, $latestReportMap] = $this->batchPendingReportData($ticketIds);

        $items = [];
        foreach ($paginator->items() as $ticket) {
            $tid = (string) $ticket->id;
            $items[] = $this->toItem(
                $ticket,
                (int) ($pendingCountMap[$tid] ?? 0),
                $latestReportMap[$tid] ?? null,
            );
        }

        return new CommunityModerationQueueViewModel(
            items: $items,
            filters: $this->normalizeFilters($filters),
            paginator: $paginator,
            summary: $this->computeSummary(),
            categories: $this->categoriesForFilter(),
            buildings: $this->buildings(),
            states: $this->statesForFilter(),
            visibilityOptions: $this->visibilityOptions(),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Ticket::query()->orderByDesc('updated_at');

        $this->applyVisibility($query, $filters);
        $this->applySearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applyBuilding($query, $filters);
        $this->applyState($query, $filters);
        $this->applyPeriod($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyVisibility(Builder $query, array $filters): void
    {
        $visibility = trim((string) ($filters['visibility'] ?? 'all'));

        if ($visibility === 'visible') {
            $query->whereRaw('"community_visible" IS TRUE');
        } elseif ($visibility === 'hidden') {
            $query->whereRaw('"community_visible" IS FALSE');
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhereHas('location', fn (Builder $l) => $l
                    ->where('name', 'like', $like)
                    ->orWhere('building', 'like', $like)
                    ->orWhere('room_code', 'like', $like))
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like));
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCategory(Builder $query, array $filters): void
    {
        $categoryId = trim((string) ($filters['category'] ?? ''));
        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyBuilding(Builder $query, array $filters): void
    {
        $building = trim((string) ($filters['building'] ?? ''));
        if ($building !== '') {
            $query->whereHas('location', fn (Builder $l) => $l->where('building', $building));
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyState(Builder $query, array $filters): void
    {
        $state = trim((string) ($filters['state'] ?? ''));
        if ($state !== '' && in_array($state, self::ALL_STATES, true)) {
            $query->where('state', $state);
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyPeriod(Builder $query, array $filters): void
    {
        $period = trim((string) ($filters['period'] ?? ''));
        if (! in_array($period, self::PERIODS, true)) {
            return;
        }

        $cutoff = match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
        };

        $query->where('created_at', '>=', $cutoff);
    }

    /**
     * @return array{id: string, ref: string, title: string, state: string, state_label: string, priority: string, priority_label: string, community_visible: bool, state_blocks_feed: bool, community_visibility_reason: string|null, community_hidden_at: string|null, hidden_by_name: string|null, location: array{name: string, building: string, room_code: string}|null, category: array{name: string}|null, show_url: string, created_at: string, updated_at: string, pending_reports_count: int, latest_pending_report: array{id: string, target_label: string, reason_label: string, note: string|null, comment_excerpt: string|null}|null}
     */
    private function toItem(Ticket $ticket, int $pendingReportsCount = 0, ?CommunityReport $latestPendingReport = null): array
    {
        $state = (string) $ticket->state;

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'priority' => (string) $ticket->priority,
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'community_visible' => (bool) $ticket->community_visible,
            'state_blocks_feed' => ! in_array($state, self::PUBLIC_STATES, true),
            'community_visibility_reason' => $ticket->community_visibility_reason !== null
                ? (string) $ticket->community_visibility_reason
                : null,
            'community_hidden_at' => LocalTime::format($ticket->community_hidden_at),
            'hidden_by_name' => $ticket->community_hidden_by !== null
                ? $this->displayName($ticket->communityHiddenBy)
                : null,
            'location' => $ticket->location !== null ? [
                'name' => (string) $ticket->location->name,
                'building' => (string) $ticket->location->building,
                'room_code' => (string) $ticket->location->room_code,
            ] : null,
            'category' => $ticket->category !== null ? [
                'name' => (string) $ticket->category->name,
            ] : null,
            'show_url' => route('tickets.show', $ticket->id),
            'created_at' => LocalTime::format($ticket->created_at, 'd/m/Y') ?? '',
            'updated_at' => LocalTime::format($ticket->updated_at, 'd/m/Y') ?? '',
            'pending_reports_count' => $pendingReportsCount,
            'latest_pending_report' => $latestPendingReport !== null ? [
                'id' => (string) $latestPendingReport->id,
                'target_label' => $latestPendingReport->targetLabel(),
                'reason_label' => CommunityReport::reasonLabel((string) $latestPendingReport->reason),
                'note' => $latestPendingReport->note !== null
                    ? Str::limit((string) $latestPendingReport->note, 100)
                    : null,
                'comment_excerpt' => $latestPendingReport->comment_id !== null && $latestPendingReport->comment !== null
                    ? Str::limit((string) $latestPendingReport->comment->body, 80)
                    : null,
            ] : null,
        ];
    }

    /**
     * Batch-load pending report counts and latest pending report per ticket.
     * Includes comment_id so toItem can show the target type and excerpt.
     *
     * @param  list<string>  $ticketIds
     * @return array{0: array<string, int>, 1: array<string, CommunityReport>}
     */
    private function batchPendingReportData(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [[], []];
        }

        $pendingCountMap = CommunityReport::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->selectRaw('ticket_id, COUNT(*) as cnt')
            ->groupBy('ticket_id')
            ->pluck('cnt', 'ticket_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        $latestReportMap = CommunityReport::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->select(['id', 'ticket_id', 'comment_id', 'reason', 'note'])
            ->with(['comment:id,body'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->first())
            ->all();

        return [$pendingCountMap, $latestReportMap];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        $visibility = trim((string) ($filters['visibility'] ?? ''));

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'visibility' => in_array($visibility, ['visible', 'hidden', 'all'], true) ? $visibility : 'all',
            'state' => in_array(trim((string) ($filters['state'] ?? '')), self::ALL_STATES, true)
                ? trim((string) ($filters['state'] ?? ''))
                : '',
            'category' => trim((string) ($filters['category'] ?? '')),
            'building' => trim((string) ($filters['building'] ?? '')),
            'period' => in_array(trim((string) ($filters['period'] ?? '')), self::PERIODS, true)
                ? trim((string) ($filters['period'] ?? ''))
                : '',
        ];
    }

    /**
     * @return array{total_visible: int, total_hidden: int, hidden_last_7d: int, visible_high_priority: int, pending_reports: int}
     */
    private function computeSummary(): array
    {
        $totalVisible = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', self::PUBLIC_STATES)
            ->count();

        $totalHidden = Ticket::query()
            ->whereRaw('"community_visible" IS FALSE')
            ->count();

        $hiddenLast7d = Ticket::query()
            ->whereRaw('"community_visible" IS FALSE')
            ->whereNotNull('community_hidden_at')
            ->where('community_hidden_at', '>=', now()->subDays(7))
            ->count();

        $visibleHighPriority = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', self::PUBLIC_STATES)
            ->whereIn('priority', ['critical', 'high'])
            ->count();

        $pendingReports = CommunityReport::query()
            ->where('status', CommunityReport::STATUS_PENDING)
            ->count();

        return [
            'total_visible' => $totalVisible,
            'total_hidden' => $totalHidden,
            'hidden_last_7d' => $hiddenLast7d,
            'visible_high_priority' => $visibleHighPriority,
            'pending_reports' => $pendingReports,
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function categoriesForFilter(): array
    {
        return Category::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function buildings(): array
    {
        return Location::query()
            ->whereNotNull('building')
            ->where('building', '!=', '')
            ->distinct()
            ->orderBy('building')
            ->pluck('building')
            ->map(fn (mixed $b) => (string) $b)
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statesForFilter(): array
    {
        return [
            ['value' => 'open', 'label' => 'Abierto'],
            ['value' => 'in_progress', 'label' => 'En progreso'],
            ['value' => 'resolved', 'label' => 'Resuelto'],
            ['value' => 'cancelled', 'label' => 'Cancelado'],
            ['value' => 'rejected', 'label' => 'Rechazado'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function visibilityOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todos'],
            ['value' => 'visible', 'label' => 'Visible'],
            ['value' => 'hidden', 'label' => 'Oculto'],
        ];
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'open' => 'Abierto',
            'in_progress' => 'En progreso',
            'resolved' => 'Resuelto',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rechazado',
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }
}
