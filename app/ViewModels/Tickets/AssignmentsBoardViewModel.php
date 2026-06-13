<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Collection;

/**
 * Read-only data carrier for the maintenance "Mis asignaciones" board.
 *
 * Built once by AssignmentsBoardQuery and consumed by the assignments view.
 * Every figure is already scoped to the authenticated technician's own
 * assignments (assigned_to = them), so the view never re-filters for security.
 *
 * Contract:
 *  - $assignments reflects the ACTIVE tab + filters (center list).
 *  - $tabs / $summary describe the technician's whole assignment universe and
 *    stay stable across tabs, so their counters don't jump while narrowing.
 *  - $focused is the assignment shown in the detail rail (detail + stepper +
 *    activity), or null when the technician has no assignments to show.
 */
final class AssignmentsBoardViewModel
{
    /**
     * @param  list<array{key: string, label: string, count: int}>  $tabs
     * @param  array<string, mixed>  $filters  Validated filters, for repopulating the toolbar.
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, Category>  $categories
     * @param  array{active: int, in_progress: int, waiting: int, overdue: int, compliance: int}  $summary
     * @param  list<array<string, mixed>>  $assignments  Shaped rows for the active tab.
     * @param  array<string, mixed>|null  $focused  Full detail (incl. steps[] + activity[]) or null.
     */
    public function __construct(
        public readonly string $technicianId,
        public readonly string $activeTab,
        public readonly array $tabs,
        public readonly array $filters,
        public readonly string $sort,
        public readonly Collection $locations,
        public readonly Collection $categories,
        public readonly array $summary,
        public readonly array $assignments,
        public readonly int $shownCount,
        public readonly int $totalCount,
        public readonly bool $hasMore,
        public readonly ?array $focused,
    ) {}

    /** True when the technician has at least one assignment in any state. */
    public function hasAnyAssignments(): bool
    {
        return $this->tabs[0]['count'] > 0
            || ($this->tabs[2]['count'] ?? 0) > 0;
    }
}
