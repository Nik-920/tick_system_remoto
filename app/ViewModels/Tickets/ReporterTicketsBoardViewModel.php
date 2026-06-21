<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Collection;

/**
 * Read-only data carrier for the reporter "Mis tickets" board.
 *
 * Built once by ReporterTicketsBoardQuery and consumed by the board view. Every
 * figure is already scoped to the authenticated reporter's own tickets
 * (reporter_id = them), so the view never re-filters for security.
 *
 * Contract:
 *  - $tickets reflects the active status chip + filters + sort + page (list).
 *  - $chips / $summary / $donut / $labs describe the reporter's WHOLE set of
 *    tickets and stay stable across the active chip, so the counters don't jump
 *    while narrowing the list.
 */
final class ReporterTicketsBoardViewModel
{
    /**
     * @param  array<string, mixed>  $filters  Validated filters, for repopulating the toolbar.
     * @param  list<array{key: string, label: string, count: int, tone: string, active: bool}>  $chips
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, Category>  $categories
     * @param  list<array<string, mixed>>  $tickets  Shaped rows for the current page.
     * @param  array{from: int, to: int, total: int, current: int, last: int, pages: list<int>}  $pagination
     * @param  array{total: int, avg_value: string, avg_note: string, avg_has_data: bool, donut: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>}  $summary
     * @param  array{total: int, segments: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>}  $donut
     * @param  array{peak: int, items: list<array{name: string, label: string, count: int}>}  $labs
     */
    public function __construct(
        public readonly string $reporterId,
        public readonly string $activeStatus,
        public readonly string $sort,
        public readonly array $filters,
        public readonly array $chips,
        public readonly Collection $locations,
        public readonly Collection $categories,
        public readonly array $tickets,
        public readonly array $pagination,
        public readonly array $summary,
        public readonly array $donut,
        public readonly array $labs,
        public readonly int $totalReported,
    ) {}

    /** True when the reporter has filed at least one ticket. */
    public function hasAnyTickets(): bool
    {
        return $this->totalReported > 0;
    }

    /** True when a content filter (not the status chip) is narrowing the list. */
    public function hasOtherFilters(): bool
    {
        foreach (['search', 'priority', 'location_id', 'category_id', 'from', 'to'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** True when anything (status chip or a content filter) is narrowing the list. */
    public function hasAnyFilter(): bool
    {
        return $this->activeStatus !== 'all' || $this->hasOtherFilters();
    }

    /** Number of active filters (status chip + content filters) for the badge on the filter button. */
    public function activeFiltersCount(): int
    {
        $count = $this->activeStatus !== 'all' ? 1 : 0;
        foreach (['priority', 'location_id', 'category_id', 'from', 'to'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<string, string> */
    public function sortOptions(): array
    {
        return [
            'recent' => 'Más recientes',
            'oldest' => 'Más antiguos',
            'priority' => 'Prioridad',
        ];
    }

    /** Human-readable display label for a Location (building · room_code · name). */
    public function locationLabel(Location $location): string
    {
        return $location->getDisplayLabel();
    }
}
