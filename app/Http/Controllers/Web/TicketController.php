<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\DispatchesTicketCreatedAfterResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\ReviewDuplicateRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Http\Requests\UpdateTicketStateRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Tickets\MaintenanceBoardQuery;
use App\Queries\Tickets\TicketIndexQuery;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketAssignmentService;
use App\Services\Tickets\TicketCreationService;
use App\Services\Tickets\TicketStateService;
use App\Support\Tickets\DuplicateExplanationPresenter;
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

        $user = $request->user();

        // Maintenance technicians get the operational, prioritised board;
        // reporters and admins keep the classic table below.
        if (
            $user instanceof User
            && $user->hasRole('maintenance')
            && ! $user->hasAnyRole(['admin', 'super_admin'])
        ) {
            return $this->maintenanceBoard($request, $user);
        }

        $filters = $request->validated();

        $query = TicketIndexQuery::build($user, $filters);

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
            'media' => fn ($query) => $query->with('uploadedBy')->latest('created_at'),
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

        // Limited operational edit (category/priority/evidence) from this view.
        $canEditOperational = $currentUser instanceof User
            && $currentUser->can('updateMaintenance', $ticket);

        return view('tickets.show', [
            'ticket' => $ticket,
            'availableTransitions' => $availableTransitions,
            'maintenanceUsers' => $maintenanceUsers,
            'isMaintenance' => $isMaintenance,
            'isAvailableForClaim' => $isAvailableForClaim,
            'canEditOperational' => $canEditOperational,
            'categories' => $canEditOperational
                ? Category::query()->orderBy('name', 'asc')->get()
                : collect(),
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'duplicateExplanation' => DuplicateExplanationPresenter::present($ticket),
        ]);
    }

    /**
     * PATCH /tickets/{ticket}/maintenance
     * Limited operational edit from tickets.show: the assigned maintenance
     * technician (or admin/super_admin) corrects category/priority, attaches
     * additional evidence and/or leaves a technical comment. Reporter fields
     * (title, description, reporter_id) are never touched: the FormRequest
     * does not accept them and nothing else is written to the model.
     */
    public function updateMaintenance(
        UpdateMaintenanceTicketRequest $request,
        Ticket $ticket,
        TicketMediaStorageService $mediaStorage
    ): RedirectResponse {
        $this->authorize('updateMaintenance', $ticket);

        $user = $request->user();
        $validated = $request->validated();
        $files = $request->file('evidence', []);
        $comment = trim((string) ($validated['comment'] ?? ''));

        $changeDescriptions = [];

        $newCategoryId = $validated['category_id'] ?? null;
        if ($newCategoryId !== null && $newCategoryId !== $ticket->category_id) {
            $oldCategoryName = $ticket->category?->name ?? 'Sin categoría';
            $newCategoryName = Category::query()->find($newCategoryId)?->name ?? $newCategoryId;
            $ticket->category_id = $newCategoryId;
            $changeDescriptions[] = "categoría corregida de «{$oldCategoryName}» a «{$newCategoryName}»";
        }

        $newPriority = $validated['priority'] ?? null;
        if ($newPriority !== null && $newPriority !== $ticket->priority) {
            $oldPriority = (string) $ticket->priority;
            $ticket->priority = $newPriority;
            $changeDescriptions[] = "prioridad corregida de «{$oldPriority}» a «{$newPriority}»";
        }

        if ($changeDescriptions === [] && $files === [] && $comment === '') {
            return redirect()
                ->route('tickets.show', $ticket)
                ->with('status', 'No hay cambios para guardar.');
        }

        DB::transaction(function () use ($ticket, $user, $changeDescriptions, $files, $comment): void {
            if ($ticket->isDirty()) {
                $ticket->save();
            }

            $historyParts = [];
            if ($changeDescriptions !== []) {
                $historyParts[] = 'Actualización técnica: '.implode('; ', $changeDescriptions).'.';
            }
            if ($files !== []) {
                $historyParts[] = 'Evidencia agregada por maintenance ('.count($files).' archivo(s)).';
            }
            if ($comment !== '') {
                $historyParts[] = 'Comentario: '.$comment;
            }

            // Administrative entry: same state on both sides, no transition.
            StateHistory::create([
                'ticket_id' => $ticket->id,
                'from_state' => $ticket->state,
                'to_state' => $ticket->state,
                'changed_by' => $user->id,
                'comment' => implode(' ', $historyParts),
            ]);
        });

        if ($files !== []) {
            $mediaStorage->storeManyForTicket($ticket, $user, $files);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Cambios guardados correctamente.');
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
     * Operational, prioritised board for maintenance technicians (the V2 view).
     * Read-only: claim/manage are delegated to the existing ticket routes.
     */
    private function maintenanceBoard(ListTicketsRequest $request, User $user): View
    {
        $board = MaintenanceBoardQuery::for(
            $user,
            $request->validated(),
            $this->resolveBoardView($request),
        );

        return view('tickets.maintenance-v2', [
            'board' => $board,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    private function resolveBoardView(ListTicketsRequest $request): string
    {
        $view = (string) $request->query('view', 'all');

        return in_array($view, MaintenanceBoardQuery::VIEWS, true) ? $view : 'all';
    }

    /** @param array<string, mixed> $filters */
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

        if (! empty($filters['duplicates'])) {
            $query->whereHas('embedding', function (Builder $q): void {
                /** @phpstan-ignore-next-line */
                $q->effectiveDuplicates();
            });
        }
    }
}
