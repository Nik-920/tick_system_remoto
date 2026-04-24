<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_logout_action_in_ticket_index(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSee('Cerrar sesion');
    }

    public function test_authenticated_user_can_view_ticket_index(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Proyector sin imagen',
            'description' => 'El proyector del aula 201 no muestra señal HDMI.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.index');
        $response->assertSee('Proyector sin imagen');
    }

    public function test_authenticated_user_can_create_ticket_from_web_form(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Enchufe sin corriente',
            'description' => 'El enchufe de la esquina derecha no entrega energia desde la mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $response = $this
            ->actingAs($user)
            ->post(route('tickets.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Ticket creado correctamente.');
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Enchufe sin corriente',
            'reporter_id' => $user->id,
            'state' => 'open',
        ]);
    }

    public function test_authenticated_user_can_create_ticket_with_media_from_web_form(): void
    {
        config([
            'filesystems.domain_disks.tickets' => 'public',
            'filesystems.domain_prefixes.tickets' => 'tickets/media',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $response = $this
            ->actingAs($user)
            ->post(route('tickets.store'), [
                'title' => 'Ticket Web con adjuntos',
                'description' => 'Descripcion valida y suficientemente extensa para crear ticket con adjuntos desde web.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'high',
                'media_files' => [
                    UploadedFile::fake()->image('evidencia.png', 120, 120),
                    UploadedFile::fake()->create('acta.pdf', 200, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Ticket creado correctamente.');

        $ticket = Ticket::query()->where('title', 'Ticket Web con adjuntos')->firstOrFail();
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $user->id,
            'file_type' => 'image',
        ]);
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $user->id,
            'file_type' => 'document',
        ]);

        $storedFiles = Storage::disk('public')->allFiles('tickets/media/' . $ticket->id);
        $this->assertCount(2, $storedFiles);
    }

    public function test_maintenance_can_change_ticket_state_from_open_to_in_progress(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Luz apagada en laboratorio',
            'description' => 'No encienden las luces del laboratorio principal.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.update-state', $ticket), [
                'to_state' => 'in_progress',
                'comment' => 'Se asigna tecnico de turno.',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'state' => 'in_progress',
        ]);
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'in_progress',
            'changed_by' => $maintenance->id,
        ]);
    }

    public function test_admin_can_delete_ticket_and_remove_media_from_storage_from_web(): void
    {
        config([
            'services.supabase.storage.domain_buckets.tickets' => 'TableTicket',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $admin = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket a eliminar Web',
            'description' => 'Ticket con adjunto para validar borrado desde modulo web.',
            'reporter_id' => $admin->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $mediaPath = 'tickets/media/' . $ticket->id . '/web-evidencia.png';
        Storage::disk('public')->put($mediaPath, 'image-content');

        $media = TicketMedia::query()->create([
            'ticket_id' => $ticket->id,
            'file_url' => '/storage/v1/object/public/TableTicket/' . $mediaPath,
            'file_type' => 'image',
            'uploaded_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('tickets.destroy', $ticket));

        $response->assertRedirect(route('tickets.index'));
        $response->assertSessionHas('status', 'Ticket eliminado correctamente.');

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
        ]);
        $this->assertDatabaseMissing('ticket_media', [
            'id' => $media->id,
        ]);

        Storage::disk('public')->assertMissing($mediaPath);
    }

    public function test_reporter_cannot_delete_ticket_from_web(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket protegido Web',
            'description' => 'Un reporter no debe eliminar tickets desde web.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($reporter)
            ->delete(route('tickets.destroy', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
        ]);
    }

    public function test_user_can_filter_tickets_by_location(): void
    {
        $user = $this->createUserWithRole('reporter');
        $locationA = $this->createLocation(['name' => 'Aula A']);
        $locationB = $this->createLocation(['name' => 'Aula B']);
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Ticket en Aula A',
            'description' => 'Incidencia en aula A',
            'reporter_id' => $user->id,
            'location_id' => $locationA->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        Ticket::create([
            'title' => 'Ticket en Aula B',
            'description' => 'Incidencia en aula B',
            'reporter_id' => $user->id,
            'location_id' => $locationB->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index', ['location_id' => $locationA->id]));

        $response->assertOk();
        $response->assertSee('Ticket en Aula A');
        $response->assertDontSee('Ticket en Aula B');
    }

    public function test_user_can_filter_tickets_by_category(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $categoryA = $this->createCategory(['name' => 'Electricidad A']);
        $categoryB = $this->createCategory(['name' => 'Red B']);

        Ticket::create([
            'title' => 'Ticket categoria A',
            'description' => 'Incidencia categoria A',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $categoryA->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        Ticket::create([
            'title' => 'Ticket categoria B',
            'description' => 'Incidencia categoria B',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $categoryB->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index', ['category_id' => $categoryA->id]));

        $response->assertOk();
        $response->assertSee('Ticket categoria A');
        $response->assertDontSee('Ticket categoria B');
    }

    public function test_user_can_paginate_tickets_with_per_page_filter(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        for ($i = 1; $i <= 25; $i++) {
            Ticket::create([
                'title' => "Ticket #{$i}",
                'description' => "Descripcion {$i}",
                'reporter_id' => $user->id,
                'location_id' => $location->id,
                'category_id' => $category->id,
                'state' => 'resolved',
                'priority' => 'low',
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index', ['per_page' => 10]));

        $response->assertOk();

        $tickets = $response->viewData('tickets');
        $this->assertCount(10, $tickets->items());
        $this->assertSame(10, $tickets->perPage());
        $this->assertSame(25, $tickets->total());
    }

    public function test_filters_are_preserved_in_pagination_links(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        for ($i = 1; $i <= 20; $i++) {
            Ticket::create([
                'title' => "Ticket filtrado #{$i}",
                'description' => "Descripcion filtro {$i}",
                'reporter_id' => $user->id,
                'location_id' => $location->id,
                'category_id' => $category->id,
                'state' => 'resolved',
                'priority' => 'medium',
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index', [
                'location_id' => $location->id,
                'category_id' => $category->id,
                'per_page' => 10,
            ]));

        $response->assertOk();

        $tickets = $response->viewData('tickets');
        $nextPageUrl = (string) $tickets->nextPageUrl();

        $this->assertNotSame('', $nextPageUrl);
        $this->assertStringContainsString('location_id=' . $location->id, $nextPageUrl);
        $this->assertStringContainsString('category_id=' . $category->id, $nextPageUrl);
        $this->assertStringContainsString('per_page=10', $nextPageUrl);
    }

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

    /**
     * @param array<string, mixed> $overrides
     */
    private function createLocation(array $overrides = []): Location
    {
        $base = [
            'name' => 'Aula Innovacion',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-' . Str::upper(Str::random(6)),
            'qr_token' => 'qr-' . Str::lower(Str::random(12)),
            'is_active' => true,
        ];

        return Location::create(array_merge($base, $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCategory(array $overrides = []): Category
    {
        $base = [
            'name' => 'Categoria ' . Str::lower(Str::random(8)),
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ];

        return Category::create(array_merge($base, $overrides));
    }
}
