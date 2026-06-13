<?php

namespace App\Services\Tickets;

use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Concerns\ResolvesCorrelationId;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketCreationService
{
    use ResolvesCorrelationId;

    public function __construct(
        private TicketQrLogger $logger,
        private TicketMediaStorageService $ticketMediaStorage,
    ) {}

    /**
     * Creates and persists a ticket.
     *
     * Observer note:
     * This service intentionally does not dispatch TicketCreated directly.
     * HTTP controllers dispatch TicketCreated through DispatchesTicketCreatedAfterResponse
     * after the response is sent, so downstream observers run after the ticket exists
     * and without adding latency to the request.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $mediaFiles
     * @return array{created: bool, ticket: Ticket, reason: string|null, warning: array<string, mixed>|null, warning_pending: bool}
     */
    public function create(
        User $reporter,
        array $payload,
        array $mediaFiles = [],
        string $correlationId = ''
    ): array {
        $correlationId = $this->resolveCorrelationId($correlationId);

        $ticket = DB::transaction(function () use ($reporter, $payload, $mediaFiles): Ticket {
            $ticket = Ticket::create([
                'title' => (string) $payload['title'],
                'description' => (string) $payload['description'],
                'reporter_id' => $reporter->id,
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

            $this->ticketMediaStorage->storeManyForTicket($ticket, $reporter, $mediaFiles);

            return $ticket;
        });

        $this->logger->info('ticket.creation.succeeded', [
            'ticket_id' => $ticket->id,
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'reporter_id' => $reporter->id,
            'correlation_id' => $correlationId,
            'state' => $ticket->state,
            'priority' => $ticket->priority,
        ]);

        return [
            'created' => true,
            'ticket' => $ticket,
            'reason' => null,
            'warning' => null,
            'warning_pending' => false,
        ];
    }
}
