<?php

declare(strict_types=1);

namespace App\Queries\Community;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\Concerns\TicketBoardHelpers;
use App\ViewModels\Community\CommunityFeedViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds the public community feed for the reporter role.
 *
 * Security contract:
 * - Base query selects only safe ticket columns — reporter_id, assigned_to,
 *   and assigned_by are never loaded.
 * - Eager loading uses explicit column lists; user relations are not loaded.
 * - Returned ViewModel contains pre-mapped arrays, not raw Eloquent models.
 * - Reaction/save counts are aggregate only; no user identity is exposed.
 *
 * Visibility rule (community_visible column):
 * - community_visible=true AND state IN (open, in_progress, resolved) → public.
 * - community_visible=false → hidden regardless of state.
 * - cancelled / rejected → hidden regardless of community_visible.
 */
final class CommunityFeedQuery
{
    use TicketBoardHelpers;

    /** @var list<string> */
    private const PUBLIC_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    private const PER_PAGE = 10;

    private const MAX_SUMMARY = 140;

    private const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const PERIODS = ['24h', '7d', '30d'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function forReporter(User $viewer, array $filters): CommunityFeedViewModel
    {
        $base = $this->baseQuery($filters, $viewer);

        $paginator = $base
            ->with([
                'location:id,name,building,floor,room_code',
                'category:id,name,icon',
                'media' => fn ($q) => $q
                    ->select(['id', 'ticket_id', 'file_url', 'file_type', 'created_at'])
                    ->oldest('created_at'),
            ])
            ->paginate(self::PER_PAGE, ['id', 'title', 'description', 'location_id', 'category_id', 'state', 'priority', 'resolved_at', 'created_at', 'updated_at'], 'page')
            ->withQueryString();

        /** @var list<string> $ticketIds */
        $ticketIds = collect($paginator->items())->pluck('id')->all();

        [$reactionCountsMap, $userReactionTypesMap, $userSavesSet, $saveCountsMap, $viewerPendingReportSet] =
            $this->batchSocialData($ticketIds, $viewer->id);

        [$commentCountsMap, $latestCommentsMap] =
            $this->batchCommentData($ticketIds, $viewer->id);

        $posts = [];
        foreach ($paginator->items() as $ticket) {
            $tid = (string) $ticket->id;
            $posts[] = $this->toPost(
                $ticket,
                $reactionCountsMap[$tid] ?? [],
                $userReactionTypesMap[$tid] ?? [],
                isset($userSavesSet[$tid]),
                (int) ($saveCountsMap[$tid] ?? 0),
                isset($viewerPendingReportSet[$tid]),
                (int) ($commentCountsMap[$tid] ?? 0),
                $latestCommentsMap[$tid] ?? [],
            );
        }

        return new CommunityFeedViewModel(
            posts: $posts,
            filters: $this->normalizeFilters($filters),
            paginator: $paginator,
            shortcuts: $this->shortcuts(),
            activeLocations: $this->activeLocations(),
            quickSummary: $this->quickSummary(),
            hotCategories: $this->hotCategories(),
            buildings: $this->buildings(),
            categories: $this->categoriesForFilter(),
        );
    }

    /**
     * Batch-load reaction counts, current-user reaction types, save flags,
     * save counts, and pending report flags for a page of ticket IDs — no N+1 queries.
     *
     * @param  list<string>  $ticketIds
     * @return array{
     *   0: array<string, array<string, int>>,
     *   1: array<string, list<string>>,
     *   2: array<string, true>,
     *   3: array<string, int>,
     *   4: array<string, true>
     * }
     */
    private function batchSocialData(array $ticketIds, string $viewerId): array
    {
        if ($ticketIds === []) {
            return [[], [], [], [], []];
        }

        // Aggregate counts: {ticket_id => {type => count}}
        $reactionCountsMap = CommunityReaction::query()
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, type, COUNT(*) as cnt')
            ->groupBy('ticket_id', 'type')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->pluck('cnt', 'type')->map(fn ($c) => (int) $c)->all())
            ->all();

        // Current viewer's reaction types per ticket: {ticket_id => [type, ...]}
        $userReactionTypesMap = CommunityReaction::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('user_id', $viewerId)
            ->select(['ticket_id', 'type'])
            ->get()
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->pluck('type')->all())
            ->all();

        // Current viewer's saved tickets: {ticket_id => true}
        $savedIds = CommunitySave::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('user_id', $viewerId)
            ->pluck('ticket_id')
            ->all();
        /** @var array<string, true> $userSavesSet */
        $userSavesSet = array_fill_keys($savedIds, true);

