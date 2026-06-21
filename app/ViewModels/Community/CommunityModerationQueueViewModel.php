<?php

declare(strict_types=1);

namespace App\ViewModels\Community;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CommunityModerationQueueViewModel
{
    /**
     * @param  list<array{id: string, ref: string, title: string, state: string, state_label: string, priority: string, priority_label: string, community_visible: bool, state_blocks_feed: bool, community_visibility_reason: string|null, community_hidden_at: string|null, hidden_by_name: string|null, location: array{name: string, building: string, room_code: string}|null, category: array{name: string}|null, show_url: string, created_at: string, updated_at: string, pending_reports_count: int, latest_pending_report: array{id: string, reason_label: string, note: string|null}|null}>  $items
     * @param  array<string, string>  $filters
     * @param  array{total_visible: int, total_hidden: int, hidden_last_7d: int, visible_high_priority: int, pending_reports: int}  $summary
     * @param  list<array{id: string, name: string}>  $categories
     * @param  list<string>  $buildings
     * @param  list<array{value: string, label: string}>  $states
     * @param  list<array{value: string, label: string}>  $visibilityOptions
     */
    public function __construct(
        public readonly array $items,
        public readonly array $filters,
        public readonly LengthAwarePaginator $paginator,
        public readonly array $summary,
        public readonly array $categories,
        public readonly array $buildings,
        public readonly array $states,
        public readonly array $visibilityOptions,
    ) {}

    public function hasActiveFilters(): bool
    {
        return $this->filters['q'] !== ''
            || $this->filters['visibility'] !== 'all'
            || $this->filters['state'] !== ''
            || $this->filters['category'] !== ''
            || $this->filters['building'] !== ''
            || $this->filters['period'] !== '';
    }
}
