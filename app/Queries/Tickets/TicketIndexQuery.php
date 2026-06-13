<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the base Eloquent query for the ticket index listing.
 *
 * Responsibilities:
 *  - Apply role scope FIRST (reporter/maintenance/admin) — unconditional.
 *  - Apply user-supplied filters AFTER scope — never before.
 *  - Eager-load relations needed for the listing.
 *
 * Does NOT: paginate, sort, authorize, change state, send notifications.
 */
final class TicketIndexQuery
{
    public function __construct(
        private readonly User $user,
        private readonly array $filters,
    ) {}

    public static function build(User $user, array $filters): Builder
    {
        return (new self($user, $filters))->toQuery();
    }

    public function toQuery(): Builder
    {
        $query = Ticket::query()->with($this->resolveRelations());

        // Role scope MUST be applied before any user-supplied filter so that
        // search, duplicates, location, etc. cannot leak tickets outside the
        // boundary established by the user's role.
        $this->applyRoleScope($query);
        $this->applyFilters($query);

        return $query;
    }

    private function applyRoleScope(Builder $query): void
    {
        if (
            $this->user->hasRole('reporter')
            && ! $this->user->hasAnyRole(['maintenance', 'admin', 'super_admin'])
        ) {
            /** @phpstan-ignore-next-line */
            $query->reportedBy($this->user->id);

            return;
        }

        if (
            $this->user->hasRole('maintenance')
            && ! $this->user->hasAnyRole(['admin', 'super_admin'])
        ) {
            /** @phpstan-ignore-next-line */
            $query->visibleToMaintenance($this->user->id);
        }
        // admin / super_admin: no additional scope — see all tickets.
    }

    private function applyFilters(Builder $query): void
    {
        $this->applyStateFilter($query);
        $this->applyPriorityFilter($query);
        $this->applyLocationFilter($query);
        $this->applyCategoryFilter($query);
        $this->applyAssignmentFilter($query);
        $this->applySearchFilter($query);
        $this->applyDateRangeFilter($query);
        $this->applyDuplicatesFilter($query);
    }

    private function applyStateFilter(Builder $query): void
    {
        if (! empty($this->filters['state'])) {
            $query->where('state', $this->filters['state']);
        }
    }

    private function applyPriorityFilter(Builder $query): void
    {
        if (! empty($this->filters['priority'])) {
            $query->where('priority', $this->filters['priority']);
        }
    }

    private function applyLocationFilter(Builder $query): void
    {
        if (! empty($this->filters['location_id'])) {
            $query->where('location_id', $this->filters['location_id']);
        }
    }

    private function applyCategoryFilter(Builder $query): void
    {
        if (! empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }
    }

    private function applyAssignmentFilter(Builder $query): void
    {
        $assignment = (string) ($this->filters['assignment'] ?? '');

        if ($assignment === '' || $assignment === 'all') {
            return;
        }

        if ($assignment === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignment === 'assigned') {
            $query->whereNotNull('assigned_to');
        } elseif ($assignment === 'mine') {
            $query->where('assigned_to', $this->user->id);
        }
    }

    private function applySearchFilter(Builder $query): void
    {
        if (empty($this->filters['search'])) {
            return;
        }

        $search = trim((string) $this->filters['search']);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($search): void {
            $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    private function applyDateRangeFilter(Builder $query): void
    {
        if (! empty($this->filters['from'])) {
            $query->whereDate('created_at', '>=', $this->filters['from']);
        }

        if (! empty($this->filters['to'])) {
            $query->whereDate('created_at', '<=', $this->filters['to']);
        }
    }

    private function applyDuplicatesFilter(Builder $query): void
    {
        if (empty($this->filters['duplicates'])) {
            return;
        }

        $query->whereHas('embedding', function (Builder $q): void {
            /** @phpstan-ignore-next-line */
            $q->effectiveDuplicates();
        });
    }

    /** @return string[] */
    private function resolveRelations(): array
    {
        return [
            'reporter',
            'assignee',
            'assignedBy',
            'location',
            'category',
            'embedding.matchedTicket',
        ];
    }
}
