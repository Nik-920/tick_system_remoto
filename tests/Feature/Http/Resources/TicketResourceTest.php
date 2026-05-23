<?php

namespace Tests\Feature\Http\Resources;

use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TicketResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_includes_duplicate_warning_when_embedding_is_duplicate(): void
    {
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
            'similarity_score' => 0.92,
            'matched_ticket_id' => $matched->id,
            'is_duplicate' => true,
        ]);

        $ticket = Ticket::with(['embedding.matchedTicket'])->findOrFail($ticket->id);

        $resource = new TicketResource($ticket);
        $result = $resource->toArray(Request::create('/api/tickets', 'GET'));

        $this->assertTrue($result['duplicate_warning']);
        $this->assertSame($matched->id, $result['similar_ticket']['id']);
        $this->assertSame($matched->state, $result['similar_ticket']['state']);
        $this->assertSame(0.92, $result['similar_ticket']['similarity_score']);
    }

    public function test_resource_maps_loaded_relations_and_formats_dates(): void
    {
        $reporter = User::factory()->create();
        $assignee = User::factory()->create();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $ticket = Ticket::create([
            'title' => 'Full ticket',
            'description' => 'Full description',
            'reporter_id' => $reporter->id,
            'assigned_to' => $assignee->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => null,
            'to_state' => 'open',
            'changed_by' => $reporter->id,
            'comment' => 'Created',
        ]);

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.com/file.png',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $ticket = Ticket::with([
            'reporter',
            'assignee',
            'location',
            'category',
            'stateHistory',
            'media',
        ])->findOrFail($ticket->id);

        $resource = new TicketResource($ticket);
        $result = $resource->toArray(Request::create('/api/tickets', 'GET'));

        $this->assertSame($reporter->id, $result['reporter']['id']);
        $this->assertSame($assignee->id, $result['assignee']['id']);
        $this->assertSame($location->id, $result['location']['id']);
        $this->assertSame($category->id, $result['category']['id']);
        $this->assertCount(1, $result['state_history']);
        $this->assertCount(1, $result['media']);
        $this->assertFalse($result['duplicate_warning']);
        $this->assertSame($ticket->created_at->format(DATE_ATOM), $result['created_at']);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Room R',
            'building' => 'Building R',
            'floor' => '1',
            'room_code' => 'R-101',
            'qr_token' => 'qr-r-101',
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Electric',
            'icon' => 'bolt',
            'description' => 'Category electric',
        ]);
    }
}
