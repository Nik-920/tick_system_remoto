<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_logout_action_in_ticket_index(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Cerrar sesión');
    }

    public function test_authenticated_user_can_view_ticket_index(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Proyector sin imagen',
            'description' => 'El proyector del aula 201 no muestra señal HDMI.',
            'reporter_id' => $reporter->id,
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

    public function test_reporter_only_sees_own_tickets_in_index(): void
    {
        // Reporters are redirected from /tickets to their dedicated board,
        // where scope isolation (own tickets only) is enforced by the reporter controller.
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index'))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_reporter_cannot_see_other_reporter_ticket_via_show(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket ajeno para show',
            'description' => 'El reporter no debe ver este ticket.',
            'reporter_id' => $otherReporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_reporter_cannot_see_update_state_form_in_show(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket propio sin form update state',
            'description' => 'El reporter no debe ver el formulario de estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSeeText('Actualizar estado');
        $response->assertDontSeeText('Nuevo estado');
    }

    public function test_reporter_cannot_bypass_index_filter_with_search(): void
    {
        // Reporters are redirected to their own board before any query runs,
        // so they can never reach the classic index regardless of query params.
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['search' => 'TICKET_AJENO_SECRETO_123']))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_reporter_cannot_bypass_index_filter_with_duplicates(): void
    {
        // Reporters are redirected to their own board before any query runs,
        // so they can never reach the classic index regardless of query params.
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['duplicates' => '1']))
            ->assertRedirect(route('reporter.tickets.index'));
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
        // Message may include warning_pending text depending on queue configuration
        $response->assertSessionHas('status');
        $this->assertStringStartsWith(
            'Ticket creado correctamente',
            (string) session('status'),
            'Expected status message to start with "Ticket creado correctamente"'
        );
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
        // Message may include warning_pending text depending on queue configuration
        $response->assertSessionHas('status');
        $this->assertStringStartsWith(
            'Ticket creado correctamente',
            (string) session('status'),
            'Expected status message to start with "Ticket creado correctamente"'
        );

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

        $mediaUrls = Ticket::query()
            ->findOrFail($ticket->id)
            ->media()
            ->pluck('file_url')
            ->all();

        $this->assertCount(2, $mediaUrls);
        foreach ($mediaUrls as $mediaUrl) {
            $this->assertStringContainsString('/storage/v1/object/public/TableTicket/tickets/media/'.$ticket->id.'/', $mediaUrl);
        }
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
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

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

        $mediaPath = 'tickets/media/'.$ticket->id.'/web-evidencia.png';
        Storage::disk('public')->put($mediaPath, 'image-content');

        $media = TicketMedia::query()->create([
            'ticket_id' => $ticket->id,
            'file_url' => '/storage/v1/object/public/TableTicket/'.$mediaPath,
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

        $this->assertFalse(Storage::disk('public')->exists($mediaPath));
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
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
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
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
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
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
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
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
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
        $this->assertStringContainsString('location_id='.$location->id, $nextPageUrl);
        $this->assertStringContainsString('category_id='.$category->id, $nextPageUrl);
        $this->assertStringContainsString('per_page=10', $nextPageUrl);
    }

    // ── Phase 1: maintenance ownership tests ────────────────────────────────

    public function test_maintenance_only_sees_assigned_and_available_tickets_in_index(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        // Ticket assigned to maintenance A — must be visible
        $ticketOwnA = Ticket::create([
            'title' => 'Ticket propio maintenance A',
            'description' => 'Asignado al tecnico A, debe verse.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        $ticketOwnA->forceFill(['assigned_to' => $maintenanceA->id])->save();

        // Open + unassigned ticket — must be visible (claim queue)
        $ticketAvailable = Ticket::create([
            'title' => 'Ticket disponible abierto',
            'description' => 'Open y sin asignar, visible para cualquier tecnico.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        // Ticket assigned to maintenance B — must NOT be visible to A
        $ticketOwnB = Ticket::create([
            'title' => 'Ticket asignado a maintenance B',
            'description' => 'No debe verse por maintenance A.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'medium',
        ]);
        $ticketOwnB->forceFill(['assigned_to' => $maintenanceB->id])->save();

        // Resolved ticket not assigned to A — must NOT be visible
        $ticketResolved = Ticket::create([
            'title' => 'Ticket resuelto ajeno',
            'description' => 'Resuelto y no asignado al tecnico A.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'resolved',
            'priority' => 'low',
        ]);

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSee($ticketOwnA->title);
        $response->assertSee($ticketAvailable->title);
        $response->assertDontSee($ticketOwnB->title);
        $response->assertDontSee($ticketResolved->title);
    }

    public function test_maintenance_cannot_view_ticket_assigned_to_other_maintenance(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket de otro tecnico',
            'description' => 'Asignado a maintenance B, tecnico A no debe ver.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'medium',
        ]);
        $ticket->forceFill(['assigned_to' => $maintenanceB->id])->save();

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_maintenance_cannot_update_state_of_unassigned_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        // Ticket is open but not assigned to this maintenance user
        $ticket = Ticket::create([
            'title' => 'Ticket sin asignar',
            'description' => 'Open pero sin asignar al tecnico que intenta cambiar estado.',
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
                'comment' => 'Intento no autorizado.',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'state' => 'open',
        ]);
    }

    public function test_maintenance_can_update_state_of_assigned_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket asignado al tecnico',
            'description' => 'Ticket asignado al tecnico que va a cambiar estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.update-state', $ticket), [
                'to_state' => 'in_progress',
                'comment' => 'Iniciando revision del equipo.',
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

    public function test_maintenance_index_search_filter_respects_ownership_scope(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $secretTitle = 'TICKET_AJENO_SECRETO_MAINT_456';

        // Ticket assigned to maintenance B with a searchable title
        $ticketB = Ticket::create([
            'title' => $secretTitle,
            'description' => 'Ticket secreto del otro tecnico.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'critical',
        ]);
        $ticketB->forceFill(['assigned_to' => $maintenanceB->id])->save();

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.index', ['search' => $secretTitle]));

        $response->assertOk();
        $response->assertDontSeeText($secretTitle);
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
     * @param  array<string, mixed>  $overrides
     */
    private function createLocation(array $overrides = []): Location
    {
        $base = [
            'name' => 'Aula Innovacion',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ];

        return Location::create(array_merge($base, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCategory(array $overrides = []): Category
    {
        $base = [
            'name' => 'Categoria '.Str::lower(Str::random(8)),
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ];

        return Category::create(array_merge($base, $overrides));
    }

    // ── Test: duplicate badge in index ─────────────────────────────────────

    public function test_index_shows_duplicate_badge_for_effective_duplicate(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $matchedTicket = Ticket::create([
            'title' => 'Proyector existente',
            'description' => 'Descripcion del ticket que ya existe y es similar.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = Ticket::create([
            'title' => 'Proyector duplicado',
            'description' => 'Descripcion del ticket que la IA marcará como duplicado.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matchedTicket->id,
            'similarity_score' => 0.97,
            'review_status' => null,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSee('Posible duplicado');
    }

    public function test_index_does_not_show_badge_when_review_dismissed(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $matchedTicket = Ticket::create([
            'title' => 'Proyector existente dismissed',
            'description' => 'Descripcion del ticket que sirve como referencia.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = Ticket::create([
            'title' => 'Proyector descartado',
            'description' => 'Duplicado descartado manualmente por el revisor.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matchedTicket->id,
            'similarity_score' => 0.97,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertDontSee('Posible duplicado');
    }

    public function test_index_shows_related_badge_for_precheck_candidate_without_ai_duplicate(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $precheckMatched = Ticket::create([
            'title' => 'Proyector existente relacionado',
            'description' => 'Ticket detectado por el precheck al crear el otro.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = Ticket::create([
            'title' => 'Proyector confirmado como caso distinto',
            'description' => 'El reportante confirmó que es un caso distinto.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'is_duplicate' => false,
            'precheck_matched_ticket_id' => $precheckMatched->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertDontSee('Posible duplicado');
        $response->assertSee('Relacionado');
    }

    public function test_index_filter_duplicates_shows_only_effective_duplicates(): void
    {
        // Reporters are redirected to their own board; use admin for classic index.
        $user = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $matchedTicket = Ticket::create([
            'title' => 'Ticket referencia',
            'description' => 'Descripcion del ticket que sirve como referencia para los duplicados.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $dupAi = Ticket::create([
            'title' => 'Duplicado IA sin revision web',
            'description' => 'Este ticket debe aparecer por duplicado IA sin revision.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $dupAi->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => 'hash-dup-web',
            'is_duplicate' => true,
            'matched_ticket_id' => $matchedTicket->id,
            'review_status' => null,
        ]);

        $dismissed = Ticket::create([
            'title' => 'Duplicado dismissed web',
            'description' => 'Este ticket NO debe aparecer por revision dismissed.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $dismissed->id,
            'embedding_vector' => [0.3, 0.4],
            'description_hash' => 'hash-dismissed-web',
            'is_duplicate' => true,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        $confirmed = Ticket::create([
            'title' => 'Duplicado confirmado web',
            'description' => 'Este ticket debe aparecer por confirmacion manual.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $confirmed->id,
            'embedding_vector' => [0.5, 0.6],
            'description_hash' => 'hash-confirmed-web',
            'is_duplicate' => false,
            'review_status' => TicketEmbedding::REVIEW_CONFIRMED,
        ]);

        $normal = Ticket::create([
            'title' => 'Ticket normal web',
            'description' => 'Este ticket NO debe aparecer en el filtro de duplicados web.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $normal->id,
            'embedding_vector' => [0.7, 0.8],
            'description_hash' => 'hash-normal-web',
            'is_duplicate' => false,
            'review_status' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index', ['duplicates' => '1']));

        $response->assertOk();
        $response->assertSee('Duplicado IA sin revision web');
        $response->assertSee('Duplicado confirmado web');
        $response->assertDontSee('Duplicado dismissed web');
        $response->assertDontSee('Ticket normal web');
    }

    // ── Test: web reviewDuplicate endpoint ────────────────────────────────

    public function test_maintenance_can_dismiss_duplicate_from_web(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Duplicado a descartar desde web',
            'description' => 'Descripcion del ticket que el tecnico va a descartar como no duplicado.',
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
            'is_duplicate' => true,
        ]);

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.duplicate-review.update', $ticket), [
                'review_status' => TicketEmbedding::REVIEW_DISMISSED,
                'review_note' => 'No es un duplicado, equipo diferente.',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('status', 'Revisión de duplicado actualizada.');

        $this->assertDatabaseHas('ticket_embeddings', [
            'ticket_id' => $ticket->id,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
            'reviewed_by' => $maintenance->id,
            'review_note' => 'No es un duplicado, equipo diferente.',
        ]);

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertTrue($embedding->is_duplicate);
        $this->assertNotNull($embedding->reviewed_at);
    }

    public function test_reporter_cannot_review_duplicate_from_web(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket protegido web review',
            'description' => 'Reporter no debe poder hacer review desde web.',
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
            'is_duplicate' => true,
        ]);

        $response = $this
            ->actingAs($reporter)
            ->patch(route('tickets.duplicate-review.update', $ticket), [
                'review_status' => TicketEmbedding::REVIEW_DISMISSED,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('ticket_embeddings', [
            'ticket_id' => $ticket->id,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);
    }

    // ── Phase 2 UX Tests ──────────────────────────────────────────────────

    public function test_reporter_index_view_hides_assignment_and_duplicates_filters(): void
    {
        // Reporters are redirected from the classic /tickets to their dedicated board,
        // which inherently hides admin-only filters (assignment, duplicates, quick-filters).
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index'))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_admin_index_view_shows_assignment_and_duplicates_filters(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Tickets');
        $response->assertSeeText('Asignación');
        $response->assertSeeText('Vista rápida:');
        $response->assertSeeText('Posibles duplicados');
    }

    public function test_maintenance_index_has_operational_quick_filters(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Mis tickets');
        $response->assertSeeText('Disponibles para tomar');
        $response->assertSeeText('Todos los visibles');
    }

    public function test_maintenance_show_available_ticket_prompts_claim_before_state_change(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket disponible para UX maintenance',
            'description' => 'Debe pedir claim antes de cambiar estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('Ticket disponible para tomar');
        $response->assertSeeText('Tomar este ticket');
        $response->assertDontSeeText('Actualizar estado');
    }

    public function test_maintenance_show_assigned_ticket_displays_only_allowed_state_transitions(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket asignado para transiciones UX',
            'description' => 'Solo debe permitir transiciones válidas.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('Actualizar estado');
        $response->assertSeeText('En progreso');
        $response->assertDontSeeText('Resuelto');
        $response->assertDontSeeText('Rechazado');
    }

    // ── State History Display Tests ────────────────────────────────────────

    public function test_state_update_creates_state_history_entry(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket con historial',
            'description' => 'Test de creación de historial al cambiar estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.update-state', $ticket), [
                'to_state' => 'in_progress',
                'comment' => 'Revisando el ticket',
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'in_progress',
            'changed_by' => $maintenance->id,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'state' => 'in_progress',
        ]);
    }

    public function test_ticket_show_displays_existing_state_history(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket con historial visible',
            'description' => 'Verificar que show muestra el historial.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'high',
        ]);

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'in_progress',
            'changed_by' => $admin->id,
            'comment' => 'Revisando incidencia',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSeeText('Aún no hay cambios de estado registrados');
        $response->assertSeeText('Abierto');
        $response->assertSeeText('En progreso');
        $response->assertSeeText('Revisando incidencia');
    }

    public function test_ticket_show_displays_assignment_history_entries(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket con historial de asignación',
            'description' => 'Verificar que show muestra eventos de asignación.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $assignmentComment = 'Ticket asignado a Luis Guillermo por admin/super_admin: Admin';

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'open',
            'changed_by' => $admin->id,
            'comment' => $assignmentComment,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText($assignmentComment);
        $response->assertDontSeeText('Aún no hay cambios de estado registrados');
    }

    // ── Fase 0 Group A: reporter scope no se salta con filtros adicionales ──

    public function test_reporter_location_filter_does_not_leak_other_tickets(): void
    {
        // Reporters are redirected from /tickets (with any query params) to their
        // own board — scope isolation is enforced by the reporter board controller.
        $reporter = $this->createUserWithRole('reporter');
        $locationA = $this->createLocation(['name' => 'Aula Filtro A', 'room_code' => 'F-'.Str::upper(Str::random(5))]);

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['location_id' => $locationA->id]))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_reporter_category_filter_does_not_leak_other_tickets(): void
    {
        // Reporters are redirected from /tickets (with any query params) to their
        // own board — scope isolation is enforced by the reporter board controller.
        $reporter = $this->createUserWithRole('reporter');
        $categoryA = $this->createCategory(['name' => 'CategoriaFiltroA']);

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['category_id' => $categoryA->id]))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_reporter_date_range_filter_does_not_leak_other_tickets(): void
    {
        // Reporters are redirected to their own board before any query runs.
        $reporter = $this->createUserWithRole('reporter');
        $today = now()->toDateString();

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['from' => $today, 'to' => $today]))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    public function test_reporter_assignment_all_does_not_return_foreign_tickets(): void
    {
        // Reporters are redirected to their own board before any query runs.
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index', ['assignment' => 'all']))
            ->assertRedirect(route('reporter.tickets.index'));
    }

    // ── Fase 0 Group B: maintenance scope no se salta con filtros adicionales ──

    public function test_maintenance_location_filter_respects_visible_scope(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $locationA = $this->createLocation(['name' => 'Aula Maint A', 'room_code' => 'M-'.Str::upper(Str::random(5))]);
        $category = $this->createCategory();

        // Ticket en locationA asignado a maintenanceB — visible en locationA pero NO para maintenanceA
        $ajenoTitle = 'TICKET_MAINT_LOCATION_SCOPE_CHECK';
        $ajenoTicket = Ticket::create([
            'title' => $ajenoTitle,
            'description' => 'Ticket del otro tecnico en la misma ubicacion.',
            'reporter_id' => $reporter->id,
            'location_id' => $locationA->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'high',
        ]);
        $ajenoTicket->forceFill(['assigned_to' => $maintenanceB->id])->save();

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.index', ['location_id' => $locationA->id]));

        $response->assertOk();
        $response->assertDontSeeText($ajenoTitle);
    }

    public function test_maintenance_category_filter_respects_visible_scope(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $categoryA = $this->createCategory(['name' => 'CatMaintenanceA']);

        // Ticket en categoryA asignado a maintenanceB — visible en categoryA pero NO para maintenanceA
        $ajenoTitle = 'TICKET_MAINT_CATEGORY_SCOPE_CHECK';
        $ajenoTicket = Ticket::create([
            'title' => $ajenoTitle,
            'description' => 'Ticket del otro tecnico en la misma categoria.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $categoryA->id,
            'state' => 'in_progress',
            'priority' => 'critical',
        ]);
        $ajenoTicket->forceFill(['assigned_to' => $maintenanceB->id])->save();

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.index', ['category_id' => $categoryA->id]));

        $response->assertOk();
        $response->assertDontSeeText($ajenoTitle);
    }

    public function test_maintenance_date_range_filter_respects_visible_scope(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        // Ticket asignado a maintenanceB creado hoy — en el rango pero NO visible a maintenanceA
        $ajenoTitle = 'TICKET_MAINT_DATE_SCOPE_CHECK';
        $ajenoTicket = Ticket::create([
            'title' => $ajenoTitle,
            'description' => 'Ticket del otro tecnico dentro del rango de fechas.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'medium',
        ]);
        $ajenoTicket->forceFill(['assigned_to' => $maintenanceB->id, 'created_at' => now()])->save();

        $today = now()->toDateString();

        $response = $this
            ->actingAs($maintenanceA)
            ->get(route('tickets.index', ['from' => $today, 'to' => $today]));

        $response->assertOk();
        $response->assertDontSeeText($ajenoTitle);
    }

    public function test_reporter_can_see_state_history_but_not_update_state_form(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket reporter con historial',
            'description' => 'Reporter debe ver historial pero no formulario de estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'low',
        ]);

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'in_progress',
            'changed_by' => $admin->id,
            'comment' => 'Incidencia en revisión',
        ]);

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        // Reporter SÍ ve el historial
        $response->assertSeeText('Historial de estados');
        $response->assertSeeText('Incidencia en revisión');
        $response->assertDontSeeText('Aún no hay cambios de estado registrados');
        // Reporter NO ve el formulario de cambio de estado
        $response->assertDontSeeText('Actualizar estado');
    }

    // ── Community visibility on create ──────────────────────────────────────

    public function test_create_form_shows_community_visibility_option(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Visibilidad en Comunidad', false)
            ->assertSee('Mostrar este reporte en Comunidad', false)
            ->assertSee('No se mostrará tu nombre, correo ni teléfono', false);
    }

    public function test_ticket_is_public_by_default_when_community_visible_not_sent(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket sin campo visible',
            'description' => 'Descripcion larga para pasar validacion minima de longitud en el test.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket sin campo visible',
            'community_visible' => true,
        ]);
    }

    public function test_reporter_can_create_private_ticket_with_community_visible_false(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket privado de comunidad',
            'description' => 'Este ticket no debe aparecer en la comunidad segun preferencia del reporter.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'low',
            'community_visible' => '0',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket privado de comunidad',
            'community_visible' => false,
        ]);
    }

    public function test_reporter_can_create_public_ticket_with_community_visible_true(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket publico de comunidad',
            'description' => 'Este ticket debe aparecer en la comunidad segun preferencia del reporter.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'community_visible' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket publico de comunidad',
            'community_visible' => true,
        ]);
    }

    // ── Category community visibility defaults ────────────────────────────────

    public function test_create_form_shows_sensitive_category_hint(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Algunas categorías sensibles se mantienen privadas automáticamente', false);
    }

    public function test_ticket_default_follows_category_community_default_visible_false(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket sin campo visible categoria privada',
            'description' => 'La categoria tiene default privado y no se envio community_visible.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket sin campo visible categoria privada',
            'community_visible' => false,
        ]);
    }

    public function test_reporter_can_override_default_private_category_to_public(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket privado por default pero reporter activa',
            'description' => 'La categoria tiene default privado pero el reporter puede sobrescribir.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'low',
            'community_visible' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket privado por default pero reporter activa',
            'community_visible' => true,
        ]);
    }

    public function test_locked_private_category_ignores_community_visible_true_in_request(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket categoria bloqueada privada intento publico',
            'description' => 'El request intenta forzar community_visible=1 pero la categoria lo bloquea.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'community_visible' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket categoria bloqueada privada intento publico',
            'community_visible' => false,
        ]);
    }

    public function test_locked_public_category_ignores_community_visible_false_in_request(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory([
            'community_default_visible' => true,
            'community_visibility_locked' => true,
        ]);

        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket categoria bloqueada publica intento privado',
            'description' => 'El request intenta forzar community_visible=0 pero la categoria lo bloquea como publico.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'community_visible' => '0',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket categoria bloqueada publica intento privado',
            'community_visible' => true,
        ]);
    }

    public function test_create_form_shows_locked_message_when_old_category_is_locked(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        // Trigger a validation error so old input (including locked category) is flashed back.
        $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => '',
            'description' => '',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        // The GET create page, with old category_id flashed, should show the locked message.
        $this->actingAs($reporter)
            ->withSession(['_old_input' => ['category_id' => $category->id]])
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('no puede publicarse en Comunidad', false);
    }
}
