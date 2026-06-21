<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression: community_visible must persist as boolean, not integer.
 *
 * PostgreSQL's boolean column rejects integer 1/0 with SQLSTATE[42804].
 * Laravel's prepareBindings() converts PHP bool → (int) before PDO, so
 * the Ticket model must use an Attribute mutator (like assignmentLocked) that
 * returns the string 'true'/'false' on pgsql, bypassing that cast.
 * SQLite accepts 1/0, which is why this bug was invisible locally.
 *
 * Coverage:
 *   A) Category::resolveCommunityVisibility() returns strict bool.
 *   B) TicketCreationService persists true/false/default/locked correctly.
 *   C) Ticket model re-hydrates community_visible as PHP bool (assertIsBool).
 *   D) Attribute mutator normalises int/string inputs to bool on set.
 *   E) hide/restore controller persists booleans without QueryException.
 */
class TicketCommunityVisibleBooleanPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private TicketCreationService $service;

    private TicketMediaStorageService&MockObject $mediaStorage;

    private TicketQrLogger&MockObject $logger;

    private User $reporter;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaStorage = $this->createMock(TicketMediaStorageService::class);
        $this->mediaStorage->method('storeManyForTicket');

        $this->logger = $this->createMock(TicketQrLogger::class);
        $this->logger->method('info');

        $this->service = new TicketCreationService($this->logger, $this->mediaStorage);

        Role::findOrCreate('reporter', 'web');
        Role::findOrCreate('admin', 'web');

        $this->reporter = User::factory()->create();
        $this->reporter->assignRole('reporter');

        $this->location = $this->makeLocation();
    }

    // ── A. Category::resolveCommunityVisibility() returns strict bool ─────────

    public function test_resolve_returns_bool_true_not_int(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: false);

        $result = $category->resolveCommunityVisibility(true);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_resolve_returns_bool_false_not_int(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: false);

        $result = $category->resolveCommunityVisibility(false);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function test_resolve_uses_default_when_null_and_returns_bool(): void
    {
        $categoryPublic = $this->makeCategory(defaultVisible: true, locked: false);
        $categoryPrivate = $this->makeCategory(defaultVisible: false, locked: false);

        $resultPublic = $categoryPublic->resolveCommunityVisibility(null);
        $resultPrivate = $categoryPrivate->resolveCommunityVisibility(null);

        $this->assertIsBool($resultPublic);
        $this->assertTrue($resultPublic);
        $this->assertIsBool($resultPrivate);
        $this->assertFalse($resultPrivate);
    }

    public function test_resolve_locked_private_ignores_true_request_and_returns_bool(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: true);

        $result = $category->resolveCommunityVisibility(true);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function test_resolve_locked_public_ignores_false_request_and_returns_bool(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: true);

        $result = $category->resolveCommunityVisibility(false);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    // ── B. TicketCreationService persists community_visible correctly ──────────

    public function test_create_with_community_visible_1_persists_true_bool(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: false);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket visible BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'community_visible' => '1',
        ]);

        $this->assertTrue($result['created']);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    public function test_create_with_community_visible_0_persists_false_bool(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: false);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket oculto BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'community_visible' => '0',
        ]);

        $this->assertTrue($result['created']);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    public function test_create_without_community_visible_uses_category_default_true(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: false);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket default public BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
        ]);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    public function test_create_without_community_visible_uses_category_default_false(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: false);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket default private BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
        ]);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    public function test_create_locked_private_category_ignores_visible_1_request(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: true);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket locked private BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'community_visible' => '1',
        ]);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    public function test_create_locked_public_category_ignores_visible_0_request(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: true);

        $result = $this->service->create($this->reporter, [
            'title' => 'Ticket locked public BOOLTEST',
            'description' => 'Descripción del ticket.',
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'community_visible' => '0',
        ]);

        $fresh = Ticket::findOrFail($result['ticket']->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    // ── C. Ticket model re-hydrates community_visible as PHP bool ─────────────

    public function test_ticket_model_rehydrates_community_visible_as_bool_true(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: false);
        $ticket = Ticket::create([
            'title' => 'Rehidrate true BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $fresh = Ticket::findOrFail($ticket->id);

        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    public function test_ticket_model_rehydrates_community_visible_as_bool_false(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: false);
        $ticket = Ticket::create([
            'title' => 'Rehidrate false BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $fresh = Ticket::findOrFail($ticket->id);

        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    // ── D. Attribute mutator normalises int/string inputs on set ──────────────

    public function test_ticket_model_accepts_integer_1_and_stores_true(): void
    {
        $category = $this->makeCategory(defaultVisible: false, locked: false);
        $ticket = Ticket::create([
            'title' => 'Int 1 BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => 1,
        ]);

        $fresh = Ticket::findOrFail($ticket->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    public function test_ticket_model_accepts_integer_0_and_stores_false(): void
    {
        $category = $this->makeCategory(defaultVisible: true, locked: false);
        $ticket = Ticket::create([
            'title' => 'Int 0 BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => 0,
        ]);

        $fresh = Ticket::findOrFail($ticket->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    // ── E. hide/restore controller persists booleans without QueryException ───

    public function test_hide_sets_community_visible_to_false_bool(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = $this->makeCategory(defaultVisible: true, locked: false);
        $ticket = Ticket::create([
            'title' => 'Hide BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Prueba hide boolean'])
            ->assertRedirect();

        $fresh = Ticket::findOrFail($ticket->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertFalse($fresh->community_visible);
    }

    public function test_restore_sets_community_visible_to_true_bool(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = $this->makeCategory(defaultVisible: true, locked: false);
        $ticket = Ticket::create([
            'title' => 'Restore BOOLTEST',
            'description' => 'desc',
            'reporter_id' => $this->reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $admin->id,
            'community_visibility_reason' => 'Oculto temporalmente',
        ])->save();

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertRedirect();

        $fresh = Ticket::findOrFail($ticket->id);
        $this->assertIsBool($fresh->community_visible);
        $this->assertTrue($fresh->community_visible);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCategory(bool $defaultVisible = true, bool $locked = false): Category
    {
        return Category::create([
            'name' => 'Cat-Bool-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para test boolean persistence',
            'community_default_visible' => $defaultVisible,
            'community_visibility_locked' => $locked,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala BoolTest',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'BT-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }
}
