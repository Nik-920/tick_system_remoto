<?php

declare(strict_types=1);

namespace App\Queries\Community;

use App\Models\Category;
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
 *
 * V1 visibility rule (no community_visible column yet):
 * - state IN (open, in_progress, resolved) → public.
 * - cancelled / rejected → hidden.
 *
 * Debt: future phase must introduce community_posts or community_visible
 * before enabling reactions / saves / comments.
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
        $base = $this->baseQuery($filters);

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

        $posts = [];
        foreach ($paginator->items() as $ticket) {
            $posts[] = $this->toPost($ticket);
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
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Ticket::query()
            ->whereIn('state', self::PUBLIC_STATES)
            ->orderByDesc('updated_at');

        $this->applySearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applyBuilding($query, $filters);
        $this->applyState($query, $filters);
        $this->applyPriority($query, $filters);
        $this->applyHasMedia($query, $filters);
        $this->applyPeriod($query, $filters);

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
     * @return array{id: string, ref: string, title: string, summary: string, state: string, state_label: string, state_tone: string, priority: string, priority_label: string, priority_tone: string, updated_ago: string, created_ago: string, is_recent: bool, is_resolved: bool, location: array{name: string, building: string, floor: string, room_code: string}|null, category: array{name: string, icon: string}|null, thumbnail_url: string|null, thumbnail_type: string|null, media_count: int, has_media: bool}
     */
    private function toPost(Ticket $ticket): array
    {
        $firstMedia = $ticket->media->first();
        $mediaCount = $ticket->media->count();
        $state = (string) $ticket->state;

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
            ->whereIn('state', self::PUBLIC_STATES)
            ->selectRaw('state, COUNT(*) as cnt')
            ->groupBy('state')
            ->pluck('cnt', 'state');

        $locationCount = Ticket::query()
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
