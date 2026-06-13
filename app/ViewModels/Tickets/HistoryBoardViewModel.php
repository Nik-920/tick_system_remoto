<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Collection;

/**
 * Read-only data carrier for the maintenance "Historial" board.
 *
 * Built once by HistoryBoardQuery and consumed by the history view. Every
 * figure is already scoped to the authenticated technician's own closed-out
 * tickets (assigned_to = them, state in resolved/rejected), so the view never
 * re-filters for security.
 *
 * Contract:
 *  - $tickets reflects the active result chip + filters + page (center list).
 *  - $chips / $summary / $donut / $topLabs describe the technician's whole
 *    history within the selected period and stay stable across the result chip,
 *    so their counters don't jump while narrowing the list.
 */
final class HistoryBoardViewModel
{
    /**
     * @param  list<array{key: string, label: string, count: int, tone: string}>  $chips
     * @param  array<string, mixed>  $filters  Validated filters, for repopulating the toolbar.
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, Category>  $categories
     * @param  array{total: int, resolved: int, rejected: int, resolved_pct: int, rejected_pct: int, resolution_rate: int, avg_label: string, avg_has_data: bool}  $summary
     * @param  list<array<string, mixed>>  $tickets  Shaped rows for the current page.
     * @param  array{from: int, to: int, total: int, current: int, last: int, pages: list<int>}  $pagination
     * @param  array{total: int, segments: list<array{key: string, label: string, count: int, percent: float, start: float, end: float, color: string, tone: string}>}  $donut
     * @param  array{peak: int, items: list<array{name: string, count: int}>}  $topLabs
     * @param  list<array{ref: string, id: string, text: string, tone: string, icon: string, actor: string, at: string}>  $activity
     */
    public function __construct(
        public readonly string $technicianId,
        public readonly string $activeResult,
        public readonly array $chips,
        public readonly array $filters,
        public readonly string $sort,
        public readonly Collection $locations,
        public readonly Collection $categories,
        public readonly array $summary,
        public readonly array $tickets,
        public readonly array $pagination,
        public readonly array $donut,
        public readonly array $topLabs,
        public readonly array $activity,
    ) {}

    /** True when the technician has at least one closed-out ticket in the period. */
    public function hasAnyHistory(): bool
    {
        return $this->summary['total'] > 0;
    }
}
