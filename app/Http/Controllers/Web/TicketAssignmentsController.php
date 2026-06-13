<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Tickets\AssignmentsBoardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mis asignaciones" — operational board for the maintenance role listing the
 * tickets currently assigned to the technician, with a detail rail (assignment
 * detail, progress stepper, recent activity).
 *
 * Reads live data through AssignmentsBoardQuery, which enforces the ownership
 * boundary (assigned_to = the authenticated technician) BEFORE any tab, filter
 * or focus parameter is applied. The route middleware (auth + role:maintenance)
 * is the first gate; the query is defence in depth. This endpoint is read-only.
 */
class TicketAssignmentsController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $tab = $this->resolveChoice((string) $request->query('tab', 'active'), AssignmentsBoardQuery::TABS, 'active');
        $sort = $this->resolveChoice((string) $request->query('sort', 'recent'), AssignmentsBoardQuery::SORTS, 'recent');

        $board = AssignmentsBoardQuery::for($user, $this->filters($request), $tab, $sort);

        return view('tickets.assignments', ['board' => $board]);
    }

    /**
     * Whitelisted, trimmed filter inputs. Unknown keys are ignored; the query
     * applies them only inside the technician's own-tickets boundary.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'state' => (string) $request->query('state', ''),
            'priority' => (string) $request->query('priority', ''),
            'location_id' => (string) $request->query('location_id', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'focus' => (string) $request->query('focus', ''),
            'all' => $request->boolean('all'),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function resolveChoice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
