<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Storage\TicketMediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integration tests for UploadValidationPipeline wired into Form Requests.
 *
 * Verifies that the functional pipeline complements (not replaces) Laravel
 * validation: valid flows pass, errors are human-readable, no internal paths
 * leak, and the total-size check (unique to the pipeline) fires correctly.
 */
class FunctionalUploadValidationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Create ticket — valid paths ───────────────────────────────────────────

    #[Test]
    public function create_ticket_with_valid_jpg_under_10mb_passes_pipeline(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, fn ($m) => $m->shouldReceive('storeManyForTicket')->andReturnNull());

        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => [UploadedFile::fake()->image('foto.jpg', 100, 100)->size(500)]],
        ));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function create_ticket_with_five_valid_files_passes_pipeline(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, fn ($m) => $m->shouldReceive('storeManyForTicket')->andReturnNull());

        $reporter = $this->reporter();
        $files = array_map(
            fn (int $i): UploadedFile => UploadedFile::fake()->image("foto{$i}.jpg")->size(200),
            range(1, 5),
        );

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function create_ticket_without_files_passes_pipeline_with_no_errors(): void
    {
        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), $this->createPayload());

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    // ── Create ticket — rejection cases ──────────────────────────────────────

    #[Test]
    public function create_ticket_with_six_files_fails_with_human_message(): void
    {
        $reporter = $this->reporter();
        $files = array_fill(0, 6, UploadedFile::fake()->image('foto.jpg')->size(50));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $response->assertSessionHasErrors('media_files');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function create_ticket_total_size_error_fires_from_pipeline_and_is_human(): void
    {
        // Temporarily lower total limit so 4 small files (each under per-file
        // limit) collectively exceed the total. This isolates the pipeline's
        // array_reduce total-size check from per-file checks.
        Config::set('tickets.media.create.max_total_size_mb', 3);

        $reporter = $this->reporter();
        // 4 files × 1 MB each = 4 MB total > 3 MB total limit, each < 10 MB per-file.
        $files = array_fill(0, 4, UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $response->assertSessionHasErrors('media_files');

        $errors = session('errors')?->get('media_files') ?? [];
        $totalSizeError = collect($errors)->first(
            fn (string $msg): bool => str_contains($msg, '3 MB'),
        );

        $this->assertNotNull($totalSizeError, 'Expected pipeline total-size error containing "3 MB"');
        $this->assertStringNotContainsString('validation.', $totalSizeError);
    }

    // ── Reporter edit — valid paths ───────────────────────────────────────────

    #[Test]
    public function reporter_edit_with_valid_jpg_under_5mb_passes_pipeline(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, fn ($m) => $m->shouldReceive()->andReturn(null));

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge(
                $this->reporterUpdatePayload($ticket),
                ['new_images' => [UploadedFile::fake()->image('foto.jpg')->size(500)]],
            ),
        );

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function reporter_edit_without_files_passes_pipeline_with_no_errors(): void
    {
        Storage::fake('public');
        $this->mock(TicketMediaStorageService::class, fn ($m) => $m->shouldReceive()->andReturn(null));

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            $this->reporterUpdatePayload($ticket),
        );

        $response->assertSessionHasNoErrors();
    }

    // ── Reporter edit — rejection cases ──────────────────────────────────────

    #[Test]
    public function reporter_edit_with_file_over_5mb_fails_with_human_message(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $oversized = UploadedFile::fake()->create('grande.jpg', 6 * 1024, 'image/jpeg');

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge(
                $this->reporterUpdatePayload($ticket),
                ['new_images' => [$oversized]],
            ),
        );

        $response->assertSessionHasErrors('new_images.*');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function reporter_edit_total_size_error_fires_from_pipeline(): void
    {
        // Lower the total limit so files individually pass per-file check but
        // collectively exceed the total — isolates pipeline's array_reduce path.
        Config::set('tickets.media.reporter_edit.max_total_size_mb', 3);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        // 4 files × 1 MB each = 4 MB > 3 MB total, each < 5 MB per-file.
        $files = array_fill(0, 4, UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'));

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge(
                $this->reporterUpdatePayload($ticket),
                ['new_images' => $files],
            ),
        );

        $response->assertSessionHasErrors('new_images');

        $errors = session('errors')?->get('new_images') ?? [];
        $totalSizeError = collect($errors)->first(
            fn (string $msg): bool => str_contains($msg, '3 MB'),
        );

        $this->assertNotNull($totalSizeError, 'Expected pipeline total-size error containing "3 MB"');
        $this->assertStringNotContainsString('validation.', $totalSizeError);
    }

    // ── No leaked internal details ────────────────────────────────────────────

    #[Test]
    public function pipeline_error_responses_do_not_expose_internal_paths(): void
    {
        $reporter = $this->reporter();
        $invalid = UploadedFile::fake()->create('bad.exe', 50, 'application/octet-stream');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => [$invalid]],
        ));

        $body = $response->content();
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('upload_tmp_dir', $body);
        $this->assertStringNotContainsString('storage/app', $body);
        $this->assertStringNotContainsString('Supabase', $body);
        $this->assertStringNotContainsString('bucket', $body);
    }

    #[Test]
    public function pipeline_errors_never_contain_raw_validation_dot_keys(): void
    {
        $reporter = $this->reporter();
        $invalid = UploadedFile::fake()->create('bad.exe', 50, 'application/octet-stream');

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => [$invalid]],
        ));

        $allErrors = session('errors')?->all() ?? [];
        foreach ($allErrors as $msg) {
            $this->assertStringNotContainsString('validation.', $msg);
        }
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

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket funcional de prueba '.Str::uuid(),
            'description' => 'Descripción con longitud suficiente para la validación del formulario.',
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
            ['room_code' => 'FUNC-PIPE-01'],
            [
                'name' => 'Laboratorio Pipeline Funcional',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-func-pipeline-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'FunctionalPipelineTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests del pipeline funcional'],
        );
    }

    /** @return array<string, mixed> */
    private function createPayload(string $title = 'Ticket de prueba del pipeline funcional'): array
    {
        return [
            'title' => $title,
            'description' => 'Descripción detallada con longitud mínima necesaria para pasar la validación del formulario de incidencias.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    /** @return array<string, mixed> */
    private function reporterUpdatePayload(Ticket $ticket): array
    {
        return [
            'title' => 'Título actualizado del ticket de prueba funcional',
            'description' => 'Descripción actualizada con longitud suficiente para pasar la validación requerida.',
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'priority' => 'medium',
        ];
    }
}
