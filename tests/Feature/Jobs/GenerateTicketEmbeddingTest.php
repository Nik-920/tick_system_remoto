<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateTicketEmbedding;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Ai\EmbeddingService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeEmbeddingProvider;
use Tests\TestCase;

class GenerateTicketEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_embedding_when_enabled(): void
    {
        $ticket = $this->createTicket('Ticket embedding test');

        $job = new GenerateTicketEmbedding($ticket, 'corr-emb-001');
        $job->handle($this->makeEmbeddingService([0.1, 0.2, 0.3]), $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($embedding);
        $this->assertSame([0.1, 0.2, 0.3], $embedding->embedding_vector);
        $this->assertSame(hash('sha256', $ticket->embeddingText()), $embedding->description_hash);
    }

    public function test_job_skips_when_ai_is_unavailable(): void
    {
        $ticket = $this->createTicket('Ticket disabled');

        $job = new GenerateTicketEmbedding($ticket, 'corr-emb-disabled');
        $job->handle(
            new EmbeddingService(FakeEmbeddingProvider::unavailable()),
            $this->makeLogger()
        );

        $this->assertDatabaseMissing('ticket_embeddings', ['ticket_id' => $ticket->id]);
    }

    public function test_job_skips_when_embedding_is_up_to_date(): void
    {
        $ticket = $this->createTicket('Ticket embedding cached');

        $hash = hash('sha256', $ticket->embeddingText());
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => $hash,
            'is_duplicate' => false,
        ]);

        $job = new GenerateTicketEmbedding($ticket, 'corr-emb-002');
        $job->handle($this->makeEmbeddingService([9.9, 9.9]), $this->makeLogger());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertSame([0.1, 0.2], $embedding->embedding_vector);
    }

    public function test_is_duplicate_persists_as_postgres_boolean(): void
    {
        $driver = DB::connection()->getDriverName();

        $ticket = $this->createTicket('Ticket bool');

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
        ]);

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'select is_duplicate::text as value from ticket_embeddings where ticket_id = ?',
                [$ticket->id]
            );

            $this->assertSame('true', $row?->value);

            return;
        }

        $row = DB::selectOne(
            'select is_duplicate as value from ticket_embeddings where ticket_id = ?',
            [$ticket->id]
        );

        $this->assertSame(1, (int) ($row?->value ?? 0));
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
        return new EmbeddingService(new FakeEmbeddingProvider($vector));
    }

    private function makeLogger(): TicketQrLogger
    {
        return new class extends TicketQrLogger
        {
            /** @param  array<string, mixed>  $context */
            public function info(string $eventName, array $context = []): void {}

            /** @param  array<string, mixed>  $context */
            public function warning(string $eventName, array $context = []): void {}
        };
    }
}
