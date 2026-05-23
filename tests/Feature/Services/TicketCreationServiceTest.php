<?php

namespace Tests\Feature\Services;

use App\Events\TicketCreated;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class TicketCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketCreationService $service;

    private TicketMediaStorageService&MockObject $mediaStorage;

    private TicketQrLogger&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaStorage = $this->createMock(TicketMediaStorageService::class);
        $this->logger = $this->createMock(TicketQrLogger::class);

        $this->service = new TicketCreationService($this->logger, $this->mediaStorage);
    }

    public function test_create_prefers_correlation_id_param_and_warning_not_pending_when_sync(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => false,
            'queue.default' => 'sync',
        ]);

        $reporter = User::factory()->create();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $this->mediaStorage->expects($this->once())
            ->method('storeManyForTicket');
        $this->logger->expects($this->once())
            ->method('info');

        Event::fake([TicketCreated::class]);

        $payload = [
            'title' => 'New ticket',
            'description' => 'Some description',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ];

        $result = $this->service->create($reporter, $payload, [], '  corr-001  ');

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
            return $event->correlationId === 'corr-001';
        });

        $this->assertTrue($result['created']);
        $this->assertNull($result['warning']);
        $this->assertFalse($result['warning_pending']);
    }

    public function test_create_uses_header_correlation_id_and_sets_warning_pending_when_async(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
            'queue.default' => 'database',
        ]);

        $request = Request::create('/api/tickets', 'POST');
        $request->headers->set('X-Correlation-Id', 'corr-h-001');
        $this->app->instance('request', $request);

        $reporter = User::factory()->create();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $this->mediaStorage->expects($this->once())
            ->method('storeManyForTicket');
        $this->logger->expects($this->once())
            ->method('info');

        Event::fake([TicketCreated::class]);

        $payload = [
            'title' => 'Async ticket',
            'description' => 'Async description',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ];

        $result = $this->service->create($reporter, $payload, [], '');

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
            return $event->correlationId === 'corr-h-001';
        });

        $this->assertNull($result['warning']);
        $this->assertTrue($result['warning_pending']);
        $this->assertSame('corr-h-001', $request->attributes->get('correlation_id'));
    }

    public function test_resolve_duplicate_warning_returns_data_when_duplicate_is_open(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => false,
            'queue.default' => 'sync',
        ]);

        $reporter = User::factory()->create();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $ticket = Ticket::create([
            'title' => 'Primary ticket',
            'description' => 'Primary description',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $matched = Ticket::create([
            'title' => 'Matched ticket',
            'description' => 'Matched description',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'similarity_score' => 0.95,
            'matched_ticket_id' => $matched->id,
            'is_duplicate' => true,
        ]);

        $method = new \ReflectionMethod(TicketCreationService::class, 'resolveDuplicateWarning');
        $method->setAccessible(true);

        $warning = $method->invoke($this->service, $ticket);

        $this->assertNotNull($warning);
        $this->assertSame($matched->id, $warning['id']);
        $this->assertSame('open', $warning['state']);
        $this->assertSame(0.95, $warning['similarity_score']);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Room A',
            'building' => 'Building A',
            'floor' => '1',
            'room_code' => 'A-'.Str::uuid(),
            'qr_token' => 'qr-'.Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Network-'.Str::uuid(),
            'icon' => 'wifi',
            'description' => 'Category description',
        ]);
    }
}
