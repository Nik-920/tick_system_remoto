<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStateRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Services\Tickets\TicketCreationService;
use App\Services\Tickets\TicketStateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function index(ListTicketsRequest $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->validated();
        $query = Ticket::query()->with(['reporter', 'assignee', 'location', 'category']);
        $this->applyFilters($query, $filters);

        $tickets = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
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
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                $selectedLocationId = $requestedLocationId;
            }
        }

        return view('tickets.create', [
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'selectedLocationId' => $selectedLocationId,
        ]);
    }

    public function store(StoreTicketRequest $request, TicketCreationService $creationService): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $result = $creationService->create(
            $request->user(),
            $request->validated(),
            $request->file('media_files', [])
        );
        $ticket = $result['ticket'];

        if (! $result['created']) {
            return redirect()
                ->route('tickets.show', $ticket)
                ->with('status', 'Se detecto un ticket activo para la misma ubicacion y categoria.');
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket creado correctamente.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'reporter',
            'assignee',
            'location',
            'category',
            'media' => fn ($query) => $query->latest('created_at'),
            'stateHistory' => fn ($query) => $query->latest('created_at'),
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'states' => ['open', 'in_progress', 'resolved', 'rejected'],
        ]);
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
