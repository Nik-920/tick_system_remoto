<?php

namespace Tests\Feature\Events;

use App\Events\TicketCreated;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — punto 0.5
 *
 * Verifica que TicketController@store (Web y API) dispara el evento
 * TicketCreated a través del terminating callback registrado por el
 * trait DispatchesTicketCreatedAfterResponse.
 *
 * En el entorno de tests, app()->terminating() se ejecuta cuando el
 * kernel llama a terminate($request, $response) tras devolver la respuesta.
 * Event::fake() captura el evento incluso dentro de ese callback.
 */
class TicketCreatedEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Web: POST /tickets → TicketCreated se dispara
    // ──────────────────────────────────────────────────────────

    public function test_web_store_dispatches_ticket_created_event_after_response(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Luz parpadeante en pasillo',
            'description' => 'La luz del pasillo principal parpadea desde ayer.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ]);

        $response->assertRedirect();

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
            return $event->ticket instanceof Ticket
                && $event->ticket->title === 'Luz parpadeante en pasillo'
                && $event->correlationId !== '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Web: TicketCreated se dispara UNA sola vez por store
    // ──────────────────────────────────────────────────────────

    public function test_web_store_dispatches_ticket_created_exactly_once(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Grieta en pared del aula',
            'description' => 'Grieta visible en la pared norte del aula 301.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'low',
        ]);

        Event::assertDispatchedTimes(TicketCreated::class, 1);
    }

    // ──────────────────────────────────────────────────────────
    // Web: correlationId del header X-Correlation-Id llega al evento
    // ──────────────────────────────────────────────────────────

    public function test_web_store_propagates_correlation_id_header_to_event(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();
        $correlationId = 'corr-web-store-001';

        $this->actingAs($reporter)
            ->withHeader('X-Correlation-Id', $correlationId)
            ->post(route('tickets.store'), [
                'title' => 'Ventilador averiado',
                'description' => 'El ventilador del servidor hace ruido.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'high',
            ]);

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event) use ($correlationId): bool {
            return $event->correlationId === $correlationId;
        });
    }

    // ──────────────────────────────────────────────────────────
    // Web: el ticket del evento coincide con el persistido en BD
    // ──────────────────────────────────────────────────────────

    public function test_web_store_event_ticket_matches_persisted_record(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Inundacion en sotano',
            'description' => 'Hay agua acumulada en el sotano del edificio B.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'critical',
        ]);

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event) use ($reporter, $location, $category): bool {
            $ticket = $event->ticket;

            return $ticket->reporter_id === $reporter->id
                && $ticket->location_id === $location->id
                && $ticket->category_id === $category->id
                && $ticket->state === 'open'
                && $ticket->priority === 'critical';
        });
    }

    // ──────────────────────────────────────────────────────────
    // API: POST /api/tickets → TicketCreated se dispara
    // ──────────────────────────────────────────────────────────

    public function test_api_store_dispatches_ticket_created_event_after_response(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $response = $this->postJson(route('api.tickets.store'), [
            'title' => 'Proyector sin señal en aula magna',
            'description' => 'El proyector del aula magna no detecta la señal HDMI.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ]);

        $response->assertCreated();

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
            return $event->ticket instanceof Ticket
                && $event->ticket->title === 'Proyector sin señal en aula magna'
                && $event->correlationId !== '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // API: correlationId del header llega al evento
    // ──────────────────────────────────────────────────────────

    public function test_api_store_propagates_correlation_id_header_to_event(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();
        $correlationId = 'corr-api-store-001';

        $this->withHeader('X-Correlation-Id', $correlationId)
            ->postJson(route('api.tickets.store'), [
                'title' => 'Cable de red roto',
                'description' => 'El cable de red del laboratorio 4 está cortado.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'medium',
            ]);

        Event::assertDispatched(TicketCreated::class, function (TicketCreated $event) use ($correlationId): bool {
            return $event->correlationId === $correlationId;
        });
    }

    // ──────────────────────────────────────────────────────────
    // API: TicketCreated NO se dispara en requests que no son store
    // ──────────────────────────────────────────────────────────

    public function test_api_index_does_not_dispatch_ticket_created_event(): void
    {
        Event::fake([TicketCreated::class]);

        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $this->getJson(route('api.tickets.index'));

        Event::assertNotDispatched(TicketCreated::class);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Observer '.Str::upper(Str::random(4)),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'OBS-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-obs-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Categoria Observer '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoria de prueba para Observer',
        ]);
    }
}
