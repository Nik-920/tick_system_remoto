<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStateRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Observability\TicketQrLogger;
use App\Services\Tickets\TicketCreationService;
use App\Services\Tickets\TicketStateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->validated();
        $query = Ticket::query()->with(['reporter', 'assignee', 'location', 'category']);
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

        $result = $creationService->create($request->user(), $request->validated(), $correlationId);
        $ticket = $result['ticket']->load(['reporter', 'assignee', 'location', 'category']);

        if (! $result['created']) {
            return response()->json([
                'message' => 'Se detecto un ticket activo para la misma ubicacion y categoria.',
                'duplicate' => true,
                'data' => (new TicketResource($ticket))->resolve($request),
            ]);
        }

        return response()->json([
            'message' => 'Ticket creado correctamente.',
            'duplicate' => false,
            'data' => (new TicketResource($ticket))->resolve($request),
        ], 201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'reporter',
            'assignee',
            'location',
            'category',
            'stateHistory' => fn ($query) => $query->latest('created_at'),
        ]);

        return new TicketResource($ticket);
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

    /**
     * @param array<string, mixed> $filters
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
    }
}
