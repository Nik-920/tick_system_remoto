<?php

namespace Tests\Feature\Api;

use App\Jobs\DetectDuplicates;
use App\Jobs\GenerateTicketEmbedding;
use App\Jobs\UpdateRecurrenceHistory;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_tickets_from_api(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Proyector sin imagen API',
            'description' => 'API test para listado de tickets.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->getJson(route('api.tickets.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'state',
                    'priority',
                ],
            ],
        ]);
    }

    public function test_api_store_creates_ticket_and_returns_201(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Incidencia creada via API',
            'description' => 'Se registra un ticket de ejemplo creado desde endpoint API.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('duplicate', false);
        $response->assertJsonPath('data.title', 'Incidencia creada via API');
        $this->assertDatabaseHas('tickets', [
            'title' => 'Incidencia creada via API',
            'reporter_id' => $user->id,
        ]);
    }

    public function test_api_store_can_persist_ticket_media_files(): void
    {
        config([
            'services.supabase.storage.domain_buckets.tickets' => 'TableTicket',
            'services.supabase.storage.domain_prefixes.tickets' => 'tickets/media',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(route('api.tickets.store'), [
                'title' => 'Ticket con adjuntos API',
                'description' => 'Descripcion suficientemente larga para crear ticket con evidencias adjuntas.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'high',
                'media_files' => [
                    UploadedFile::fake()->image('evidencia.jpg', 120, 120),
                    UploadedFile::fake()->create('reporte.pdf', 300, 'application/pdf'),
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.media');

        $ticketId = (string) $response->json('data.id');
        $this->assertDatabaseCount('ticket_media', 2);
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticketId,
            'uploaded_by' => $user->id,
            'file_type' => 'image',
        ]);
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticketId,
            'uploaded_by' => $user->id,
            'file_type' => 'document',
        ]);

        $mediaUrls = Ticket::query()
            ->findOrFail($ticketId)
            ->media()
            ->pluck('file_url')
            ->all();

        $this->assertCount(2, $mediaUrls);
        foreach ($mediaUrls as $mediaUrl) {
            $this->assertStringContainsString('/storage/v1/object/public/TableTicket/tickets/media/'.$ticketId.'/', $mediaUrl);
        }
    }

    public function test_api_store_detects_duplicate_ticket_in_window(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Ticket existente',
            'description' => 'Ya hay un ticket en esta ubicacion y categoria.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $payload = [
            'title' => 'Nuevo intento duplicado',
            'description' => 'Intento crear otro ticket para la misma incidencia activa.',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertOk();
        $response->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_reporter_cannot_change_ticket_state_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket sin permiso de cambio',
            'description' => 'El reporter no deberia poder cambiar estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson(route('api.tickets.update-state', $ticket), [
            'to_state' => 'in_progress',
            'comment' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
    }

    public function test_maintenance_can_change_ticket_state_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket para atender',
            'description' => 'Cambio de estado permitido para maintenance.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson(route('api.tickets.update-state', $ticket), [
            'to_state' => 'in_progress',
            'comment' => 'Tecnico asignado.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.state', 'in_progress');
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'to_state' => 'in_progress',
            'changed_by' => $maintenance->id,
        ]);
    }

    public function test_admin_can_delete_ticket_and_remove_media_from_storage(): void
    {
        config([
            'services.supabase.storage.domain_buckets.tickets' => 'TableTicket',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket a eliminar API',
            'description' => 'Ticket con adjunto para validar limpieza en storage al eliminar.',
            'reporter_id' => $admin->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $mediaPath = 'tickets/media/'.$ticket->id.'/evidencia.png';
        Storage::disk('public')->put($mediaPath, 'image-content');

        $media = TicketMedia::query()->create([
            'ticket_id' => $ticket->id,
            'file_url' => '/storage/v1/object/public/TableTicket/'.$mediaPath,
            'file_type' => 'image',
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->deleteJson(route('api.tickets.destroy', $ticket));

        $response->assertOk();
        $response->assertJsonPath('message', 'Ticket eliminado correctamente.');

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
        ]);
        $this->assertDatabaseMissing('ticket_media', [
            'id' => $media->id,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($mediaPath));
    }

    public function test_reporter_cannot_delete_ticket_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket protegido API',
            'description' => 'Un reporter no debe eliminar tickets.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->deleteJson(route('api.tickets.destroy', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
        ]);
    }

    public function test_delete_ticket_keeps_database_deletion_when_storage_cleanup_fails(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket cleanup fallido API',
            'description' => 'Si falla storage, el ticket igual debe quedar eliminado de BD.',
            'reporter_id' => $admin->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketMedia::query()->create([
            'ticket_id' => $ticket->id,
            'file_url' => '/storage/v1/object/public/TableTicket/tickets/media/'.$ticket->id.'/fallo.png',
            'file_type' => 'image',
            'uploaded_by' => $admin->id,
        ]);

        $this->mock(TicketMediaStorageService::class, function ($mock): void {
            $mock->shouldReceive('deleteManyByUrls')
                ->once()
                ->andThrow(new RuntimeException('storage-delete-failed'));
        });

        $response = $this->deleteJson(route('api.tickets.destroy', $ticket));

        $response->assertOk();
        $response->assertJsonPath('message', 'Ticket eliminado correctamente.');

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
        ]);
    }

    public function test_correlation_id_is_propagated_to_ticket_ai_jobs(): void
    {
        Queue::fake();

        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();
        $correlationId = 'corr-ticket-create-001';

        $payload = [
            'title' => 'Ticket con correlacion',
            'description' => 'Prueba de propagacion correlation id en jobs de IA.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ];

        $response = $this
            ->withHeader('X-Correlation-Id', $correlationId)
            ->postJson(route('api.tickets.store'), $payload);

        $response->assertCreated();

        Queue::assertPushed(GenerateTicketEmbedding::class, function (GenerateTicketEmbedding $job) use ($correlationId): bool {
            return $job->correlationId === $correlationId;
        });

        Queue::assertPushed(DetectDuplicates::class, function (DetectDuplicates $job) use ($correlationId): bool {
            return $job->correlationId === $correlationId;
        });
    }

    public function test_correlation_id_is_propagated_to_recurrence_job_on_resolve(): void
    {
        Queue::fake();

        config([
            'ai.enabled' => true,
            'ai.recurrence.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket listo para resolver',
            'description' => 'Se valida propagacion correlation id en recurrencia.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'medium',
        ]);

        $correlationId = 'corr-ticket-resolve-001';

        $response = $this
            ->withHeader('X-Correlation-Id', $correlationId)
            ->patchJson(route('api.tickets.update-state', $ticket), [
                'to_state' => 'resolved',
                'comment' => 'Resuelto para validar correlacion.',
            ]);

        $response->assertOk();

        Queue::assertPushed(UpdateRecurrenceHistory::class, function (UpdateRecurrenceHistory $job) use ($ticket, $correlationId): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === $correlationId;
        });
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

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Laboratorio API',
            'building' => 'Edificio B',
            'floor' => '1',
            'room_code' => 'B-101',
            'qr_token' => 'qr-b-101',
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Red',
            'icon' => 'wifi',
            'description' => 'Incidencias de conectividad',
        ]);
    }
}
