<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\DispatchesTicketCreatedAfterResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\ReviewDuplicateRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStateRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketAssignmentService;
use App\Services\Tickets\TicketCreationService;
use App\Services\Tickets\TicketStateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class TicketController extends Controller
{
    use DispatchesTicketCreatedAfterResponse;

    public function index(ListTicketsRequest $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->validated();
        $user = $request->user();

        // Eager-load embedding and matchedTicket to show duplicate badge without N+1
        $query = Ticket::query()->with([
            'reporter',
            'assignee',
            'assignedBy',
            'location',
            'category',
            'embedding.matchedTicket',
        ]);

        if ($user instanceof User && $user->hasRole('reporter') && ! $user->hasAnyRole(['maintenance', 'admin', 'super_admin'])) {
            $query->reportedBy($user->id);
        }

        // maintenance-only scope: restrict index to tickets assigned to them
        // or tickets that are open and unassigned (claim queue).
        // Applied BEFORE user-supplied filters so that search/location/etc.
        // cannot leak tickets outside this boundary.
        if ($user instanceof User
            && $user->hasRole('maintenance')
            && ! $user->hasAnyRole(['admin', 'super_admin'])
        ) {
            $query->visibleToMaintenance($user->id);
        }

        $this->applyFilters($query, $filters, $user);

        $tickets = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'locations' => Location::query()->active()->orderBy('name', 'asc')->get(),
            'categories' => Category::query()->orderBy('name', 'asc')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);

        $requestedLocationId = (string) $request->query('location_id', '');
        $selectedLocationId = null;

        if ($requestedLocationId !== '') {
            $exists = Location::query()
                ->where('id', $requestedLocationId)
                ->active()
                ->exists();

            if ($exists) {
                $selectedLocationId = $requestedLocationId;
            }
        }

        return view('tickets.create', [
            'locations' => Location::query()->active()->orderBy('name', 'asc')->get(),
            'categories' => Category::query()->orderBy('name', 'asc')->get(),
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'selectedLocationId' => $selectedLocationId,
        ]);
    }

    public function available(ListTicketsRequest $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->validated();
        unset($filters['state'], $filters['assignment']);

        $query = Ticket::query()
            ->availableForClaim()
            ->with([
                'reporter',
                'assignee',
                'assignedBy',
                'location',
                'category',
                'embedding.matchedTicket',
            ]);

        $this->applyFilters($query, $filters, $request->user());

        $tickets = $query
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->oldest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('tickets.available', [
            'tickets' => $tickets,
            'filters' => $filters,
            'locations' => Location::query()->active()->orderBy('name', 'asc')->get(),
            'categories' => Category::query()->orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(StoreTicketRequest $request, TicketCreationService $creationService): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $correlationId = (string) $request->attributes->get('correlation_id', '');
        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
            $request->attributes->set('correlation_id', $correlationId);
        }

        $result = $creationService->create(
            $request->user(),
            $request->validated(),
            $request->file('media_files', []),
            $correlationId,
        );
        $ticket = $result['ticket'];
        $this->dispatchAfterResponse($ticket, $correlationId);
        $warning = $result['warning'] ?? null;
        $warningPending = $this->isDedupEnabled();

        $message = 'Ticket creado correctamente.';
        if (is_array($warning)) {
            $message = 'Ticket creado correctamente, pero se detecto un posible duplicado.';
        } elseif ($warningPending) {
            $message = 'Ticket creado correctamente. La verificacion de duplicados esta en proceso.';
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', $message);
    }

    public function show(Ticket $ticket, TicketStateService $ticketStateService): View
    {
        $this->authorize('view', $ticket);

        $currentUser = request()->user();
        $maintenanceUsers = collect();
        if ($currentUser instanceof User && $currentUser->hasAnyRole(['admin', 'super_admin'])) {
            $maintenanceUsers = User::role('maintenance')
                ->orderBy('name')
                ->get();
        }

        $ticket->load([
            'reporter',
            'assignee',
            'assignedBy',
            'location',
            'category',
            'media' => fn ($query) => $query->latest('created_at'),
            'stateHistory' => fn ($query) => $query->with('changedBy')->oldest('created_at'),
            'embedding.matchedTicket',
            'embedding.reviewer',
        ]);

        $availableTransitions = $currentUser instanceof User
            ? $ticketStateService->availableTransitionsFor($ticket, $currentUser)
            : [];

        $isMaintenance = $currentUser instanceof User
            && $currentUser->hasRole('maintenance')
            && ! $currentUser->hasAnyRole(['admin', 'super_admin']);

        $isAvailableForClaim = $isMaintenance
            && $ticket->state === Ticket::STATE_OPEN
            && $ticket->assigned_to === null
            && ! $ticket->assignment_locked;

        return view('tickets.show', [
            'ticket' => $ticket,
            'availableTransitions' => $availableTransitions,
            'maintenanceUsers' => $maintenanceUsers,
            'isMaintenance' => $isMaintenance,
            'isAvailableForClaim' => $isAvailableForClaim,
        ]);
    }

    public function destroy(Ticket $ticket, TicketMediaStorageService $mediaStorage): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticketId = $ticket->id;
        $mediaUrls = $ticket->media()->pluck('file_url')->all();

        DB::transaction(function () use ($ticket): void {
            Ticket::query()->whereKey($ticket->id)->delete();
        });

        try {
            $mediaStorage->deleteManyByUrls($mediaUrls);
        } catch (Throwable $exception) {
            Log::warning('No fue posible eliminar adjuntos del ticket en storage.', [
                'ticket_id' => $ticketId,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('tickets.index')
            ->with('status', 'Ticket eliminado correctamente.');
    }

    public function updateState(
        UpdateTicketStateRequest $request,
        Ticket $ticket,
        TicketStateService $stateService
    ): RedirectResponse {
        $this->authorize('updateState', $ticket);

        try {
            $stateService->transition(
                $ticket,
                $request->user(),
                (string) $request->validated('to_state'),
                $request->validated('comment')
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['to_state' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Estado del ticket actualizado correctamente.');
    }

    public function claim(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): RedirectResponse {
        $this->authorize('claim', $ticket);

        try {
            $assignmentService->claimByMaintenance($ticket, $request->user());
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['assignment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket tomado correctamente.');
    }

    public function release(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): RedirectResponse {
        $this->authorize('release', $ticket);

        try {
            $assignmentService->releaseByMaintenance($ticket, $request->user());
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['assignment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket liberado correctamente.');
    }

    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): RedirectResponse {
        $this->authorize('assign', $ticket);

        $assignedTo = (string) $request->validated('assigned_to');
        $target = User::query()->findOrFail($assignedTo);

        try {
            if ($ticket->assigned_to === null) {
                $assignmentService->assignByAdmin($ticket, $request->user(), $target);
            } else {
                $assignmentService->reassignByAdmin($ticket, $request->user(), $target);
            }
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['assigned_to' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Asignacion actualizada correctamente.');
    }

    public function unassign(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): RedirectResponse {
        $this->authorize('unassign', $ticket);

        try {
            $assignmentService->unassignByAdmin($ticket, $request->user());
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['assignment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Asignacion eliminada correctamente.');
    }

    /**
     * PATCH /tickets/{ticket}/duplicate-review
     * Allows maintenance/admin/super_admin to confirm or dismiss an AI duplicate.
     */
    public function reviewDuplicate(ReviewDuplicateRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reviewDuplicate', $ticket);

        $embedding = $ticket->embedding;

        if (! $embedding) {
            return back()->withErrors([
                'review' => 'Este ticket aún no tiene análisis de duplicados generado por la IA.',
            ]);
        }

        $validated = $request->validated();

        // Update only human-review columns — AI columns are intentionally untouched
        $embedding->review_status = $validated['review_status'];
        $embedding->reviewed_by = $request->user()->id;
        $embedding->reviewed_at = now();
        $embedding->review_note = $validated['review_note'] ?? null;
        $embedding->save();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Revisión de duplicado actualizada.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, ?User $user = null): void
    {
        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['assignment']) && $filters['assignment'] !== 'all') {
            $assignment = (string) $filters['assignment'];
            if ($assignment === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($assignment === 'assigned') {
                $query->whereNotNull('assigned_to');
            } elseif ($assignment === 'mine' && $user instanceof User) {
                $query->where('assigned_to', $user->id);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        // Duplicate filter: effective_duplicate = true (sql-equivalent)
        if (! empty($filters['duplicates'])) {
            $query->whereHas('embedding', function (Builder $q): void {
                /** @phpstan-ignore-next-line */
                $q->effectiveDuplicates();
            });
        }
    }
}
