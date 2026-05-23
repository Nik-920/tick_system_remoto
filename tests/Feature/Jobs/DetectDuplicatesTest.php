<?php

namespace Tests\Feature\Jobs;

use App\Events\DuplicateDetected;
use App\Jobs\DetectDuplicates;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Ai\DeduplicationService;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\HuggingFaceService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DetectDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_duplicate_and_dispatches_event(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.8,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket principal');
        $matched = $this->createTicket('Ticket comparado', $ticket->location_id, $ticket->category_id);

        $vector = [1.0, 0.0];
        $hash = hash('sha256', $ticket->embeddingText());

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => $vector,
            'description_hash' => $hash,
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => 'other-hash',
            'is_duplicate' => false,
        ]);

        Event::fake([DuplicateDetected::class]);

        $deduplication = new DeduplicationService($this->makeEmbeddingService([0.0, 1.0]));
        $embeddings = $this->makeEmbeddingService([0.0, 1.0]);

        $job = new DetectDuplicates($ticket, 'corr-dup-001');
        $job->handle($deduplication, $embeddings, $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertSame($matched->id, $embedding->matched_ticket_id);
        $this->assertTrue($embedding->is_duplicate);
        $this->assertSame(1.0, $embedding->similarity_score);

        Event::assertDispatched(DuplicateDetected::class, function (DuplicateDetected $event) use ($ticket, $matched): bool {
            return $event->ticket->id === $ticket->id
                && $event->matchedTicket?->id === $matched->id
                && $event->similarityScore === 1.0;
        });
    }

    public function test_job_reuses_embedding_and_clears_duplicate_when_no_candidates(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.8,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket principal');
        $matched = $this->createTicket('Ticket comparado', $ticket->location_id, $ticket->category_id);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'similarity_score' => 0.9,
            'matched_ticket_id' => $matched->id,
            'is_duplicate' => true,
        ]);

        Event::fake([DuplicateDetected::class]);

        $deduplication = new DeduplicationService($this->makeEmbeddingService([0.0, 1.0]));
        $embeddings = $this->makeEmbeddingService([0.0, 1.0]);

        $job = new DetectDuplicates($ticket, 'corr-dup-002');
        $job->handle($deduplication, $embeddings, $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->matched_ticket_id);
        $this->assertNull($embedding->similarity_score);

        Event::assertNotDispatched(DuplicateDetected::class);
    }

    public function test_job_logs_warning_when_embedding_generation_fails(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
        ]);

        $ticket = $this->createTicket('Ticket con error');

        $embeddingService = $this->createMock(EmbeddingService::class);
        $embeddingService->expects($this->once())
            ->method('generate')
            ->willThrowException(new \RuntimeException('fail'));

        $logger = $this->createMock(TicketQrLogger::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('ticket.duplicate.embedding_failed'),
                $this->callback(function (array $context) use ($ticket): bool {
                    return ($context['ticket_id'] ?? null) === $ticket->id
                        && ($context['correlation_id'] ?? null) === 'corr-dup-003';
                })
            );

        $job = new DetectDuplicates($ticket, 'corr-dup-003');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $embeddingService,
            $logger
        );

        $this->assertDatabaseMissing('ticket_embeddings', ['ticket_id' => $ticket->id]);
    }

    private function createTicket(string $title, ?string $locationId = null, ?string $categoryId = null): Ticket
    {
        $user = User::factory()->create();

        $location = $locationId
            ? Location::query()->findOrFail($locationId)
            : Location::create([
                'name' => 'Aula Detect',
                'building' => 'Edificio D',
                'floor' => '2',
                'room_code' => 'D-201',
                'qr_token' => 'qr-d-201',
                'is_active' => true,
            ]);

        $category = $categoryId
            ? Category::query()->findOrFail($categoryId)
            : Category::create([
                'name' => 'Red',
                'icon' => 'wifi',
                'description' => 'Categoria red',
            ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripcion para duplicados.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeEmbeddingService(array $vector): EmbeddingService
    {
        $huggingFace = new class($vector) extends HuggingFaceService
        {
            public function __construct(private array $vector) {}

            public function embedding(string $text, ?string $model = null): array
            {
                return $this->vector;
            }
        };

        return new EmbeddingService($huggingFace);
    }

    private function makeLogger(): TicketQrLogger
    {
        return new class extends TicketQrLogger
        {
            /**
             * @param  array<string, mixed>  $context
             */
            public function info(string $eventName, array $context = []): void {}

            /**
             * @param  array<string, mixed>  $context
             */
            public function warning(string $eventName, array $context = []): void {}
        };
    }
}
