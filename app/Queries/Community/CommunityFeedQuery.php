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
use App\Support\Cache\CacheTtl;
use App\Support\Community\CommentAuthorPresenter;
use App\ViewModels\Community\CommunityFeedViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds the public community feed for the reporter role.
 *
 * Security contract:
 * - Base query selects only safe ticket columns — reporter_id, assigned_to,
 *   and assigned_by are never loaded. The ticket/post author stays anonymous
 *   ("Creado por usuario anónimo") — this decision is unrelated to comments.
 * - Eager loading uses explicit column lists; user relations are not loaded
 *   for the ticket itself.
 * - Comment authors are a deliberate exception: the commenter's display name
 *   (name + last_name) and role are shown next to each comment/reply, see
 *   batchCommentData()/mapCommentItem(). Email and internal IDs are still
 *   never exposed.
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

    /** @var list<string> */
    private const ALLOWED_SORTS = ['recent', 'active', 'discussed', 'supported'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function forReporter(User $viewer, array $filters): CommunityFeedViewModel
    {
        $normalized = $this->normalizeFilters($filters);
        $base = $this->baseQuery($normalized, $viewer);

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

        [$commentCountsMap, $rootCommentCountsMap, $latestCommentsMap] =
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
                (int) ($rootCommentCountsMap[$tid] ?? 0),
                $latestCommentsMap[$tid] ?? [],
            );
        }

        $currentSort = $normalized['sort'];

        return new CommunityFeedViewModel(
            posts: $posts,
            filters: $normalized,
            paginator: $paginator,
            quickSummary: $this->quickSummary(),
            buildings: $this->buildings(),
            categories: $this->categoriesForFilter(),
            currentSort: $currentSort,
            sortOptions: $this->buildSortOptions($normalized),
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
     * Batch-load comment counts and the latest visible root comments per ticket,
     * each with up to 2 visible one-level replies. Also batch-loads the viewer's
     * pending comment reports for every rendered comment/reply (no N+1).
     *
     * Hierarchy rules:
     * - count includes ALL visible comments (root comments + replies).
     * - root_count includes only visible ROOT comments — it powers "Ver más
     *   comentarios" pagination, which loads more roots (not replies).
     * - Only the latest 2 visible ROOT comments per ticket are shown here.
     * - Each shown root carries the latest 2 visible replies; replies of
     *   hidden/deleted roots never surface because those roots are excluded.
     *
     * @param  list<string>  $ticketIds
     * @return array{
     *   0: array<string, int>,
     *   1: array<string, int>,
     *   2: array<string, list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string, reply_count: int, replies: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}>}>>
     * }
     */
    private function batchCommentData(array $ticketIds, string $viewerId): array
    {
        if ($ticketIds === []) {
            return [[], [], []];
        }

        // Query 1 — aggregate visible counts per ticket (root comments AND replies).
        $commentCountsMap = CommunityComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->selectRaw('ticket_id, COUNT(*) as cnt')
            ->groupBy('ticket_id')
            ->pluck('cnt', 'ticket_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        // Query 1b — aggregate visible ROOT-only counts per ticket, used to
        // decide whether "Ver más comentarios" should render.
        $rootCommentCountsMap = CommunityComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereNull('parent_id')
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->selectRaw('ticket_id, COUNT(*) as cnt')
            ->groupBy('ticket_id')
            ->pluck('cnt', 'ticket_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        // Query 2 — latest visible ROOT comments per ticket (parent_id null),
        // reduced in PHP to the newest 2 per ticket.
        $rootComments = CommunityComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereNull('parent_id')
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->select(['id', 'ticket_id', 'user_id', 'body', 'edited_at', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        $rootSeenPerTicket = [];
        /** @var array<string, list<CommunityComment>> $shownRootsByTicket */
        $shownRootsByTicket = [];
        foreach ($rootComments as $root) {
            $tid = (string) $root->ticket_id;
            $rootSeenPerTicket[$tid] = ($rootSeenPerTicket[$tid] ?? 0) + 1;
            if ($rootSeenPerTicket[$tid] > 2) {
                continue;
            }
            $shownRootsByTicket[$tid][] = $root;
        }

        $latestCommentsMap = $this->mapRootsToCommentItems($shownRootsByTicket, $viewerId);

        return [$commentCountsMap, $rootCommentCountsMap, $latestCommentsMap];
    }

    /**
     * Load the next page of visible ROOT comments for a single ticket, each
     * with up to 2 visible replies — powers "Ver más comentarios" beyond the
     * first 2 roots already rendered by the feed. Shares mapRootsToCommentItems()
     * with batchCommentData() so a paginated comment renders identically to
     * one shown on initial load (same author resolution, same report flags).
     *
     * @return array{items: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string, reply_count: int, replies: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}>}>, has_more: bool}
     */
    public function loadMoreComments(Ticket $ticket, string $viewerId, int $offset, int $limit = 2): array
    {
        $offset = max(0, $offset);
        $limit = max(1, $limit);

        // Fetch one extra row to detect "has more" without a second COUNT query.
        $page = CommunityComment::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('parent_id')
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->select(['id', 'ticket_id', 'user_id', 'body', 'edited_at', 'created_at'])
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $page->count() > $limit;
        $roots = $page->take($limit)->values();

        $tid = (string) $ticket->id;
        $itemsByTicket = $this->mapRootsToCommentItems([$tid => $roots->all()], $viewerId);

        return [
            'items' => $itemsByTicket[$tid] ?? [],
            'has_more' => $hasMore,
        ];
    }

    /**
     * Shared by batchCommentData() (many tickets, capped at 2 roots each) and
     * loadMoreComments() (one ticket, offset-paginated): loads visible replies
     * (top 2 per parent), the viewer's pending comment reports, and commenter
     * identities for an already-selected set of root comments, then maps every
     * root + its replies into the feed's comment item shape. No N+1 regardless
     * of how many tickets/roots are passed in.
     *
     * @param  array<string, list<CommunityComment>>  $rootsByTicket
     * @return array<string, list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string, reply_count: int, replies: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}>}>>
     */
    private function mapRootsToCommentItems(array $rootsByTicket, string $viewerId): array
    {
        /** @var list<string> $shownRootIds */
        $shownRootIds = [];
        foreach ($rootsByTicket as $roots) {
            foreach ($roots as $root) {
                $shownRootIds[] = (string) $root->id;
            }
        }

        if ($shownRootIds === []) {
            return [];
        }

        // Visible replies for the shown roots; newest 2 per parent are kept
        // for display, the rest only contribute to reply_count.
        /** @var array<string, list<CommunityComment>> $shownRepliesByParent */
        $shownRepliesByParent = [];
        /** @var array<string, int> $replyCountByParent */
        $replyCountByParent = [];
        /** @var list<string> $shownReplyIds */
        $shownReplyIds = [];
        $replies = CommunityComment::query()
            ->whereIn('parent_id', $shownRootIds)
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->select(['id', 'parent_id', 'user_id', 'body', 'edited_at', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($replies as $reply) {
            $pid = (string) $reply->parent_id;
            $replyCountByParent[$pid] = ($replyCountByParent[$pid] ?? 0) + 1;
            if ($replyCountByParent[$pid] > 2) {
                continue;
            }
            $shownRepliesByParent[$pid][] = $reply;
            $shownReplyIds[] = (string) $reply->id;
        }

        // Viewer's pending reports for every rendered comment/reply id.
        $shownCommentIds = array_merge($shownRootIds, $shownReplyIds);
        $pendingCommentIds = CommunityReport::query()
            ->whereNotNull('comment_id')
            ->whereIn('comment_id', $shownCommentIds)
            ->where('reported_by', $viewerId)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->pluck('comment_id')
            ->all();
        /** @var array<string, true> $viewerPendingReportSet */
        $viewerPendingReportSet = array_fill_keys($pendingCommentIds, true);

        // Commenter display name + role (batched, no N+1), shared with the
        // reporter's own ticket-comments modal via CommentAuthorPresenter.
        $allCommentUserIds = array_values(array_unique(array_filter(array_map(
            fn (CommunityComment $c) => $c->user_id !== null ? (string) $c->user_id : null,
            array_merge(
                array_merge(...array_values($rootsByTicket) ?: [[]]),
                array_merge(...array_values($shownRepliesByParent) ?: [[]])
            )
        ))));
        $userDataMap = CommentAuthorPresenter::loadUserData($allCommentUserIds);

        $itemsByTicket = [];
        foreach ($rootsByTicket as $tid => $roots) {
            foreach ($roots as $root) {
                $rid = (string) $root->id;

                $replyItems = [];
                foreach ($shownRepliesByParent[$rid] ?? [] as $reply) {
                    $replyItems[] = $this->mapCommentItem($reply, $viewerId, $viewerPendingReportSet, $userDataMap);
                }

                $item = $this->mapCommentItem($root, $viewerId, $viewerPendingReportSet, $userDataMap);
                $item['reply_count'] = (int) ($replyCountByParent[$rid] ?? 0);
                $item['replies'] = $replyItems;

                $itemsByTicket[$tid][] = $item;
            }
        }

        return $itemsByTicket;
    }

    /**
     * Map a single comment/reply model into the safe feed array shape.
     * The commenter's display name and role are included for identification;
     * email and internal IDs are never part of this shape.
     *
     * @param  array<string, true>  $viewerPendingReportSet
     * @param  array<string, array{name: string, last_name: string, role: string}>  $userDataMap
     * @return array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}
     */
    private function mapCommentItem(CommunityComment $comment, string $viewerId, array $viewerPendingReportSet, array $userDataMap = []): array
    {
        $cid = (string) $comment->id;
        $uid = $comment->user_id !== null ? (string) $comment->user_id : null;
        $isOwner = $uid !== null && $uid === $viewerId;
        $userData = $uid !== null ? ($userDataMap[$uid] ?? null) : null;

        $roleTone = CommentAuthorPresenter::roleTone($userData);
        $author = CommentAuthorPresenter::resolve($userData, $isOwner);

        return [
            'id' => $cid,
            'body' => (string) $comment->body,
            'created_ago' => $comment->created_at?->diffForHumans() ?? '',
            'owned_by_viewer' => $isOwner,
            'viewer_report_pending' => isset($viewerPendingReportSet[$cid]),
            'edited' => $comment->edited_at !== null,
            'role_tone' => $roleTone,
            'role_label' => CommentAuthorPresenter::roleLabel($roleTone),
            'author_label' => $author['label'],
            'author_initials' => $author['initials'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function baseQuery(array $filters, User $viewer): Builder
    {
        $query = Ticket::query()
            ->whereRaw('"community_visible" IS TRUE')
            ->whereIn('state', self::PUBLIC_STATES);

        $this->applySearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applyBuilding($query, $filters);
        $this->applyState($query, $filters);
        $this->applyPriority($query, $filters);
        $this->applyHasMedia($query, $filters);
        $this->applyPeriod($query, $filters);
        $this->applySaved($query, $filters, $viewer);
        $this->applySort($query, $filters);

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
     * @param  list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string, reply_count: int, replies: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}>}>  $latestComments
     * @return array{id: string, ref: string, title: string, summary: string, state: string, state_label: string, state_tone: string, priority: string, priority_label: string, priority_tone: string, updated_ago: string, created_ago: string, is_recent: bool, is_resolved: bool, location: array{name: string, building: string, floor: string, room_code: string}|null, category: array{name: string, icon_type: string, icon_name: string, icon_url: string|null}|null, thumbnail_url: string|null, thumbnail_type: string|null, media_count: int, has_media: bool, media_images: list<string>, reactions: array{counts: array<string, int>, user_types: list<string>}, saved: bool, saves_count: int, viewer_report_pending: bool, comments: array{count: int, root_count: int, items: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string, reply_count: int, replies: list<array{id: string, body: string, created_ago: string, owned_by_viewer: bool, viewer_report_pending: bool, edited: bool, role_tone: string, role_label: string, author_label: string, author_initials: string}>}>}}
     */
    private function toPost(
        Ticket $ticket,
        array $reactionCounts,
        array $userReactionTypes,
        bool $isSaved,
        int $savesCount,
        bool $viewerReportPending = false,
        int $commentCount = 0,
        int $rootCommentCount = 0,
        array $latestComments = [],
    ): array {
        $firstMedia = $ticket->media->first();
        $mediaCount = $ticket->media->count();
        $state = (string) $ticket->state;

        $mediaImages = $ticket->media
            ->filter(fn ($m) => str_starts_with((string) $m->file_type, 'image'))
            ->values()
            ->map(fn ($m) => route('reporter.community.media.thumbnail', $m->id))
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
            'category' => $ticket->category !== null ? $this->normalizeCategory($ticket->category) : null,
            'thumbnail_url' => $firstMedia !== null ? route('reporter.community.media.thumbnail', $firstMedia->id) : null,
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
                'root_count' => $rootCommentCount,
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
        $sort = trim((string) ($filters['sort'] ?? ''));

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
            'sort' => in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'recent',
        ];
    }

    /**
     * @return array{active: int, resolved: int, locations: int}
     */
    private function quickSummary(): array
    {
        /** @var array{active: int, resolved: int, locations: int} */
        return Cache::remember('community.ref.quick_summary', CacheTtl::COMMUNITY_REFERENCE, function (): array {
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
        });
    }

    /**
     * @return list<string>
     */
    private function buildings(): array
    {
        /** @var list<string> */
        return Cache::remember('community.ref.buildings', CacheTtl::COMMUNITY_REFERENCE, function (): array {
            return Location::query()
                ->whereNotNull('building')
                ->where('building', '!=', '')
                ->distinct()
                ->orderBy('building')
                ->pluck('building')
                ->map(fn (mixed $b) => (string) $b)
                ->all();
        });
    }

    /**
     * @return list<array{id: string, name: string, icon: string}>
     */
    private function categoriesForFilter(): array
    {
        /** @var list<array{id: string, name: string, icon: string}> */
        return Cache::remember('community.ref.categories', CacheTtl::COMMUNITY_REFERENCE, function (): array {
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
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'recent');

        match ($sort) {
            'active' => $this->orderByActive($query),
            'discussed' => $this->orderByDiscussed($query),
            'supported' => $this->orderBySupported($query),
            default => $query->orderByDesc('updated_at'),
        };
    }

    private function orderByActive(Builder $query): void
    {
        $query->orderByRaw(
            '(
                (SELECT COUNT(*) FROM community_reactions
                    WHERE community_reactions.ticket_id = tickets.id AND community_reactions.type = ?) * 3 +
                (SELECT COUNT(*) FROM community_reactions
                    WHERE community_reactions.ticket_id = tickets.id AND community_reactions.type = ?) * 4 +
                (SELECT COUNT(*) FROM community_reactions
                    WHERE community_reactions.ticket_id = tickets.id AND community_reactions.type = ?) * 1 +
                (SELECT COUNT(*) FROM community_saves
                    WHERE community_saves.ticket_id = tickets.id) * 3 +
                (SELECT COUNT(*) FROM community_comments
                    WHERE community_comments.ticket_id = tickets.id AND community_comments.status = ?) * 5 -
                (SELECT COUNT(*) FROM community_reports
                    WHERE community_reports.ticket_id = tickets.id
                    AND community_reports.comment_id IS NULL
                    AND community_reports.status = ?) * 4
            ) DESC',
            [
                CommunityReaction::TYPE_INTERESTED,
                CommunityReaction::TYPE_ALSO_HAPPENS,
                CommunityReaction::TYPE_SEEN,
                CommunityComment::STATUS_VISIBLE,
                CommunityReport::STATUS_PENDING,
            ]
        )->orderByDesc('updated_at');
    }

    private function orderByDiscussed(Builder $query): void
    {
        $query->orderByRaw(
            '(SELECT COUNT(*) FROM community_comments
                WHERE community_comments.ticket_id = tickets.id
                AND community_comments.status = ?) DESC',
            [CommunityComment::STATUS_VISIBLE]
        )->orderByDesc('updated_at');
    }

    private function orderBySupported(Builder $query): void
    {
        $query->orderByRaw(
            '(
                (SELECT COUNT(*) FROM community_reactions
                    WHERE community_reactions.ticket_id = tickets.id AND community_reactions.type = ?) +
                (SELECT COUNT(*) FROM community_reactions
                    WHERE community_reactions.ticket_id = tickets.id AND community_reactions.type = ?) +
                (SELECT COUNT(*) FROM community_saves
                    WHERE community_saves.ticket_id = tickets.id)
            ) DESC',
            [
                CommunityReaction::TYPE_INTERESTED,
                CommunityReaction::TYPE_ALSO_HAPPENS,
            ]
        )->orderByDesc('updated_at');
    }

    /**
     * Build pre-computed sort option arrays for the ViewModel (URLs include current filters).
     *
     * @param  array<string, string>  $filters  normalized filters
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    private function buildSortOptions(array $filters): array
    {
        $currentSort = $filters['sort'];
        $base = route('reporter.community');

        $params = array_filter(
            array_diff_key($filters, ['sort' => '']),
            fn (string $v) => $v !== ''
        );

        $options = [
            ['key' => 'recent', 'label' => 'Recientes'],
            ['key' => 'active', 'label' => 'Activos'],
            ['key' => 'discussed', 'label' => 'Comentados'],
            ['key' => 'supported', 'label' => 'Apoyados'],
        ];

        return array_map(function (array $option) use ($base, $params, $currentSort): array {
            $queryParams = array_merge($params, ['sort' => $option['key']]);

            return [
                'key' => $option['key'],
                'label' => $option['label'],
                'url' => $base.'?'.http_build_query($queryParams),
                'active' => $option['key'] === $currentSort,
            ];
        }, $options);
    }

    /**
     * Normalize a category's icon into a typed shape safe for Blade rendering.
     *
     * The `icon` column may hold either a Lucide icon name (e.g. "tag") or a
     * full Supabase image URL. Passing a URL to `x-dynamic-component` would
     * produce an invalid component name like `lucide-https://...` and throw an
     * InvalidArgumentException at render time.
     *
     * @return array{name: string, icon_type: string, icon_name: string, icon_url: string|null}
     */
    private function normalizeCategory(Category $category): array
    {
        $raw = (string) $category->icon;

        if (
            $raw !== '' &&
            (str_starts_with($raw, 'https://') || str_starts_with($raw, 'http://'))
        ) {
            return [
                'name' => (string) $category->name,
                'icon_type' => 'image',
                'icon_name' => 'tag',
                'icon_url' => route('reporter.community.categories.icon', $category),
            ];
        }

        $iconName = (preg_match('/^[a-z0-9-]+$/', $raw) === 1 && $raw !== '') ? $raw : 'tag';

        return [
            'name' => (string) $category->name,
            'icon_type' => 'lucide',
            'icon_name' => $iconName,
            'icon_url' => null,
        ];
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
