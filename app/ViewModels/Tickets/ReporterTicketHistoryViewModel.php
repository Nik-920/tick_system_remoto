<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Collection;

/**
 * Read-only data carrier for the reporter "Historial" board.
 *
 * Built once by ReporterTicketHistoryQuery and consumed by the history view.
 * Every figure is already scoped to the authenticated reporter's own closed-out
 * tickets (reporter_id = them, state in resolved/rejected), so the view never
 * re-filters for security.
 *
 * Contract:
 *  - $tickets reflects the active result chip + filters + sort + page (list).
 *  - $chips / $summary describe the reporter's WHOLE closed history and stay
 *    stable across the active result chip, so their counters don't jump.
 */
final class ReporterTicketHistoryViewModel
{
    /**
     * @param  array<string, mixed>  $filters  Validated filters, for repopulating the toolbar.
     * @param  list<array{key: string, label: string, count: int, tone: string, active: bool}>  $chips
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, Category>  $categories
     * @param  list<array<string, mixed>>  $tickets  Shaped rows for the current page.
     * @param  array{from: int, to: int, total: int, current: int, last: int, pages: list<int>}  $pagination
     * @param  array{total: int, donut: list<array{key: string, label: string, count: int, percent: int, tone: string, color: string, start: float, end: float}>, avg_value: string, avg_note: string, avg_has_data: bool}  $summary
     */
    public function __construct(
        public readonly string $reporterId,
        public readonly string $activeResult,
        public readonly string $sort,
        public readonly array $filters,
        public readonly array $chips,
        public readonly Collection $locations,
        public readonly Collection $categories,
        public readonly array $tickets,
        public readonly array $pagination,
        public readonly array $summary,
    ) {}

    /** True when the reporter has at least one closed-out ticket. */
    public function hasAnyHistory(): bool
    {
        return $this->summary['total'] > 0;
    }

    /** True when a content filter (not the result chip) is narrowing the list. */
    public function hasOtherFilters(): bool
    {
        foreach (['search', 'priority', 'location_id', 'category_id', 'from', 'to'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
}
