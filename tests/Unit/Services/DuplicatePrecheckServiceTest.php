<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\DuplicatePrecheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuplicatePrecheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private DuplicatePrecheckService $service;

    private User $reporter;

    private Location $location;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('reporter', 'web');
        $this->reporter = User::factory()->create();
        $this->reporter->assignRole('reporter');

        $this->location = Location::create([
            'name' => 'Aula Unit',
            'building' => 'Edificio B',
            'floor' => '1',
            'room_code' => 'U-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Cat-Unit-'.Str::lower(Str::random(4)),
            'icon' => 'bolt',
            'description' => 'Categoría unit test',
        ]);

        $this->service = new DuplicatePrecheckService;
    }

    // ── 1. Returns null when no candidates exist ──────────────────────────

    public function test_returns_null_when_no_tickets_exist(): void
    {
        $result = $this->service->check([
            'title' => 'Proyector sin imagen',
            'description' => 'El proyector no emite imagen desde esta mañana.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 2. Detects high title overlap ─────────────────────────────────────

    public function test_detects_similar_ticket_with_high_title_overlap(): void
    {
        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio aula',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matchedTitle', $result);
        $this->assertArrayHasKey('matchedState', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertSame('Abierto', $result['matchedState']);
    }

    // ── 3. Detects soft overlap (same location+category counts) ──────────

    public function test_detects_match_with_soft_overlap(): void
    {
        Ticket::create([
            'title' => 'Proyector laboratorio falla encendido',
            'description' => 'El proyector no enciende.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector laboratorio problema',
            'description' => 'No enciende el proyector.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertIsArray($result);
    }

    // ── 4. Ignores cancelled tickets ──────────────────────────────────────

    public function test_ignores_cancelled_tickets(): void
    {
        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_CANCELLED,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 5. Ignores rejected tickets ───────────────────────────────────────

    public function test_ignores_rejected_tickets(): void
    {
        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_REJECTED,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 6. Ignores tickets older than 48 hours ────────────────────────────

    public function test_ignores_tickets_older_than_48_hours(): void
    {
        $ticket = Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        $ticket->forceFill(['created_at' => now()->subHours(49)])->save();

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 7. Result only contains safe fields ──────────────────────────────

    public function test_result_only_contains_safe_fields(): void
    {
        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio unit',
            'description' => 'Descripcion privada que no debe exponerse.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio unit',
            'description' => 'Prueba safe fields.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertIsArray($result);
        $this->assertSame(['matchedTitle', 'matchedState', 'reason'], array_keys($result));
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayNotHasKey('reporter_id', $result);
    }

    // ── 8. Returns null when payload is incomplete ────────────────────────

    public function test_returns_null_when_location_id_is_missing(): void
    {
        $result = $this->service->check([
            'title' => 'Proyector sin imagen',
            'description' => 'No funciona.',
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 9. No match when location differs ─────────────────────────────────

    public function test_returns_null_when_existing_ticket_has_different_location(): void
    {
        $otherLocation = Location::create([
            'name' => 'Aula Otro',
            'building' => 'Edificio C',
            'floor' => '3',
            'room_code' => 'OT-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);

        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $otherLocation->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $result = $this->service->check([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertNull($result);
    }

    // ── 10. findMatch() returns the real candidate Ticket (server-only) ────

    public function test_find_match_returns_the_actual_candidate_ticket_with_id(): void
    {
        $existing = Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $match = $this->service->findMatch([
            'title' => 'Proyector sin imagen laboratorio aula',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ], $this->reporter);

        $this->assertIsArray($match);
        $this->assertArrayHasKey('ticket', $match);
        $this->assertArrayHasKey('reason', $match);
        $this->assertSame($existing->id, $match['ticket']->id);
        $this->assertSame('Misma ubicación, categoría y título similar', $match['reason']);
    }

    // ── 11. findMatch() and check() agree on whether there is a match ──────

    public function test_find_match_returns_null_exactly_when_check_returns_null(): void
    {
        $payload = [
            'title' => 'Filtración agua techo baño',
            'description' => 'Hay una gotera en el baño.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ];

        $this->assertNull($this->service->check($payload, $this->reporter));
        $this->assertNull($this->service->findMatch($payload, $this->reporter));
    }

    // ── 12. findMatch() never leaks into check()'s safe payload ────────────

    public function test_check_result_does_not_expose_the_id_findmatch_would_return(): void
    {
        Ticket::create([
            'title' => 'Proyector sin imagen laboratorio',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $payload = [
            'title' => 'Proyector sin imagen laboratorio aula',
            'description' => 'El proyector no proyecta imagen.',
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
        ];

        $match = $this->service->findMatch($payload, $this->reporter);
        $checkResult = $this->service->check($payload, $this->reporter);

        $this->assertNotNull($match);
        $this->assertIsArray($checkResult);
        $this->assertSame(['matchedTitle', 'matchedState', 'reason'], array_keys($checkResult));
    }
}
