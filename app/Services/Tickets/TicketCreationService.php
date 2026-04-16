<?php

namespace App\Services\Tickets;

use App\Events\TicketCreated;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\DeduplicationService;
use Illuminate\Support\Facades\DB;

class TicketCreationService
{
    public function __construct(private DeduplicationService $deduplication)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{created: bool, ticket: Ticket, reason: string|null}
     */
    public function create(User $reporter, array $payload): array
    {
        $existing = $this->findExistingTicket((string) $payload['location_id'], (string) $payload['category_id']);
        if ($existing !== null) {
            return [
                'created' => false,
                'ticket' => $existing,
                'reason' => 'duplicate',
            ];
        }

        $ticket = DB::transaction(function () use ($reporter, $payload): Ticket {
            $ticket = Ticket::create([
                'title' => (string) $payload['title'],
                'description' => (string) $payload['description'],
                'reporter_id' => $reporter->id,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'location_id' => (string) $payload['location_id'],
                'category_id' => (string) $payload['category_id'],
                'state' => 'open',
                'priority' => (string) ($payload['priority'] ?? 'medium'),
            ]);

            StateHistory::create([
                'ticket_id' => $ticket->id,
                'from_state' => null,
                'to_state' => 'open',
                'changed_by' => $reporter->id,
                'comment' => 'Ticket creado',
            ]);

            event(new TicketCreated($ticket));

            return $ticket;
        });

        return [
            'created' => true,
            'ticket' => $ticket,
            'reason' => null,
        ];
    }

    private function findExistingTicket(string $locationId, string $categoryId): ?Ticket
    {
        return Ticket::query()
            ->where('location_id', $locationId)
            ->where('category_id', $categoryId)
            ->whereIn('state', ['open', 'in_progress'])
            ->where('created_at', '>=', now()->subHours($this->deduplication->windowHours()))
            ->latest('created_at')
            ->first();
    }
}
