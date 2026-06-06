<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Read-only data carrier for the maintenance "Tickets V2" board.
 *
 * Built once by MaintenanceBoardQuery and consumed by the V2 view. Every
 * personal figure is already scoped to what the technician may see
 * (assigned to them OR open-and-claimable), mirroring TicketIndexQuery's
 * security contract.
 *
 * Contract:
 *  - $queue / $queueTotal reflect the ACTIVE view + filters (center column).
 *  - $summary / $chipCounts / $insights describe the whole maintenance-visible
 *    universe (right rail + chips + stat cards), independent of the active chip,
 *    so their numbers stay stable while the user narrows the queue.
 */
final class MaintenanceBoardViewModel
{
    /**
     * @param  array<string, mixed>  $filters  Validated filters (for select state + querystrings).
     * @param  Collection<int, Ticket>  $queue  Prioritised tickets for the active view, scored desc.
     * @param  array{visible: int, in_progress: int, mine: int, available: int}  $summary
     * @param  array{all: int, in_progress: int, high: int, available: int, duplicates: int}  $chipCounts
     * @param  array{
     *     priority: array{total: int, bands: list<array{key: string, label: string, count: int, percent: int}>},
     *     labs: array{peak: int, items: list<array{name: string, count: int}>},
     *     states: array{open: int, in_progress: int, resolved_today: int, rejected: int},
     *     duplicates: int,
     *     avg_time: array{label: string, has_data: bool}
     * }  $insights
     */
    public function __construct(
        public readonly string $technicianId,
        public readonly string $view,
        public readonly array $filters,
        public readonly Collection $queue,
        public readonly int $queueTotal,
        public readonly array $summary,
        public readonly array $chipCounts,
        public readonly array $insights,
    ) {}

    /** Conic-gradient stops (in %) for the priority donut: [altaEnd, mediaEnd]. */
    public function donutStops(): array
    {
        $bands = $this->insights['priority']['bands'];
        $alta = $bands[0]['percent'] ?? 0;
        $media = $bands[1]['percent'] ?? 0;

        return [$alta, $alta + $media];
    }

    /** True when there is at least one ticket in the visible universe. */
    public function hasAnyTickets(): bool
    {
        return $this->summary['visible'] > 0;
    }
}
