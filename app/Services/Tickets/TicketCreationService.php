<?php

namespace App\Services\Tickets;

use App\Events\TicketCreated;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketCreationService
{
    public function __construct(
        private TicketQrLogger $logger,
        private TicketMediaStorageService $ticketMediaStorage,
    ) {}

    /**
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

            return $ticket;
        });

        // Disparar evento FUERA de la transacción
        // Disparar evento FUERA de la transacción
        Log::info('EVENTO TICKET CREATED DISPARADO', [
            'ticket_id' => $ticket->id,
            'time' => now()->toDateTimeString(),
        ]);
        event(new TicketCreated($ticket, $correlationId));

        $this->logger->info('ticket.creation.succeeded', [
            'ticket_id' => $ticket->id,
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'reporter_id' => $reporter->id,
            'correlation_id' => $correlationId,
            'state' => $ticket->state,
            'priority' => $ticket->priority,
        ]);

        $warning = $this->resolveDuplicateWarning($ticket);
        $warningPending = $warning === null && $this->isDedupEnabled() && $this->isAsyncProcessing();

        return [
            'created' => true,
            'ticket' => $ticket,
            'reason' => null,
            'warning' => $warning,
            'warning_pending' => $warningPending,
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDuplicateWarning(Ticket $ticket): ?array
    {
        $warning = null;

        if ($this->isDedupEnabled() && ! $this->isAsyncProcessing()) {
            $embedding = TicketEmbedding::query()
                ->with('matchedTicket')
                ->where('ticket_id', $ticket->id)
                ->first();

            if ($embedding && $embedding->is_duplicate) {
                /** @var Ticket|null $matched */
                $matched = $embedding->matchedTicket;

                if ($matched && in_array($matched->state, ['open', 'in_progress'], true)) {
                    $warning = [
                        'id' => $matched->id,
                        'title' => $matched->title,
                        'state' => $matched->state,
                        'created_at' => $matched->created_at?->toIso8601String(),
                        'similarity_score' => $embedding->similarity_score,
                    ];
                }
            }
        }

        return $warning;
    }

    private function isAsyncProcessing(): bool
    {
        if (! (bool) config('ai.automation.async_processing', true)) {
            return false;
        }

        return (string) config('queue.default') !== 'sync';
    }

    private function isDedupEnabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.dedup.enabled');
    }
}
