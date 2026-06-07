<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Tickets\ReporterTicketHistoryQuery;
use App\Queries\Tickets\ReporterTicketsBoardQuery;
use App\Queries\Tickets\ReporterTicketTrackingQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reporter ticket surfaces (reporter-only role) — ALL LIVE DATA:
 *
 *  - index():   "Mis tickets" board — ReporterTicketsBoardQuery.
 *  - show():    "Ver seguimiento" — ReporterTicketTrackingQuery (resolves the
 *               ticket inside the reporter_id boundary, 404 otherwise).
 *  - history(): "Historial" — ReporterTicketHistoryQuery (own closed-out
 *               tickets: resolved/rejected, plus donut/average/monthly).
 *
 * Every screen applies the reporter_id ownership scope FIRST so no parameter can
 * leak another reporter's tickets. NO maintenance action (Tomar, Iniciar,
 * Liberar, Resolver) is exposed. The route middleware (auth + role:reporter) is
 * the access gate; the classic /tickets route stays intact.
 */
class ReporterTicketController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $status = $this->resolveChoice((string) $request->query('status', 'all'), ReporterTicketsBoardQuery::STATUSES, 'all');
        $sort = $this->resolveChoice((string) $request->query('sort', 'recent'), ReporterTicketsBoardQuery::SORTS, 'recent');

        $board = ReporterTicketsBoardQuery::for($user, $this->filters($request), $status, $sort);

        return view('tickets.reporter.index', ['board' => $board]);
    }

    /**
     * Whitelisted, trimmed filter inputs. Unknown keys are ignored; the query
     * applies them only INSIDE the reporter's own-tickets boundary.
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

    /**
     * "Ver seguimiento" — real tracking screen for ONE of the reporter's own
     * tickets. ReporterTicketTrackingQuery resolves it inside the reporter_id
     * boundary (404 for a malformed id or any ticket the reporter does not own)
     * and shapes the stepper / timeline / details / evidence from real data.
     */
    public function show(Request $request, string $ticket): View
    {
        /** @var User $user */
        $user = $request->user();

        $tracking = ReporterTicketTrackingQuery::for($user, $ticket);

        return view('tickets.reporter.show', ['tracking' => $tracking]);
    }

    /**
     * "Historial" — LIVE board of the reporter's own closed-out tickets
     * (resolved / rejected). ReporterTicketHistoryQuery applies the reporter_id
     * ownership scope first; the result chip, filters, search, sort, date range
     * and page narrow only inside it. Export remains a visual placeholder.
     */
    public function history(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->resolveChoice((string) $request->query('status', 'all'), ReporterTicketHistoryQuery::RESULTS, 'all');
        $sort = $this->resolveChoice((string) $request->query('sort', 'recent'), ReporterTicketHistoryQuery::SORTS, 'recent');

        $board = ReporterTicketHistoryQuery::for($user, $this->filters($request), $result, $sort);

        return view('tickets.reporter.history', ['board' => $board]);
    }
}
