<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DispatchesTicketCreatedAfterResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\ReviewDuplicateRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStateRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketAssignmentService;
use App\Services\Tickets\TicketCreationService;
use App\Services\Tickets\TicketStateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class TicketController extends Controller
{
    use DispatchesTicketCreatedAfterResponse;

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $filters = $request->validated();

        // Eager-load embedding and matchedTicket to expose duplicate data without N+1
        $query = Ticket::query()->with([
            'reporter',
            'assignee',
            'location',
            'category',
            'embedding.matchedTicket',
        ]);

        // Reporter-role scope: restrict to own tickets BEFORE any user-supplied filters
        // so that query params (search, duplicates, location, etc.) cannot leak foreign tickets.
        if ($user instanceof User
            && $user->hasRole('reporter')
            && ! $user->hasAnyRole(['maintenance', 'admin', 'super_admin'])
        ) {
            $query->reportedBy($user->id);
        }

        // maintenance-only scope: restrict index to tickets assigned to them
        // or tickets that are open and unassigned (claim queue).
        // Mirrors Web controller to maintain API parity.
        if ($user instanceof User
            && $user->hasRole('maintenance')
            && ! $user->hasAnyRole(['admin', 'super_admin'])
        ) {
            $query->visibleToMaintenance($user->id);
        }

        $this->applyFilters($query, $filters);

        $tickets = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request, TicketCreationService $creationService): JsonResponse
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
            $correlationId
        );
        $ticket = $result['ticket']->load([
            'reporter',
            'assignee',
            'location',
            'category',
            'media',
            'embedding.matchedTicket',
        ]);
        $this->dispatchAfterResponse($ticket, $correlationId);
        $warning = $result['warning'] ?? null;
        $warningPending = $this->isDedupEnabled();

        $message = 'Ticket creado correctamente.';
        if (is_array($warning)) {
            $message = 'Ticket creado correctamente, pero se detecto un posible duplicado.';
        } elseif ($warningPending) {
            $message = 'Ticket creado correctamente. La verificacion de duplicados esta en proceso.';
        }

        return response()->json([
            'message' => $message,
            'duplicate' => false,
            'duplicate_warning' => is_array($warning),
            'duplicate_warning_pending' => ! is_array($warning) && $warningPending,
            'similar_ticket' => $warning,
            'data' => (new TicketResource($ticket))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'reporter',
            'assignee',
            'location',
            'category',
            'media' => fn ($query) => $query->latest('created_at'),
            'stateHistory' => fn ($query) => $query->latest('created_at'),
            'embedding.matchedTicket',
            'embedding.reviewer',
        ]);

        return response()->json((new TicketResource($ticket))->resolve($request));
    }

    public function destroy(Ticket $ticket, TicketMediaStorageService $mediaStorage): JsonResponse
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

        return response()->json([
            'message' => 'Ticket eliminado correctamente.',
        ]);
    }

    public function updateState(
        UpdateTicketStateRequest $request,
        Ticket $ticket,
        TicketStateService $stateService,
        TicketQrLogger $logger,
    ): JsonResponse {
        $this->authorize('updateState', $ticket);

        $correlationId = (string) $request->attributes->get('correlation_id', '');
        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
            $request->attributes->set('correlation_id', $correlationId);
        }

        $validated = $request->validated();
        $toState = (string) ($validated['to_state'] ?? '');
        $comment = $validated['comment'] ?? null;

        try {
            $updatedTicket = $stateService->transition(
                $ticket,
                $request->user(),
                $toState,
                is_string($comment) ? $comment : null,
                $correlationId,
            );
        } catch (InvalidArgumentException $exception) {
            $logger->warning('ticket.state.transition_denied', [
                'ticket_id' => $ticket->id,
                'location_id' => $ticket->location_id,
                'category_id' => $ticket->category_id,
                'actor_id' => $request->user()?->id,
                'correlation_id' => $correlationId,
                'from_state' => $ticket->state,
                'to_state' => $toState,
                'reason' => $exception->getMessage(),
                'comment' => $comment,
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'to_state' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Estado del ticket actualizado correctamente.',
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ]);
    }

    public function claim(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): JsonResponse {
        $this->authorize('claim', $ticket);

        try {
            $updatedTicket = $assignmentService->claimByMaintenance($ticket, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 422);
        } catch (AuthorizationException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 403);
        }

        $this->loadAssignmentRelations($updatedTicket);

        return response()->json([
            'message' => 'Ticket tomado correctamente.',
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ]);
    }

    public function release(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): JsonResponse {
        $this->authorize('release', $ticket);

        try {
            $updatedTicket = $assignmentService->releaseByMaintenance($ticket, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 422);
        } catch (AuthorizationException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 403);
        }

        $this->loadAssignmentRelations($updatedTicket);

        return response()->json([
            'message' => 'Ticket liberado correctamente.',
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ]);
    }

    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): JsonResponse {
        $this->authorize('assign', $ticket);

        $assignedTo = (string) $request->validated('assigned_to');
        $target = User::query()->findOrFail($assignedTo);

        try {
            if ($ticket->assigned_to === null) {
                $updatedTicket = $assignmentService->assignByAdmin($ticket, $request->user(), $target);
            } else {
                $updatedTicket = $assignmentService->reassignByAdmin($ticket, $request->user(), $target);
            }
        } catch (InvalidArgumentException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assigned_to', 422);
        } catch (AuthorizationException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 403);
        }

        $this->loadAssignmentRelations($updatedTicket);

        return response()->json([
            'message' => 'Asignacion actualizada correctamente.',
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ]);
    }

    public function unassign(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService
    ): JsonResponse {
        $this->authorize('unassign', $ticket);

        try {
            $updatedTicket = $assignmentService->unassignByAdmin($ticket, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 422);
        } catch (AuthorizationException $exception) {
            return $this->assignmentErrorResponse($exception->getMessage(), 'assignment', 403);
        }

        $this->loadAssignmentRelations($updatedTicket);

        return response()->json([
            'message' => 'Asignacion eliminada correctamente.',
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ]);
    }

    /**
     * PATCH /api/tickets/{ticket}/duplicate-review
     * Allows maintenance/admin/super_admin to confirm or dismiss an AI duplicate.
     */
    public function reviewDuplicate(ReviewDuplicateRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reviewDuplicate', $ticket);

        $embedding = $ticket->embedding;

        if (! $embedding) {
            return response()->json([
                'message' => 'Este ticket aún no tiene análisis de duplicados generado por la IA.',
                'errors' => ['review' => ['Sin embedding disponible.']],
            ], 422);
        }

        $validated = $request->validated();

        // Update only human-review columns — AI columns are intentionally untouched
        $embedding->review_status = $validated['review_status'];
        $embedding->reviewed_by = $request->user()->id;
        $embedding->reviewed_at = now();
        $embedding->review_note = $validated['review_note'] ?? null;
        $embedding->save();

        // Reload relations for the resource response
        $ticket->load([
            'reporter',
            'assignee',
            'location',
            'category',
            'embedding.matchedTicket',
            'embedding.reviewer',
        ]);

        return response()->json([
            'message' => 'Revisión de duplicado actualizada.',
            'data' => (new TicketResource($ticket))->resolve($request),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
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

    private function loadAssignmentRelations(Ticket $ticket): Ticket
    {
        return $ticket->load(['reporter', 'assignee', 'location', 'category']);
    }

    private function assignmentErrorResponse(string $message, string $field, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], $status);
    }
}
