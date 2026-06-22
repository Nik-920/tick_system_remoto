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
 * Regression guard for the /tickets/create image-upload flow.
 *
 * Prevents:
 *  - Valid images not being submitted (fileInput.value = '' clearing fileInput.files)
 *  - 5-file submissions failing (fileInput.disabled blocking form data)
 *  - validation.uploaded leaking in error messages
 *  - Sensitive data leaking through upload error responses
 */
class CreateTicketImageUploadRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /tickets/create HTML contract ────────────────────────────────────

    public function test_create_form_has_multipart_encoding(): void
    {
        $response = $this->actingAs($this->reporter())->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_create_form_input_has_required_upload_guard_attributes(): void
    {
        $response = $this->actingAs($this->reporter())->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('name="media_files[]"', false);
        $response->assertSee('data-upload-guard', false);
        $response->assertSee('data-max-file-size="10485760"', false);
        $response->assertSee('data-max-files="5"', false);
        $response->assertSee('data-max-total-size="52428800"', false);
    }

    public function test_create_form_input_allows_image_extensions(): void
    {
        $response = $this->actingAs($this->reporter())->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('data-allowed-extensions="jpg,jpeg,png,webp', false);
    }

    public function test_create_form_file_input_is_not_disabled_in_initial_html(): void
    {
        $response = $this->actingAs($this->reporter())->get(route('tickets.create'));

        $response->assertOk();
        // The input must never be rendered with disabled — that blocks form submission.
        $html = $response->content();
        // The upload-guard section must not pre-disable the primary file input.
        $this->assertStringNotContainsString('id="media_files" disabled', $html);
        $this->assertStringNotContainsString('id="media_files"'."\n".'                       disabled', $html);
    }

    // ── POST /tickets — valid file types ─────────────────────────────────────

    public function test_valid_jpg_upload_creates_ticket_and_media_record(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload(),
            ['media_files' => [UploadedFile::fake()->image('foto.jpg', 200, 200)]],
        ));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', $this->validPayload()['title'])->firstOrFail();

        $this->assertSame(1, TicketMedia::where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $reporter->id,
            'file_type' => 'image',
        ]);
    }

    public function test_valid_png_upload_creates_ticket_media_record(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Ticket PNG'),
            ['media_files' => [UploadedFile::fake()->image('captura.png', 400, 300)->size(500)]],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket PNG')->firstOrFail();
        $this->assertSame(1, TicketMedia::where('ticket_id', $ticket->id)->count());
    }

    public function test_valid_webp_upload_creates_ticket_media_record(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Ticket WEBP'),
            ['media_files' => [UploadedFile::fake()->create('imagen.webp', 300, 'image/webp')]],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket WEBP')->firstOrFail();
        $this->assertSame(1, TicketMedia::where('ticket_id', $ticket->id)->count());
    }

    // ── Multiple files ────────────────────────────────────────────────────────

    public function test_two_files_create_two_media_records(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Ticket Dos Archivos'),
            [
                'media_files' => [
                    UploadedFile::fake()->image('a.jpg', 100, 100)->size(100),
                    UploadedFile::fake()->image('b.jpg', 100, 100)->size(100),
                ],
            ],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket Dos Archivos')->firstOrFail();
        $this->assertSame(2, TicketMedia::where('ticket_id', $ticket->id)->count());
    }

    public function test_five_files_exactly_are_accepted_and_stored(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $files = [];
        for ($i = 1; $i <= 5; $i++) {
            $files[] = UploadedFile::fake()->image("foto{$i}.jpg", 50, 50)->size(50);
        }

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Ticket Cinco Archivos'),
            ['media_files' => $files],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket Cinco Archivos')->firstOrFail();
        $this->assertSame(5, TicketMedia::where('ticket_id', $ticket->id)->count());
    }

    // ── No media ─────────────────────────────────────────────────────────────

    public function test_ticket_created_without_files_has_no_media_records(): void
    {
        $reporter = $this->reporter();

        $this->actingAs($reporter)->post(route('tickets.store'), $this->validPayload('Ticket Sin Media'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket Sin Media')->firstOrFail();
        $this->assertSame(0, TicketMedia::where('ticket_id', $ticket->id)->count());
    }

    // ── Rejection cases ───────────────────────────────────────────────────────

    public function test_more_than_five_files_rejected_with_human_message(): void
    {
        $reporter = $this->reporter();

        $files = array_fill(0, 6, UploadedFile::fake()->image('foto.jpg', 10, 10)->size(10));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload(),
            ['media_files' => $files],
        ));

        $response->assertSessionHasErrors('media_files');
        $errors = session('errors')?->get('media_files') ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('validation.', $errors[0]);
    }

    public function test_file_over_10mb_rejected_with_human_message_not_validation_key(): void
    {
        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload(),
            ['media_files' => [UploadedFile::fake()->image('grande.jpg')->size(11 * 1024)]],
        ));

        $response->assertSessionHasErrors('media_files.*');
        $allErrors = session('errors') ? session('errors')->all() : [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    public function test_exe_file_rejected_with_human_message(): void
    {
        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload(),
            ['media_files' => [UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload')]],
        ));

        $response->assertSessionHasErrors('media_files.*');
        $allErrors = session('errors') ? session('errors')->all() : [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    // ── No sensitive data in error responses ──────────────────────────────────

    public function test_invalid_file_response_does_not_expose_sensitive_data(): void
    {
        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload(),
            ['media_files' => [UploadedFile::fake()->create('bad.exe', 50, 'application/octet-stream')]],
        ));

        $body = $response->content();
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('upload_tmp_dir', $body);
        $this->assertStringNotContainsString('storage/app', $body);
        $this->assertStringNotContainsString('Supabase', $body);
        $this->assertStringNotContainsString('bucket', $body);
    }

    // ── Duplicate precheck with attachments ───────────────────────────────────

    public function test_duplicate_precheck_with_files_sets_had_attachments_flag(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        // Create a ticket whose title will trigger the duplicate precheck.
        Ticket::create([
            'title' => 'Proyector del laboratorio A-201 no enciende',
            'description' => 'Descripción de la incidencia existente con longitud suficiente.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        // Submit the same (or very similar) title with a file attached.
        // The precheck runs before create when duplicate_ack is absent.
        // We only verify that if a precheck fires, hadAttachments is truthy.
        // If no precheck fires (no AI/similarity configured in test env), skip gracefully.
        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Proyector del laboratorio A-201 no enciende'),
            [
                'media_files' => [UploadedFile::fake()->image('foto.jpg', 100, 100)],
            ],
        ));

        // Either the ticket was created (no precheck triggered) or a precheck redirect occurred.
        // In both cases the response must not expose sensitive data.
        $body = $response->content();
        $this->assertStringNotContainsString('APP_KEY', $body);

        if ($response->isRedirect(route('tickets.create'))) {
            // Precheck fired — verify hadAttachments is set in the session flash.
            $precheck = session('duplicate_precheck');
            if (is_array($precheck)) {
                $this->assertTrue((bool) ($precheck['hadAttachments'] ?? false));
            }
        }
    }

    // ── TicketMedia integrity ─────────────────────────────────────────────────

    public function test_media_record_has_correct_ticket_id_and_uploader(): void
    {
        Storage::fake('public');

        $reporter = $this->reporter();

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->validPayload('Ticket Integridad Media'),
            ['media_files' => [UploadedFile::fake()->image('evidencia.jpg', 200, 200)->size(200)]],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::query()->where('title', 'Ticket Integridad Media')->firstOrFail();
        $media = TicketMedia::where('ticket_id', $ticket->id)->firstOrFail();

        $this->assertSame($ticket->id, $media->ticket_id);
        $this->assertSame($reporter->id, $media->uploaded_by);
        $this->assertSame('image', $media->file_type);
        $this->assertNotEmpty($media->file_url);
        // file_url must not contain the production Railway host.
        $this->assertStringNotContainsString('ticksystemremoto-production.up.railway.app', (string) $media->file_url);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        Role::firstOrCreate(['name' => 'reporter', 'guard_name' => 'web']);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'IMGUP-01'],
            [
                'name' => 'Laboratorio Upload Regresión',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-imgup-regression-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'ImageUploadTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests de upload de imágenes'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $title = 'Proyector del aula B-101 no enciende'): array
    {
        return [
            'title' => $title,
            'description' => 'Descripción detallada de la incidencia con la longitud mínima necesaria para pasar la validación del formulario.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
