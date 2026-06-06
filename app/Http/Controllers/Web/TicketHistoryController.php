<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Tickets\HistoryBoardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Historial" — read-only history board for the maintenance role: the
 * technician's own closed-out tickets (resolved / rejected) plus period
 * analytics (donut, average resolution time, top labs, recent activity).
 *
 * Reads live data through HistoryBoardQuery, which enforces the ownership
 * boundary (assigned_to = the authenticated technician, state in
 * resolved/rejected) BEFORE any chip, filter, date range or page parameter is
 * applied. The route middleware (auth + role:maintenance) is the first gate;
 * the query is defence in depth. This endpoint is read-only and the "Exportar"
 * button remains a visual placeholder (no real export yet).
 */
class TicketHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->resolveChoice((string) $request->query('result', 'all'), HistoryBoardQuery::RESULTS, 'all');
        $sort = $this->resolveChoice((string) $request->query('sort', 'recent'), HistoryBoardQuery::SORTS, 'recent');

        $board = HistoryBoardQuery::for($user, $this->filters($request), $result, $sort);

        return view('tickets.history', ['board' => $board]);
    }

    /**
     * Whitelisted, trimmed filter inputs. Unknown keys are ignored; the query
     * applies them only inside the technician's own-history boundary.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'priority' => (string) $request->query('priority', ''),
            'location_id' => (string) $request->query('location_id', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'page' => max(1, (int) $request->query('page', 1)),
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
