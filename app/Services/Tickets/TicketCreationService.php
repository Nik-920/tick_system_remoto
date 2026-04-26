<?php

namespace App\Services\Tickets;

use App\Events\TicketCreated;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\DeduplicationService;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketCreationService
{
    public function __construct(
        private DeduplicationService $deduplication,
        private TicketQrLogger $logger,
        private TicketMediaStorageService $ticketMediaStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $mediaFiles
     * @return array{created: bool, ticket: Ticket, reason: string|null}
     */
    public function create(
        User $reporter,
        array $payload,
        array $mediaFiles = [],
        string $correlationId = ''
    ): array {
        $correlationId = $this->resolveCorrelationId($correlationId);

        $existing = $this->findExistingTicket((string) $payload['location_id'], (string) $payload['category_id']);
        if ($existing !== null) {
            $this->logger->info('ticket.creation.duplicate_detected', [
                'ticket_id' => $existing->id,
                'location_id' => $existing->location_id,
                'category_id' => $existing->category_id,
                'reporter_id' => $reporter->id,
                'correlation_id' => $correlationId,
                'reason' => 'active_ticket_exists',
            ]);

            return [
                'created' => false,
                'ticket' => $existing,
                'reason' => 'duplicate',
            ];
        }

        $ticket = DB::transaction(function () use ($reporter, $payload, $mediaFiles, $correlationId): Ticket {
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

            $this->ticketMediaStorage->storeManyForTicket($ticket, $reporter, $mediaFiles);

            event(new TicketCreated($ticket, $correlationId));

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

    private function resolveCorrelationId(string $correlationId): string
    {
        $trimmed = trim($correlationId);
        if ($trimmed !== '') {
            return $trimmed;
        }

        if (app()->bound('request')) {
            $request = request();
            if ($request instanceof Request) {
                $fromAttribute = trim((string) $request->attributes->get('correlation_id', ''));
                if ($fromAttribute !== '') {
                    return $fromAttribute;
                }

                $fromHeader = trim((string) $request->headers->get('X-Correlation-Id', ''));
                if ($fromHeader !== '') {
                    $request->attributes->set('correlation_id', $fromHeader);

                    return $fromHeader;
                }
            }
        }

        return (string) Str::uuid();
    }
}
