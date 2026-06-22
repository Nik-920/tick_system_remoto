<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies that config/tickets.php is the single source of truth for upload limits.
 *
 * Each test mutates one config value via Config::set() and asserts that the
 * backend (Form Request validation) or the frontend (rendered data attributes)
 * reflects the change automatically — proving that no hardcoded duplicates exist.
 */
class UploadLimitsSingleSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    // ── Create profile — config drives Form Request validation ────────────────

    #[Test]
    public function create_rejects_more_files_than_config_max_files(): void
    {
        Config::set('tickets.media.create.max_files', 2);

        $reporter = $this->reporter();
        $files = array_fill(0, 3, UploadedFile::fake()->image('foto.jpg')->size(50));

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
    public function create_rejects_file_over_lowered_config_max_file_size(): void
    {
        Config::set('tickets.media.create.max_file_size_kb', 512);

        $reporter = $this->reporter();
        $oversized = UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => [$oversized]],
        ));

        $response->assertSessionHasErrors('media_files.*');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function create_pipeline_rejects_when_total_exceeds_lowered_config(): void
    {
        Config::set('tickets.media.create.max_total_size_mb', 3);

        $reporter = $this->reporter();
        $files = array_fill(0, 4, UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $response->assertSessionHasErrors('media_files');
        $errors = session('errors')?->get('media_files') ?? [];
        $totalSizeError = collect($errors)->first(fn (string $msg): bool => str_contains($msg, '3 MB'));
        $this->assertNotNull($totalSizeError, 'Expected pipeline total-size error containing "3 MB"');
        $this->assertStringNotContainsString('validation.', $totalSizeError);
    }

    // ── Reporter edit profile — config drives Form Request validation ─────────

    #[Test]
    public function reporter_edit_rejects_more_files_than_config_max_files(): void
    {
        Config::set('tickets.media.reporter_edit.max_files', 2);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);
        $files = array_fill(0, 3, UploadedFile::fake()->image('img.jpg')->size(50));

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->reporterUpdatePayload($ticket), ['new_images' => $files]),
        );

        $response->assertSessionHasErrors('new_images');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function reporter_edit_rejects_file_over_lowered_config_max_file_size(): void
    {
        Config::set('tickets.media.reporter_edit.max_file_size_kb', 512);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);
        $oversized = UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->reporterUpdatePayload($ticket), ['new_images' => [$oversized]]),
        );

        $response->assertSessionHasErrors('new_images.*');
    }

    #[Test]
    public function reporter_edit_pipeline_rejects_when_total_exceeds_lowered_config(): void
    {
        Config::set('tickets.media.reporter_edit.max_total_size_mb', 3);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);
        $files = array_fill(0, 4, UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'));

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->reporterUpdatePayload($ticket), ['new_images' => $files]),
        );

        $response->assertSessionHasErrors('new_images');
        $errors = session('errors')?->get('new_images') ?? [];
        $totalSizeError = collect($errors)->first(fn (string $msg): bool => str_contains($msg, '3 MB'));
        $this->assertNotNull($totalSizeError, 'Expected pipeline total-size error containing "3 MB"');
    }

    // ── Maintenance profile — config drives Form Request validation ───────────

    #[Test]
    public function maintenance_rejects_more_files_than_config_max_files(): void
    {
        Config::set('tickets.media.maintenance.max_files', 2);
        Role::firstOrCreate(['name' => 'maintenance', 'guard_name' => 'web']);

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicketFor($this->reporter());
        $files = array_fill(0, 3, UploadedFile::fake()->image('img.jpg')->size(50));

        $response = $this->actingAs($admin)->patch(
            route('tickets.maintenance.update', $ticket->id),
            ['evidence' => $files],
        );

        $response->assertSessionHasErrors('evidence');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    #[Test]
    public function maintenance_rejects_file_over_lowered_config_max_file_size(): void
    {
        Config::set('tickets.media.maintenance.max_file_size_kb', 512);
        Role::firstOrCreate(['name' => 'maintenance', 'guard_name' => 'web']);

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicketFor($this->reporter());
        $oversized = UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($admin)->patch(
            route('tickets.maintenance.update', $ticket->id),
            ['evidence' => [$oversized]],
        );

        $response->assertSessionHasErrors('evidence.*');
    }

    // ── Views reflect config (data attributes are dynamic) ───────────────────

    #[Test]
    public function create_view_data_attributes_match_current_config(): void
    {
        $reporter = $this->reporter();
        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();

        $fileSizeBytes = config('tickets.media.create.max_file_size_kb') * 1024;
        $totalSizeBytes = config('tickets.media.create.max_total_size_mb') * 1024 * 1024;
        $maxFiles = config('tickets.media.create.max_files');

        $response->assertSee("data-max-file-size=\"{$fileSizeBytes}\"", false);
        $response->assertSee("data-max-total-size=\"{$totalSizeBytes}\"", false);
        $response->assertSee("data-max-files=\"{$maxFiles}\"", false);
    }

    #[Test]
    public function create_view_data_attributes_reflect_changed_config(): void
    {
        Config::set('tickets.media.create.max_file_size_kb', 2048);
        Config::set('tickets.media.create.max_file_size_mb', 2);
        Config::set('tickets.media.create.max_total_size_mb', 10);
        Config::set('tickets.media.create.max_files', 3);

        $reporter = $this->reporter();
        $response = $this->actingAs($reporter)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('data-max-file-size="2097152"', false);   // 2048 * 1024
        $response->assertSee('data-max-total-size="10485760"', false);  // 10 * 1024 * 1024
        $response->assertSee('data-max-files="3"', false);
    }

    #[Test]
    public function reporter_edit_view_data_attributes_match_current_config(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->get(route('reporter.tickets.edit', $ticket->id));

        $response->assertOk();

        $fileSizeBytes = config('tickets.media.reporter_edit.max_file_size_kb') * 1024;
        $totalSizeBytes = config('tickets.media.reporter_edit.max_total_size_mb') * 1024 * 1024;
        $maxFiles = config('tickets.media.reporter_edit.max_files');

        $response->assertSee("data-max-file-size=\"{$fileSizeBytes}\"", false);
        $response->assertSee("data-max-total-size=\"{$totalSizeBytes}\"", false);
        $response->assertSee("data-max-files=\"{$maxFiles}\"", false);
    }

    #[Test]
    public function reporter_edit_view_data_attributes_reflect_changed_config(): void
    {
        Config::set('tickets.media.reporter_edit.max_file_size_kb', 1024);
        Config::set('tickets.media.reporter_edit.max_file_size_mb', 1);
        Config::set('tickets.media.reporter_edit.max_total_size_mb', 5);
        Config::set('tickets.media.reporter_edit.max_files', 2);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->get(route('reporter.tickets.edit', $ticket->id));

        $response->assertOk();
        $response->assertSee('data-max-file-size="1048576"', false);   // 1024 * 1024
        $response->assertSee('data-max-total-size="5242880"', false);   // 5 * 1024 * 1024
        $response->assertSee('data-max-files="2"', false);
    }

    // ── No validation.* leaks in any profile ─────────────────────────────────

    #[Test]
    public function create_upload_errors_never_contain_validation_dot_prefix(): void
    {
        Config::set('tickets.media.create.max_files', 1);

        $reporter = $this->reporter();
        $files = array_fill(0, 2, UploadedFile::fake()->image('img.jpg')->size(50));

        $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $allErrors = session('errors')?->all() ?? [];
        foreach ($allErrors as $msg) {
            $this->assertStringNotContainsString('validation.', $msg);
        }
    }

    #[Test]
    public function reporter_edit_upload_errors_never_contain_validation_dot_prefix(): void
    {
        Config::set('tickets.media.reporter_edit.max_files', 1);

        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);
        $files = array_fill(0, 2, UploadedFile::fake()->image('img.jpg')->size(50));

        $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge($this->reporterUpdatePayload($ticket), ['new_images' => $files]),
        );

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

    private function userWithRole(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket SSOT '.Str::uuid(),
            'description' => 'Descripción con longitud suficiente para la validación del formulario de incidencias.',
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
            ['room_code' => 'SSOT-01'],
            [
                'name' => 'Lab SSOT',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-ssot-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'SSOTTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests SSOT'],
        );
    }

    /** @return array<string, mixed> */
    private function createPayload(): array
    {
        return [
            'title' => 'Ticket de prueba SSOT config',
            'description' => 'Descripción detallada para pasar la validación del formulario de incidencias SSOT.',
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
            'title' => 'Título actualizado SSOT',
            'description' => 'Descripción actualizada con longitud suficiente para pasar la validación requerida por el formulario.',
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'priority' => 'medium',
        ];
    }
}
