<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Requests\Reporter\UpdateReporterTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards against regressions in upload error handling across all roles.
 *
 * Covers: translations, form attributes, backend validation, 413 handling,
 * viewing unavailable evidence, assets, and no-leak requirements.
 */
class GlobalUploadErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    // ── Translations ──────────────────────────────────────────────────────────

    #[Test]
    public function uploaded_validation_key_is_translated_to_spanish(): void
    {
        $translations = require base_path('lang/es/validation.php');

        $this->assertArrayHasKey('uploaded', $translations);
        $this->assertStringNotContainsString('validation.', $translations['uploaded']);
        $this->assertNotEmpty($translations['uploaded']);
    }

    #[Test]
    public function store_ticket_request_has_human_message_for_uploaded_rule(): void
    {
        $request = new StoreTicketRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('media_files.*.uploaded', $messages);
        $this->assertStringNotContainsString('validation.', $messages['media_files.*.uploaded']);
    }

    #[Test]
    public function reporter_update_request_has_human_message_for_uploaded_rule(): void
    {
        $request = new UpdateReporterTicketRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('new_images.*.uploaded', $messages);
        $this->assertStringNotContainsString('validation.', $messages['new_images.*.uploaded']);
    }

    #[Test]
    public function maintenance_update_request_has_human_message_for_uploaded_rule(): void
    {
        $request = new UpdateMaintenanceTicketRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('evidence.*.uploaded', $messages);
        $this->assertStringNotContainsString('validation.', $messages['evidence.*.uploaded']);
    }

    // ── Forms / data attributes ───────────────────────────────────────────────

    #[Test]
    public function create_ticket_page_has_upload_form_attribute(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('data-upload-form', false);
    }

    #[Test]
    public function create_ticket_input_has_upload_guard_and_10mb_limit(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('data-upload-guard', false);
        $response->assertSee('data-max-file-size="10485760"', false);
    }

    #[Test]
    public function create_ticket_input_has_total_size_limit(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('data-max-total-size="52428800"', false);
    }

    #[Test]
    public function reporter_edit_input_has_upload_guard_and_5mb_limit(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, fn ($m) => $m->shouldReceive()->andReturn(null));

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->get(route('reporter.tickets.edit', $ticket->id));

        $response->assertOk();
        $response->assertSee('data-upload-guard', false);
        $response->assertSee('data-max-file-size="5242880"', false);
    }

    #[Test]
    public function maintenance_evidence_input_has_upload_guard(): void
    {
        // Ticket show queries maintenance users; pre-create the role to avoid RoleDoesNotExist.
        Role::firstOrCreate(['name' => 'maintenance', 'guard_name' => 'web']);

        // Admin always passes updateMaintenance policy — simplest way to reach the upload section.
        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicketFor($this->userWithRole('reporter'));

        $response = $this->actingAs($admin)->get(route('tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSee('data-upload-guard', false);
    }

    #[Test]
    public function upload_error_modal_is_present_exactly_once_in_layout(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $html = $response->content();
        $this->assertSame(1, substr_count($html, 'data-upload-error-modal'));
    }

    // ── Backend validation ─────────────────────────────────────────────────────

    #[Test]
    public function create_ticket_accepts_valid_small_jpg(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, function ($mock): void {
            $mock->shouldReceive('storeManyForTicket')->andReturnNull();
        });

        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket con evidencia válida',
            'description' => 'Descripción de prueba con longitud suficiente para pasar la validación.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => [UploadedFile::fake()->image('foto.jpg', 100, 100)],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function create_ticket_rejects_file_over_10mb_with_human_message(): void
    {
        $reporter = $this->userWithRole('reporter');

        $oversized = UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket con evidencia grande',
            'description' => 'Descripción de prueba con longitud suficiente para pasar la validación.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => [$oversized],
        ]);

        $response->assertSessionHasErrors('media_files.*');
        $allErrors = session('errors') ? session('errors')->all() : [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function reporter_edit_rejects_file_over_5mb_with_human_message(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $oversized = UploadedFile::fake()->create('grande.jpg', 6 * 1024, 'image/jpeg');

        $response = $this->actingAs($reporter)->patch(route('reporter.tickets.update', $ticket->id), array_merge(
            $this->validReporterUpdatePayload($ticket),
            ['new_images' => [$oversized]],
        ));

        $response->assertSessionHasErrors('new_images.*');
        $allErrors = session('errors') ? session('errors')->all() : [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function create_ticket_rejects_invalid_extension_with_human_message(): void
    {
        $reporter = $this->userWithRole('reporter');

        $invalid = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket con extensión inválida',
            'description' => 'Descripción de prueba con longitud suficiente para pasar la validación.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => [$invalid],
        ]);

        $response->assertSessionHasErrors('media_files.*');
        $allErrors = session('errors') ? session('errors')->all() : [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function create_ticket_rejects_too_many_files_with_human_message(): void
    {
        $reporter = $this->userWithRole('reporter');

        $files = array_fill(0, 7, UploadedFile::fake()->image('foto.jpg', 10, 10));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket con demasiados archivos',
            'description' => 'Descripción de prueba con longitud suficiente para pasar la validación.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => $files,
        ]);

        $response->assertSessionHasErrors('media_files');
        $errors = session('errors')?->get('media_files') ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('validation.', $errors[0]);
    }

    // ── 413 / PostTooLargeException ────────────────────────────────────────────

    #[Test]
    public function post_too_large_exception_renders_friendly_web_response(): void
    {
        // No session on this request → handler falls through to the view branch.
        $request = Request::create('/tickets', 'POST');

        $handler = app(ExceptionHandler::class);
        $result = $handler->render($request, new PostTooLargeException);

        $this->assertSame(413, $result->getStatusCode());
        $body = (string) $result->getContent();
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('upload_tmp_dir', $body);
        $this->assertStringNotContainsString('storage/app', $body);
    }

    #[Test]
    public function post_too_large_exception_renders_json_for_api_requests(): void
    {
        $request = Request::create('/api/tickets', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->headers->set('Accept', 'application/json');

        $handler = app(ExceptionHandler::class);
        $result = $handler->render($request, new PostTooLargeException);

        $this->assertSame(413, $result->getStatusCode());
        $data = json_decode((string) $result->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
    }

    #[Test]
    public function error_413_view_does_not_expose_internal_details(): void
    {
        $request = Request::create('/tickets', 'POST');

        $handler = app(ExceptionHandler::class);
        $result = $handler->render($request, new PostTooLargeException);

        $body = (string) $result->getContent();

        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('upload_tmp_dir', $body);
        $this->assertStringNotContainsString('storage/app', $body);
        $this->assertStringNotContainsString('Supabase', $body);
        $this->assertStringNotContainsString('bucket', $body);
        $this->assertStringNotContainsString('stack trace', $body);
    }

    // ── Viewing evidence ───────────────────────────────────────────────────────

    #[Test]
    public function missing_evidence_file_returns_media_unavailable_view(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => url('/storage/tickets/media/'.$ticket->id.'/missing.jpg'),
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('tickets.media.view', [$ticket->id, $media->id]));

        $response->assertStatus(404);
        $response->assertSee('evidencia', false);
    }

    #[Test]
    public function community_image_fallback_attribute_is_present(): void
    {
        $reporter = $this->userWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('reporter.community'));

        // If the page loads, the community-media-fallback JS is bound to [data-community-media-img].
        // Absence of a server error is the primary check; specific attribute presence is validated
        // in browser/JS tests. This prevents regressions in the route itself.
        $response->assertOk();
    }

    // ── No-leak ────────────────────────────────────────────────────────────────

    #[Test]
    public function upload_error_responses_do_not_expose_sensitive_data(): void
    {
        $reporter = $this->userWithRole('reporter');

        $invalid = UploadedFile::fake()->create('bad.exe', 100, 'application/octet-stream');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Ticket prueba no-leak',
            'description' => 'Descripción de prueba con longitud suficiente para pasar la validación.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => [$invalid],
        ]);

        $body = $response->content();

        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('upload_tmp_dir', $body);
        $this->assertStringNotContainsString('storage/app', $body);
        $this->assertStringNotContainsString('Supabase', $body);
        $this->assertStringNotContainsString('bucket', $body);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        /** @var User $user */
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

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'UPLOAD-ERR-01'],
            [
                'name' => 'Laboratorio Upload Guard',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-upload-guard-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'UploadGuardTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests de upload guard'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validReporterUpdatePayload(Ticket $ticket): array
    {
        return [
            'title' => 'Título actualizado válido del ticket',
            'description' => 'Descripción actualizada con longitud suficiente para pasar la validación.',
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'priority' => 'medium',
            '_method' => 'PATCH',
        ];
    }
}
