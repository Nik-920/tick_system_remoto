<?php

declare(strict_types=1);

namespace App\ViewModels\Community;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Immutable bag passed from CommunityFeedQuery to the community Blade view.
 *
 * Every field is a plain PHP value (scalar, array, or paginator) —
 * no raw Eloquent models are exposed to the template layer.
 */
final class CommunityFeedViewModel
{
    /**
     * @param  list<array{id: string, ref: string, title: string, summary: string, state: string, state_label: string, state_tone: string, priority: string, priority_label: string, priority_tone: string, updated_ago: string, created_ago: string, is_recent: bool, is_resolved: bool, location: array{name: string, building: string, floor: string, room_code: string}|null, category: array{name: string, icon: string}|null, thumbnail_url: string|null, thumbnail_type: string|null, media_count: int, has_media: bool, media_images: list<string>, reactions: array{counts: array<string, int>, user_types: list<string>}, saved: bool, saves_count: int}>  $posts
     * @param  array<string, string>  $filters
     * @param  list<array{label: string, icon: string, url: string}>  $shortcuts
     * @param  list<array{name: string, building: string, room_code: string, url: string}>  $activeLocations
     * @param  array{active: int, resolved: int, locations: int}  $quickSummary
     * @param  list<array{name: string, icon: string, url: string}>  $hotCategories
     * @param  list<string>  $buildings
     * @param  list<array{id: string, name: string, icon: string}>  $categories
     */
    public function __construct(
        public readonly array $posts,
        public readonly array $filters,
        public readonly LengthAwarePaginator $paginator,
        public readonly array $shortcuts,
        public readonly array $activeLocations,
        public readonly array $quickSummary,
        public readonly array $hotCategories,
        public readonly array $buildings,
        public readonly array $categories,
    ) {}

    public function hasActiveFilters(): bool
    {
        return array_filter($this->filters, fn (string $v) => $v !== '') !== [];
    }

    public function activeFilterCount(): int
    {
        return count(array_filter($this->filters, fn (string $v) => $v !== ''));
    }
}