        // Aggregate save counts per ticket: {ticket_id => count}
        $saveCountsMap = CommunitySave::query()
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, COUNT(*) as cnt')
            ->groupBy('ticket_id')
            ->pluck('cnt', 'ticket_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        // Current viewer's pending reports: {ticket_id => true}
        $pendingReportIds = CommunityReport::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('reported_by', $viewerId)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->pluck('ticket_id')
            ->all();
        /** @var array<string, true> $viewerPendingReportSet */
        $viewerPendingReportSet = array_fill_keys($pendingReportIds, true);

        return [$reactionCountsMap, $userReactionTypesMap, $userSavesSet, $saveCountsMap, $viewerPendingReportSet];
    }

    /**
     * Batch-load comment counts and latest 2 visible comments per ticket.
     * Also batch-loads viewer's pending comment reports (no N+1).
     * Only user_id (for viewer ownership check) and safe fields are selected.
     * No user names, emails, or PII are loaded.
     *
     * @param  list<string>  $ticketIds
     * @return array{
     *   0: array<string, int>,
     *   1: array<string, list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool}>>
     * }
     */
    private function batchCommentData(array $ticketIds, string $viewerId): array
    {
        if ($ticketIds === []) {
            return [[], []];
        }

        // Aggregate visible comment counts per ticket
        $commentCountsMap = CommunityComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->selectRaw('ticket_id, COUNT(*) as cnt')
            ->groupBy('ticket_id')
            ->pluck('cnt', 'ticket_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        // Latest 2 visible comments per ticket via rank window function fallback:
        // fetch all visible comments ordered newest-first, then group in PHP.
        $allVisible = CommunityComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->select(['id', 'ticket_id', 'user_id', 'body', 'edited_at', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        // First pass: collect comment IDs that will appear in the feed (latest 2 per ticket)
        // so we can batch-query pending reports for exactly those IDs — zero N+1.
        $seenCountPerTicket = [];
        /** @var list<string> $visibleCommentIds */
        $visibleCommentIds = [];
        foreach ($allVisible as $comment) {
            $tid = (string) $comment->ticket_id;
            $seenCountPerTicket[$tid] = ($seenCountPerTicket[$tid] ?? 0) + 1;
            if ($seenCountPerTicket[$tid] <= 2) {
                $visibleCommentIds[] = (string) $comment->id;
            }
        }

        // Batch viewer's pending comment reports for the shown comment IDs only
        /** @var array<string, true> $viewerPendingCommentReportSet */
        $viewerPendingCommentReportSet = [];
        if ($visibleCommentIds !== []) {
            $pendingCommentIds = CommunityReport::query()
                ->whereNotNull('comment_id')
                ->whereIn('comment_id', $visibleCommentIds)
                ->where('reported_by', $viewerId)
                ->where('status', CommunityReport::STATUS_PENDING)
                ->pluck('comment_id')
                ->all();
            $viewerPendingCommentReportSet = array_fill_keys($pendingCommentIds, true);
        }

        /** @var array<string, list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool}>> $latestCommentsMap */
        $latestCommentsMap = [];
        foreach ($allVisible as $comment) {
            $tid = (string) $comment->ticket_id;
            if (! isset($latestCommentsMap[$tid])) {
                $latestCommentsMap[$tid] = [];
            }
            if (count($latestCommentsMap[$tid]) >= 2) {
                continue;
            }
            $cid = (string) $comment->id;
            $latestCommentsMap[$tid][] = [
                'id' => $cid,
                'body' => (string) $comment->body,
                'created_ago' => $comment->created_at?->diffForHumans() ?? '',
                'owned_by_viewer' => (string) $comment->user_id === $viewerId,
                'viewer_report_pending' => isset($viewerPendingCommentReportSet[$cid]),
                'edited' => $comment->edited_at !== null,
            ];
        }

        return [$commentCountsMap, $latestCommentsMap];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function baseQuery(array $filters, User $viewer): Builder
    {
        $query = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', self::PUBLIC_STATES)
            ->orderByDesc('updated_at');

        $this->applySearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applyBuilding($query, $filters);
        $this->applyState($query, $filters);
        $this->applyPriority($query, $filters);
        $this->applyHasMedia($query, $filters);
        $this->applyPeriod($query, $filters);
        $this->applySaved($query, $filters, $viewer);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        $query->where(function ($q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('location', fn ($l) => $l
                    ->where('name', 'like', $like)
                    ->orWhere('building', 'like', $like)
                    ->orWhere('room_code', 'like', $like))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
        });
    }

    /**
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
     * @param  array<string, mixed>  $filters
     */
    private function applyBuilding(Builder $query, array $filters): void
    {
        $building = trim((string) ($filters['building'] ?? ''));
        if ($building !== '') {
            $query->whereHas('location', fn ($l) => $l->where('building', $building));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyState(Builder $query, array $filters): void
    {
        $state = trim((string) ($filters['state'] ?? ''));
        if ($state !== '' && in_array($state, self::PUBLIC_STATES, true)) {
            $query->where('state', $state);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyPriority(Builder $query, array $filters): void
    {
        $priority = trim((string) ($filters['priority'] ?? ''));
        if ($priority !== '' && in_array($priority, self::PRIORITIES, true)) {
            $query->where('priority', $priority);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyHasMedia(Builder $query, array $filters): void
    {
        if (($filters['has_media'] ?? '') === '1') {
            $query->whereHas('media');
        }
    }

    /**
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
     * "Guardados" filter: only tickets the viewer has saved, still visible.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySaved(Builder $query, array $filters, User $viewer): void
    {
        if (($filters['saved'] ?? '') !== '1') {
            return;
        }

        $query->whereHas('communitySaves', fn ($q) => $q->where('user_id', $viewer->id));
    }

    /**
     * @param  array<string, int>  $reactionCounts  {type => count}
     * @param  list<string>  $userReactionTypes  types the viewer has active
     * @param  list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool}>  $latestComments
     * @return array{id: string, ref: string, title: string, summary: string, state: string, state_label: string, state_tone: string, priority: string, priority_label: string, priority_tone: string, updated_ago: string, created_ago: string, is_recent: bool, is_resolved: bool, location: array{name: string, building: string, floor: string, room_code: string}|null, category: array{name: string, icon: string}|null, thumbnail_url: string|null, thumbnail_type: string|null, media_count: int, has_media: bool, media_images: list<string>, reactions: array{counts: array<string, int>, user_types: list<string>}, saved: bool, saves_count: int, viewer_report_pending: bool, comments: array{count: int, items: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool}>}}
     */
    private function toPost(
        Ticket $ticket,
        array $reactionCounts,
        array $userReactionTypes,
        bool $isSaved,
        int $savesCount,
        bool $viewerReportPending = false,
        int $commentCount = 0,
        array $latestComments = [],
    ): array {
        $firstMedia = $ticket->media->first();
        $mediaCount = $ticket->media->count();
        $state = (string) $ticket->state;

        $mediaImages = $ticket->media
            ->filter(fn ($m) => str_starts_with((string) $m->file_type, 'image'))
            ->values()
            ->map(fn ($m) => (string) $m->file_url)
            ->all();

        $counts = [
            CommunityReaction::TYPE_INTERESTED => (int) ($reactionCounts[CommunityReaction::TYPE_INTERESTED] ?? 0),
            CommunityReaction::TYPE_ALSO_HAPPENS => (int) ($reactionCounts[CommunityReaction::TYPE_ALSO_HAPPENS] ?? 0),
            CommunityReaction::TYPE_SEEN => (int) ($reactionCounts[CommunityReaction::TYPE_SEEN] ?? 0),
        ];

        return [
            'id' => (string) $ticket->id,
            'ref' => $this->boardReference((string) $ticket->id),
            'title' => (string) $ticket->title,
            'summary' => Str::limit((string) ($ticket->description ?? ''), self::MAX_SUMMARY),
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'state_tone' => $this->stateTone($state),
            'priority' => (string) $ticket->priority,
            'priority_label' => $this->priorityLabel((string) $ticket->priority),
            'priority_tone' => $this->priorityTone((string) $ticket->priority),
            'updated_ago' => $ticket->updated_at?->diffForHumans() ?? '',
            'created_ago' => $ticket->created_at?->diffForHumans() ?? '',
            'is_recent' => $ticket->created_at?->gte(now()->subHours(24)) ?? false,
            'is_resolved' => $state === Ticket::STATE_RESOLVED,
            'location' => $ticket->location !== null ? [
                'name' => (string) $ticket->location->name,
                'building' => (string) $ticket->location->building,
                'floor' => (string) $ticket->location->floor,
                'room_code' => (string) $ticket->location->room_code,
            ] : null,
            'category' => $ticket->category !== null ? [
                'name' => (string) $ticket->category->name,
                'icon' => (string) $ticket->category->icon,
            ] : null,
            'thumbnail_url' => $firstMedia?->file_url !== null ? (string) $firstMedia->file_url : null,
            'thumbnail_type' => $firstMedia?->file_type !== null ? (string) $firstMedia->file_type : null,
            'media_count' => $mediaCount,
            'has_media' => $mediaCount > 0,
            'media_images' => $mediaImages,
            'reactions' => [
                'counts' => $counts,
                'user_types' => array_values($userReactionTypes),
            ],
            'saved' => $isSaved,
            'saves_count' => $savesCount,
            'viewer_report_pending' => $viewerReportPending,
            'comments' => [
                'count' => $commentCount,
                'items' => array_values($latestComments),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
            'building' => trim((string) ($filters['building'] ?? '')),
            'state' => trim((string) ($filters['state'] ?? '')),
            'priority' => trim((string) ($filters['priority'] ?? '')),
            'has_media' => ($filters['has_media'] ?? '') === '1' ? '1' : '',
            'period' => in_array(trim((string) ($filters['period'] ?? '')), self::PERIODS, true)
                ? trim((string) ($filters['period'] ?? ''))
                : '',
            'saved' => ($filters['saved'] ?? '') === '1' ? '1' : '',
        ];
    }

    /**
     * @return list<array{label: string, icon: string, url: string}>
     */
    private function shortcuts(): array
    {
        $base = route('reporter.community');

        return [
            ['label' => 'Objetos encontrados',   'icon' => 'package-search',    'url' => $base.'?q=objeto+encontrado'],
            ['label' => 'Laboratorios con fallas', 'icon' => 'flask-conical',     'url' => $base.'?q=laboratorio'],
            ['label' => 'Aulas reportadas',       'icon' => 'school',            'url' => $base.'?q=aula'],
            ['label' => 'Red y conectividad',     'icon' => 'cable',             'url' => $base.'?q=red'],
            ['label' => 'Resueltos hoy',          'icon' => 'circle-check-big',  'url' => $base.'?state=resolved&period=24h'],
        ];
    }

    /**
     * @return list<array{name: string, building: string, room_code: string, url: string}>
     */
    private function activeLocations(): array
    {
        return Location::query()
            ->select(['locations.id', 'locations.name', 'locations.building', 'locations.room_code'])
            ->join('tickets', 'locations.id', '=', 'tickets.location_id')
            ->whereRaw('"tickets"."community_visible" IS TRUE')
            ->whereIn('tickets.state', self::PUBLIC_STATES)
            ->groupBy('locations.id', 'locations.name', 'locations.building', 'locations.room_code')
            ->orderByDesc(DB::raw('COUNT(tickets.id)'))
            ->limit(5)
            ->get()
            ->map(fn (Location $loc) => [
                'name' => (string) $loc->name,
                'building' => (string) $loc->building,
                'room_code' => (string) $loc->room_code,
                'url' => route('reporter.community').'?building='.rawurlencode((string) $loc->building),
            ])
            ->all();
    }

    /**
     * @return array{active: int, resolved: int, locations: int}
     */
    private function quickSummary(): array
    {
        $counts = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', self::PUBLIC_STATES)
            ->selectRaw('state, COUNT(*) as cnt')
            ->groupBy('state')
            ->pluck('cnt', 'state');

        $locationCount = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS])
            ->distinct()
            ->count('location_id');

        return [
            'active' => (int) ($counts[Ticket::STATE_OPEN] ?? 0) + (int) ($counts[Ticket::STATE_IN_PROGRESS] ?? 0),
            'resolved' => (int) ($counts[Ticket::STATE_RESOLVED] ?? 0),
            'locations' => $locationCount,
        ];
    }

    /**
     * @return list<array{name: string, icon: string, url: string}>
     */
    private function hotCategories(): array
    {
        return Category::query()
            ->select(['categories.id', 'categories.name', 'categories.icon'])
            ->join('tickets', 'categories.id', '=', 'tickets.category_id')
            ->whereRaw('"tickets"."community_visible" IS TRUE')
            ->whereIn('tickets.state', self::PUBLIC_STATES)
            ->groupBy('categories.id', 'categories.name', 'categories.icon')
            ->orderByDesc(DB::raw('COUNT(tickets.id)'))
            ->limit(5)
            ->get()
            ->map(fn (Category $cat) => [
                'name' => (string) $cat->name,
                'icon' => (string) $cat->icon,
                'url' => route('reporter.community').'?category='.rawurlencode((string) $cat->id),
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
     * @return list<array{id: string, name: string, icon: string}>
     */
    private function categoriesForFilter(): array
    {
        return Category::query()
            ->select(['id', 'name', 'icon'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
                'icon' => (string) $c->icon,
            ])
            ->all();
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'open' => 'Abierto',
            'in_progress' => 'En progreso',
            'resolved' => 'Resuelto',
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }

    private function stateTone(string $state): string
    {
        return match ($state) {
            'open' => 'open',
            'in_progress' => 'progress',
            'resolved' => 'resolved',
            default => 'neutral',
        };
    }
}
