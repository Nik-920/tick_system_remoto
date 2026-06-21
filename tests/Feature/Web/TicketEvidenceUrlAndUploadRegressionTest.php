<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for ticket evidence URL handling and file upload fixes.
 *
 * Prevents:
 *  - evidence links using raw file_url (exposing production host in local dev)
 *  - ReporterTicketController::update() storing files on ephemeral local disk
 *  - Unauthorized users accessing evidence from foreign tickets
 *  - railway run prune blocking the CD deploy
 */
class TicketEvidenceUrlAndUploadRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── Evidence URL rendering ────────────────────────────────────────────────

    public function test_tickets_show_renders_media_view_route_not_raw_file_url(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $supabaseUrl = 'https://example.supabase.co/storage/v1/object/public/TableTicket/tickets/media/'.$ticket->id.'/photo.jpg';
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $supabaseUrl,
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $response = $this->actingAs($reporter)->get(route('tickets.show', $ticket->id));

        $response->assertOk();

        $proxyUrl = route('tickets.media.view', [$ticket->id, TicketMedia::where('ticket_id', $ticket->id)->first()->id]);
        $response->assertSee($proxyUrl, false);
        $response->assertDontSee($supabaseUrl, false);
    }

    public function test_tickets_show_does_not_expose_production_railway_host_in_html(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://ticksystemremoto-production.up.railway.app/storage/ticket-evidence/'.$ticket->id.'/old-file.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $response = $this->actingAs($reporter)->get(route('tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertDontSee('ticksystemremoto-production.up.railway.app', false);
    }

    public function test_tickets_show_evidence_link_uses_proxy_route_not_storage_path(): void
    {
        $admin = $this->userWithRole('admin');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.supabase.co/storage/v1/object/public/bucket/file.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $response = $this->actingAs($admin)->get(route('tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSee(route('tickets.media.view', [$ticket->id, $media->id]), false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }

    // ── Proxy controller access control ──────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_media_proxy(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $media = $this->mediaFor($ticket, $reporter, 'https://example.com/file.jpg');

        $this->get(route('tickets.media.view', [$ticket->id, $media->id]))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_owner_can_access_own_ticket_evidence_proxy(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://localhost']);

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        Storage::disk('public')->put('test-evidence/file.jpg', 'image-bytes');
        $localUrl = 'http://localhost/storage/test-evidence/file.jpg';

        $media = $this->mediaFor($ticket, $reporter, $localUrl);

        $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]))
            ->assertOk();
    }

    public function test_other_reporter_cannot_view_foreign_ticket_evidence(): void
    {
        $owner = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($owner);
        $media = $this->mediaFor($ticket, $owner, 'https://example.com/private.jpg');

        $this->actingAs($other)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]))
            ->assertForbidden();
    }

    public function test_admin_can_access_any_ticket_evidence_proxy(): void
    {
        $admin = $this->userWithRole('admin');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $media = $this->mediaFor($ticket, $reporter, 'https://example.supabase.co/storage/v1/object/public/TableTicket/file.jpg');

        $response = $this->actingAs($admin)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]));

        $response->assertRedirect('https://example.supabase.co/storage/v1/object/public/TableTicket/file.jpg');
    }

    public function test_proxy_redirects_to_absolute_supabase_url(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $supabaseUrl = 'https://proj.supabase.co/storage/v1/object/public/TableTicket/tickets/media/'.$ticket->id.'/photo.jpg';
        $media = $this->mediaFor($ticket, $reporter, $supabaseUrl);

        $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]))
            ->assertRedirect($supabaseUrl);
    }

    public function test_proxy_returns_404_for_missing_local_file(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://localhost']);

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $media = $this->mediaFor($ticket, $reporter, 'http://localhost/storage/missing/file.jpg');

        $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]))
            ->assertNotFound();
    }

    public function test_proxy_streams_local_file_when_it_exists(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://localhost']);

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        Storage::disk('public')->put('ticket-evidence/'.$ticket->id.'/photo.jpg', 'fake-image-bytes');
        $localUrl = 'http://localhost/storage/ticket-evidence/'.$ticket->id.'/photo.jpg';
        $media = $this->mediaFor($ticket, $reporter, $localUrl, 'image');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_proxy_returns_404_when_media_belongs_to_different_ticket(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticketA = $this->openTicketFor($reporter);
        $ticketB = $this->openTicketFor($reporter);
        $media = $this->mediaFor($ticketB, $reporter, 'https://example.com/file.jpg');

        $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticketA->id, $media->id]))
            ->assertNotFound();
    }

    // ── Upload via tickets.store (TicketCreationService → Supabase) ──────────

    public function test_create_ticket_with_valid_image_creates_ticket_media_record(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket con imagen adjunta',
            'description' => 'Descripción suficientemente larga para pasar la validación de evidencias adjuntas.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'media_files' => [UploadedFile::fake()->image('foto.jpg', 200, 200)],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $ticket = Ticket::query()->where('title', 'Ticket con imagen adjunta')->firstOrFail();

        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $reporter->id,
            'file_type' => 'image',
        ]);
    }

    public function test_create_ticket_form_has_multipart_encoding(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('multipart/form-data', false);
        $response->assertSee('name="media_files[]"', false);
    }

    public function test_create_ticket_rejects_invalid_mime_type(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket archivo inválido',
            'description' => 'Descripción suficientemente larga para pasar la validación mínima del formulario.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'media_files' => [UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['media_files.0']);
    }

    public function test_create_ticket_rejects_oversized_file(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket archivo grande',
            'description' => 'Descripción suficientemente larga para pasar la validación mínima del formulario.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'media_files' => [UploadedFile::fake()->image('huge.jpg')->size(11000)],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['media_files.0']);
    }

    // ── Upload via reporter.tickets.update (now uses TicketMediaStorageService) ─

    public function test_reporter_update_stores_evidence_via_supabase_service(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->validUpdatePayload(), [
                'new_images' => [UploadedFile::fake()->image('evidence.jpg', 300, 300)->size(400)],
            ]),
        );

        $response->assertRedirect(route('reporter.tickets.show', $ticket->id));

        $media = TicketMedia::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($media);
        $this->assertSame($reporter->id, $media->uploaded_by);
        $this->assertSame('image', $media->file_type);
    }

    public function test_reporter_update_file_url_does_not_store_production_railway_host(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://localhost:8000']);

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->validUpdatePayload(), [
                'new_images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)->size(50)],
            ]),
        );

        $media = TicketMedia::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($media);
        $this->assertStringNotContainsString('ticksystemremoto-production.up.railway.app', (string) $media->file_url);
        $this->assertStringNotContainsString('localhost:8000/storage/ticket-evidence', (string) $media->file_url);
    }

    public function test_reporter_update_invalid_file_type_is_rejected(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->validUpdatePayload(), [
                'new_images' => [UploadedFile::fake()->create('script.sh', 50, 'application/x-sh')],
            ]),
        )->assertSessionHasErrors(['new_images.0']);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket de prueba '.Str::uuid(),
            'description' => 'Descripción de prueba con largo suficiente para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function mediaFor(Ticket $ticket, User $uploader, string $fileUrl, string $fileType = 'image'): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'EVID-01'],
            [
                'name' => 'Laboratorio Evidencias',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-evidence-test-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'EvidenciaTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests de evidencias'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function validUpdatePayload(): array
    {
        return [
            'title' => 'Título válido para edición del ticket',
            'description' => 'Descripción válida con la longitud mínima necesaria para actualizar el ticket.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
        ];
    }
}
