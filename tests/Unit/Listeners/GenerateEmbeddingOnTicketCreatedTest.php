<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketCreated;
use App\Jobs\GenerateTicketEmbedding;
use App\Listeners\GenerateEmbeddingOnTicketCreated;
use App\Models\Ticket;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GenerateEmbeddingOnTicketCreatedTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Caso normal: ai + huggingface habilitados, async → dispatch
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_async_when_ai_and_huggingface_enabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket('Proyector roto en sala 201');
        $event = new TicketCreated($ticket, 'corr-emb-001');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertDispatched(GenerateTicketEmbedding::class, function (GenerateTicketEmbedding $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-emb-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // async_processing = false → dispatchSync, no async
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_sync_when_async_processing_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => false,
        ]);

        $ticket = $this->makeTicket('Falla en red del laboratorio');
        $event = new TicketCreated($ticket, 'corr-emb-sync-001');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertDispatchedSync(GenerateTicketEmbedding::class, function (GenerateTicketEmbedding $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-emb-sync-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // ai.enabled = false → early return, sin dispatch
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_ai_globally_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => false,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $event = new TicketCreated($this->makeTicket('Descripcion valida'), 'corr-emb-002');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // ai.huggingface.enabled = false → early return, sin dispatch
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_huggingface_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => false,
            'ai.automation.async_processing' => true,
        ]);

        $event = new TicketCreated($this->makeTicket('Descripcion valida'), 'corr-emb-003');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // description vacía → early return, sin dispatch
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_description_is_empty(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket('');
        $event = new TicketCreated($ticket, 'corr-emb-004');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // description sólo espacios → trim() → vacía → early return
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_description_is_only_whitespace(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket('   ');
        $event = new TicketCreated($ticket, 'corr-emb-005');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // description null → cast a string vacío → early return
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_description_is_null(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = new Ticket;
        $ticket->id = 'ticket-emb-null';
        $ticket->description = null;

        $event = new TicketCreated($ticket, 'corr-emb-006');

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // correlationId vacío se propaga correctamente al job
    // ──────────────────────────────────────────────────────────

    public function test_propagates_empty_correlation_id_to_job(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket('Descripcion con correlacion vacia');
        $event = new TicketCreated($ticket); // correlationId por defecto = ''

        (new GenerateEmbeddingOnTicketCreated)->handle($event);

        Bus::assertDispatched(GenerateTicketEmbedding::class, function (GenerateTicketEmbedding $job): bool {
            return $job->correlationId === '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────

    private function makeTicket(string $description): Ticket
    {
        $ticket = new Ticket;
        $ticket->id = 'ticket-emb-'.uniqid();
        $ticket->description = $description;

        return $ticket;
    }
}
