<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporter\CancelReporterTicketRequest;
use App\Http\Requests\Reporter\UpdateReporterTicketRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\Queries\Tickets\ReporterTicketHistoryQuery;
use App\Queries\Tickets\ReporterTicketsBoardQuery;
use App\Queries\Tickets\ReporterTicketTrackingQuery;
use App\Services\Tickets\TicketCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reporter ticket surfaces (reporter-only role) — ALL LIVE DATA:
 *
 *  - index():   "Mis tickets" board — ReporterTicketsBoardQuery.
 *  - show():    "Ver seguimiento" — ReporterTicketTrackingQuery (resolves the
 *               ticket inside the reporter_id boundary, 404 otherwise).
 *  - history(): "Historial" — ReporterTicketHistoryQuery (own closed-out
 *               tickets: resolved/rejected, plus donut/average/monthly).
 *  - edit():    "Editar ticket" — form to update safe fields + add evidence.
 *  - update():  Persists safe fields and any new uploaded images atomically.
 *  - cancel():  "Cancelar solicitud" — open → cancelled via TicketCancellationService.
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
     * "Editar" — edit form for ONE of the reporter's own requests. The ticket is
     * resolved inside the reporter_id boundary (404 for a malformed id or any
     * ticket the reporter does not own) and TicketPolicy@update then enforces the
     * edit window (own + open + unassigned + unlocked) — a 403 for a ticket the
     * reporter owns but may no longer change (in_progress/resolved/rejected,
     * assigned or locked). NO state/assignment field is editable here.
     */
    public function edit(Request $request, string $ticket): View
    {
        $model = $this->resolveOwnTicket($request, $ticket);

        $this->authorize('update', $model);

        return view('tickets.reporter.edit', [
            'ticket' => $model->load(['location', 'category', 'media']),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'priorities' => ['low', 'medium', 'high', 'critical'],
        ]);
    }

    /**
     * Persist the reporter's edit. Same resolution + authorization gate as edit().
     *
     * Safe text fields (title, description, location_id, category_id, priority)
     * are updated. Any newly uploaded images (new_images[]) are stored to
     * 'public' disk under 'ticket-evidence/{ticket_id}/' and a TicketMedia record
     * is created for each. Existing evidence is NEVER deleted — this endpoint is
     * additive only. Both the model update and the media inserts happen inside a
     * single DB transaction so a partial failure rolls back cleanly.
     */
    public function update(UpdateReporterTicketRequest $request, string $ticket): RedirectResponse
    {
        $model = $this->resolveOwnTicket($request, $ticket);

        $this->authorize('update', $model);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        // Separate the file uploads from the scalar fields before passing to update().
        /** @var array<int, UploadedFile>|null $newImages */
        $newImages = $validated['new_images'] ?? null;
        unset($validated['new_images']);

        DB::transaction(function () use ($model, $validated, $newImages, $user): void {
            // 1. Update safe scalar fields.
            $model->update($validated);

            // 2. Store new images (additive — existing media is never touched).
            if (! empty($newImages)) {
                foreach ($newImages as $file) {
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        continue;
                    }

                    // Store to 'public' disk; path: ticket-evidence/{ticket_id}/{uuid}.{ext}
                    $path = $file->storeAs(
                        'ticket-evidence/'.$model->id,
                        Str::uuid().'.'.$file->extension(),
                        'public',
                    );

                    if ($path === false || $path === null) {
                        continue;
                    }

                    TicketMedia::create([
                        'ticket_id' => $model->id,
                        'file_url' => Storage::disk('public')->url((string) $path),
                        'file_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'uploaded_by' => $user->id,
                    ]);
                }
            }
        });

        return redirect()
            ->route('reporter.tickets.show', $model->id)
            ->with('status', 'Tu solicitud se actualizó correctamente.');
    }

    /**
     * "Cancelar solicitud" — the reporter voluntarily withdraws their OWN request
     * while it is still open and untouched by maintenance. Same resolution (404
     * for foreign/unknown) + authorization gate (TicketPolicy@cancelAsReporter,
     * 403 otherwise) as edit. The terminal open→cancelled transition and its
     * state_history entry are performed by TicketCancellationService. This is NOT
     * a rejection and NOT a delete — the ticket row is preserved.
     */
    public function cancel(CancelReporterTicketRequest $request, string $ticket, TicketCancellationService $cancellation): RedirectResponse
    {
        $model = $this->resolveOwnTicket($request, $ticket);

        $this->authorize('cancelAsReporter', $model);

        /** @var User $user */
        $user = $request->user();

        $cancellation->cancelByReporter($model, $user, $request->validated()['comment'] ?? null);

        return redirect()
            ->route('reporter.tickets.show', $model->id)
            ->with('status', 'Tu solicitud fue cancelada.');
    }

    /**
     * Resolve a ticket strictly inside the authenticated reporter's ownership
     * boundary. A non-UUID id or any ticket the reporter does not own yields 404
     * (so the route never reveals whether a foreign ticket exists). Editability
     * is a separate concern enforced by the caller via TicketPolicy@update.
     */
    private function resolveOwnTicket(Request $request, string $ticket): Ticket
    {
        /** @var User $user */
        $user = $request->user();

        if (! Str::isUuid($ticket)) {
            abort(404);
        }

        /** @var Ticket */
        return Ticket::query()
            ->where('reporter_id', (string) $user->id)
            ->findOrFail($ticket);
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
