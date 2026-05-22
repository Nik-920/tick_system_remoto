<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateTicketEmbedding;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\HuggingFaceService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTicketEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_embedding_when_enabled(): void
    {
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
        ]);

        $ticket = $this->createTicket('Ticket embedding test');

        $job = new GenerateTicketEmbedding($ticket, 'corr-emb-001');

        $embeddings = $this->makeEmbeddingService([0.1, 0.2, 0.3]);
        $job->handle($embeddings, $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($embedding);
        $this->assertSame([0.1, 0.2, 0.3], $embedding->embedding_vector);
        $this->assertSame(hash('sha256', $ticket->embeddingText()), $embedding->description_hash);
    }

    public function test_job_skips_when_embedding_is_up_to_date(): void
    {
        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
        ]);

        $ticket = $this->createTicket('Ticket embedding cached');

        $hash = hash('sha256', $ticket->embeddingText());
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => $hash,
            'is_duplicate' => false,
        ]);

        $job = new GenerateTicketEmbedding($ticket, 'corr-emb-002');

        $embeddings = $this->makeEmbeddingService([9.9, 9.9]);
        $job->handle($embeddings, $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertSame([0.1, 0.2], $embedding->embedding_vector);
    }

    private function createTicket(string $title): Ticket
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Aula AI',
            'building' => 'Edificio AI',
            'floor' => '1',
            'room_code' => 'AI-101',
            'qr_token' => 'qr-ai-101',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Infraestructura',
            'icon' => 'settings',
            'description' => 'Categoria AI',
        ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripcion para embedding.',
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
