<?php

namespace App\Services\Tickets;

use App\Models\Category;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
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
     * $duplicateCandidate is the precheck candidate the reporter was warned
     * about and confirmed past (DuplicatePrecheckService::findMatch()). Null
     * when no candidate was found. Always server-derived — never built from
     * client input.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $mediaFiles
     * @param  array{ticket: Ticket, reason: string}|null  $duplicateCandidate
     * @return array{created: bool, ticket: Ticket, reason: string|null, warning: array<string, mixed>|null, warning_pending: bool}
     */
    public function create(
        User $reporter,
        array $payload,
        array $mediaFiles = [],
        string $correlationId = '',
        ?array $duplicateCandidate = null,
    ): array {
        $correlationId = $this->resolveCorrelationId($correlationId);

        $category = Category::findOrFail((string) $payload['category_id']);
        $requestedVisible = isset($payload['community_visible']) ? (bool) $payload['community_visible'] : null;
        $communityVisible = $category->resolveCommunityVisibility($requestedVisible);

        $ticket = DB::transaction(function () use ($reporter, $payload, $mediaFiles, $communityVisible, $duplicateCandidate): Ticket {
            $ticket = Ticket::create([
                'title' => (string) $payload['title'],
                'description' => (string) $payload['description'],
                'reporter_id' => $reporter->id,
                'location_id' => (string) $payload['location_id'],
                'category_id' => (string) $payload['category_id'],
                'state' => 'open',
                'priority' => (string) ($payload['priority'] ?? 'medium'),
                'community_visible' => $communityVisible,
            ]);

            StateHistory::create([
                'ticket_id' => $ticket->id,
                'from_state' => null,
                'to_state' => 'open',
                'changed_by' => $reporter->id,
                'comment' => 'Ticket creado',
            ]);

            $this->ticketMediaStorage->storeManyForTicket($ticket, $reporter, $mediaFiles);

            if ($duplicateCandidate !== null) {
                $this->persistDuplicatePrecheck($ticket, $duplicateCandidate);
            }

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

    /**
     * Records which candidate ticket the reporter was warned about and
     * confirmed past. Deliberately independent of the AI lane
     * (similarity_score/matched_ticket_id/is_duplicate/strategy_*), which
     * GenerateTicketEmbedding/DetectDuplicates write later and asynchronously
     * using unrelated criteria — see the 2026_07_01_000100 migration.
     *
     * embedding_vector has a NOT NULL constraint; [] is a placeholder until
     * the async embedding job fills in the real vector (description_hash
     * stays null, so that job always regenerates rather than trusting it).
     *
     * @param  array{ticket: Ticket, reason: string}  $duplicateCandidate
     */
    private function persistDuplicatePrecheck(Ticket $ticket, array $duplicateCandidate): void
    {
        TicketEmbedding::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'embedding_vector' => [],
                'precheck_matched_ticket_id' => $duplicateCandidate['ticket']->id,
                'precheck_reason' => $duplicateCandidate['reason'],
                'precheck_confirmed_at' => now(),
            ]
        );
    }
}
