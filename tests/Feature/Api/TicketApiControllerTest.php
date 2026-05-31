<?php

namespace Tests\Feature\Api;

use App\Jobs\DetectDuplicates;
use App\Jobs\GenerateTicketEmbedding;
use App\Jobs\UpdateRecurrenceHistory;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
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

    public function test_reporter_only_sees_own_tickets_in_api_index(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ownTicket = Ticket::create([
            'title' => 'Ticket propio API',
            'description' => 'Ticket del reporter autenticado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $otherTicket = Ticket::create([
            'title' => 'Ticket ajeno API',
            'description' => 'Ticket de otro reporter.',
            'reporter_id' => $otherReporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->getJson(route('api.tickets.index'));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($ownTicket->id, $ids);
        $this->assertNotContains($otherTicket->id, $ids);
    }

    public function test_reporter_cannot_see_other_reporter_ticket_via_api_show(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket ajeno API show',
            'description' => 'El reporter no debe acceder a este ticket.',
            'reporter_id' => $otherReporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->getJson(route('api.tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_reporter_api_index_search_only_own_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $secretTitle = 'TICKET_AJENO_SECRETO_123';

        $otherTicket = Ticket::create([
            'title' => $secretTitle,
            'description' => 'Ticket ajeno con titulo secreto.',
            'reporter_id' => $otherReporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'high',
        ]);

        $response = $this->getJson(route('api.tickets.index', ['search' => $secretTitle]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($otherTicket->id, $ids);
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

        $response->assertCreated();
        $response->assertJsonPath('duplicate', false);
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_api_store_returns_warning_pending_when_dedup_enabled(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => false,
            'queue.default' => 'sync',
        ]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket con verificacion pendiente',
            'description' => 'Descripcion valida para validar warning pendiente en async.',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('duplicate_warning_pending', true);
    }

    public function test_api_store_dispatches_dedup_jobs_after_response(): void
    {
        Queue::fake();

        config([
            'ai.enabled' => true,
            'ai.huggingface.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
            'queue.default' => 'database',
        ]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $existing = Ticket::create([
            'title' => 'Proyector sala A-201 no enciende',
            'description' => 'Ticket existente para dedup.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $existing->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $existing->embeddingText()),
            'is_duplicate' => false,
        ]);

        $payload = [
            'title' => 'Mesa rota en sala A-201',
            'description' => 'La mesa tiene una pata rota y se mueve al apoyarse.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertCreated();
        Queue::assertPushed(GenerateTicketEmbedding::class);
        Queue::assertPushed(DetectDuplicates::class);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Mesa rota en sala A-201',
            'reporter_id' => $user->id,
        ]);
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
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
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

    public function test_admin_can_delete_ticket_even_if_storage_cleanup_fails(): void
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
    // ── Test: effective_duplicate shown in API response ───────────────────

    public function test_api_show_returns_duplicate_warning_false_when_dismissed(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket con IA duplicado y revisión dismissed',
            'description' => 'Descripcion larga suficiente para el test de review dismissed.',
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
            'is_duplicate' => true,   // AI detected
            'review_status' => TicketEmbedding::REVIEW_DISMISSED, // Human overrode
        ]);

        $response = $this->getJson(route('api.tickets.show', $ticket));

        $response->assertOk();
        $response->assertJsonPath('duplicate_warning', false);        // effective = false
        $response->assertJsonPath('duplicate_ai_detected', true);     // raw AI = true
        $response->assertJsonPath('duplicate_review_status', TicketEmbedding::REVIEW_DISMISSED);
    }

    // ── Test: ?duplicates=1 filter ────────────────────────────────────────

    public function test_api_index_filter_duplicates_returns_only_effective_duplicates(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        // 1. AI duplicado sin revisión → debe aparecer
        $dupAi = Ticket::create([
            'title' => 'Duplicado IA sin revisión',
            'description' => 'Ticket marcado como duplicado por IA sin revisión humana.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $dupAi->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => 'hash-dup-ai',
            'is_duplicate' => true,
            'review_status' => null,
        ]);

        // 2. AI duplicado dismissed → NO debe aparecer
        $dismissed = Ticket::create([
            'title' => 'Duplicado dismisseado',
            'description' => 'Ticket cuyo duplicado fue descartado por el revisor.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $dismissed->id,
            'embedding_vector' => [0.3, 0.4],
            'description_hash' => 'hash-dismissed',
            'is_duplicate' => true,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        // 3. Confirmado manualmente (AI=false) → debe aparecer
        $confirmed = Ticket::create([
            'title' => 'Duplicado confirmado manualmente',
            'description' => 'La IA no lo marcó, pero el revisor lo confirmó.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $confirmed->id,
            'embedding_vector' => [0.5, 0.6],
            'description_hash' => 'hash-confirmed',
            'is_duplicate' => false,
            'review_status' => TicketEmbedding::REVIEW_CONFIRMED,
        ]);

        // 4. Ticket normal → NO debe aparecer
        $normal = Ticket::create([
            'title' => 'Ticket normal sin duplicado',
            'description' => 'Ticket sin ninguna marca de duplicado.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
        TicketEmbedding::create([
            'ticket_id' => $normal->id,
            'embedding_vector' => [0.7, 0.8],
            'description_hash' => 'hash-normal',
            'is_duplicate' => false,
            'review_status' => null,
        ]);

        $response = $this->getJson(route('api.tickets.index', ['duplicates' => '1']));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($dupAi->id, $ids, 'AI duplicado sin revisión debe aparecer');
        $this->assertContains($confirmed->id, $ids, 'Confirmado manualmente debe aparecer');
        $this->assertNotContains($dismissed->id, $ids, 'Dismissed NO debe aparecer');
        $this->assertNotContains($normal->id, $ids, 'Ticket normal NO debe aparecer');
    }

    // ── Test: reviewDuplicate endpoint ────────────────────────────────────

    public function test_admin_can_review_duplicate_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = Ticket::create([
            'title' => 'Ticket a revisar como duplicado',
            'description' => 'Descripcion suficientemente larga para el test de review.',
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

        $response = $this->patchJson(
            route('api.tickets.duplicate-review.update', $ticket),
            [
                'review_status' => TicketEmbedding::REVIEW_DISMISSED,
                'review_note' => 'La IA falló, no es un duplicado real.',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('message', 'Revisión de duplicado actualizada.');
        $response->assertJsonPath('data.duplicate_warning', false);
        $response->assertJsonPath('data.duplicate_ai_detected', true);
        $response->assertJsonPath('data.duplicate_review_status', TicketEmbedding::REVIEW_DISMISSED);

        $this->assertDatabaseHas('ticket_embeddings', [
            'ticket_id' => $ticket->id,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
            'reviewed_by' => $admin->id,
            'review_note' => 'La IA falló, no es un duplicado real.',
        ]);

        // AI column unchanged
        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertTrue($embedding->is_duplicate);
        $this->assertNotNull($embedding->reviewed_at);
    }

    public function test_maintenance_can_review_duplicate_via_api(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = Ticket::create([
            'title' => 'Ticket revisado por maintenance',
            'description' => 'Descripcion del ticket que va a ser revisado por maintenance.',
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

        $response = $this->patchJson(
            route('api.tickets.duplicate-review.update', $ticket),
            ['review_status' => TicketEmbedding::REVIEW_CONFIRMED]
        );

        $response->assertOk();
        $response->assertJsonPath('data.duplicate_review_status', 'confirmed');
    }

    public function test_reporter_cannot_review_duplicate_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket protegido de revisión',
            'description' => 'Reporter no debe poder revisar duplicados.',
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

        $response = $this->patchJson(
            route('api.tickets.duplicate-review.update', $ticket),
            ['review_status' => TicketEmbedding::REVIEW_DISMISSED]
        );

        $response->assertForbidden();

        // review_status must remain null
        $this->assertDatabaseMissing('ticket_embeddings', [
            'ticket_id' => $ticket->id,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);
    }

    public function test_review_endpoint_returns_422_when_no_embedding_exists(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = Ticket::create([
            'title' => 'Ticket sin embedding',
            'description' => 'No tiene embedding porque el job no se ejecutó aún.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        // No embedding created

        $response = $this->patchJson(
            route('api.tickets.duplicate-review.update', $ticket),
            ['review_status' => TicketEmbedding::REVIEW_DISMISSED]
        );

        $response->assertUnprocessable();
    }

    public function test_review_endpoint_validates_invalid_review_status(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = Ticket::create([
            'title' => 'Ticket para validación de status inválido',
            'description' => 'Descripcion del ticket con status inválido.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson(
            route('api.tickets.duplicate-review.update', $ticket),
            ['review_status' => 'invalid_value']
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['review_status']);
    }
}
