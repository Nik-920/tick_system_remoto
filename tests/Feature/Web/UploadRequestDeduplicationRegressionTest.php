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
 * Regression suite for the ValidatesTicketUploads trait refactor.
 *
 * Ensures that extracting common upload logic to the trait did not break
 * any upload path across the three Form Requests: create, reporter_edit,
 * and maintenance. Also verifies that config/tickets.php remains the single
 * source of truth after the refactor.
 */
class UploadRequestDeduplicationRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── Create — happy paths ──────────────────────────────────────────────────

    #[Test]
    public function create_accepts_valid_jpg_under_limit(): void
    {
        $reporter = $this->reporter();

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => [UploadedFile::fake()->image('foto.jpg')->size(100)]],
        ));

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function create_rejects_more_than_five_files_using_config(): void
    {
        Config::set('tickets.media.create.max_files', 5);

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

    // ── Reporter edit — happy paths ───────────────────────────────────────────

    #[Test]
    public function reporter_edit_accepts_valid_image_under_5mb(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->openTicketFor($reporter);

        $response = $this->actingAs($reporter)->patch(
            route('reporter.tickets.update', $ticket->id),
            array_merge(
                $this->reporterUpdatePayload($ticket),
                ['new_images' => [UploadedFile::fake()->image('img.jpg')->size(100)]],
            ),
        );

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function reporter_edit_rejects_file_over_5mb_with_human_message(): void
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

    // ── Maintenance — happy paths ─────────────────────────────────────────────

    #[Test]
    public function maintenance_accepts_valid_evidence_under_5mb(): void
    {
        Role::firstOrCreate(['name' => 'maintenance', 'guard_name' => 'web']);
        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicketFor($this->reporter());

        $response = $this->actingAs($admin)->patch(
            route('tickets.maintenance.update', $ticket->id),
            ['evidence' => [UploadedFile::fake()->image('evidencia.jpg')->size(100)]],
        );

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function maintenance_rejects_invalid_file_with_human_message(): void
    {
        Role::firstOrCreate(['name' => 'maintenance', 'guard_name' => 'web']);
        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicketFor($this->reporter());
        $oversized = UploadedFile::fake()->create('grande.jpg', 6 * 1024, 'image/jpeg');

        $response = $this->actingAs($admin)->patch(
            route('tickets.maintenance.update', $ticket->id),
            ['evidence' => [$oversized]],
        );

        $response->assertSessionHasErrors('evidence.*');
        $allErrors = session('errors')?->all() ?? [];
        $this->assertNotEmpty($allErrors);
        $this->assertStringNotContainsString('validation.', $allErrors[0]);
    }

    // ── No validation.* leaks ─────────────────────────────────────────────────

    #[Test]
    public function no_response_contains_raw_validation_uploaded_key(): void
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

    // ── Config drives all three profiles post-refactor ───────────────────────

    #[Test]
    public function config_max_files_affects_store_request_after_refactor(): void
    {
        Config::set('tickets.media.create.max_files', 2);
        $reporter = $this->reporter();
        $files = array_fill(0, 3, UploadedFile::fake()->image('foto.jpg')->size(50));

        $response = $this->actingAs($reporter)->post(route('tickets.store'), array_merge(
            $this->createPayload(),
            ['media_files' => $files],
        ));

        $response->assertSessionHasErrors('media_files');
    }

    #[Test]
    public function config_max_file_size_affects_reporter_edit_after_refactor(): void
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
    public function config_max_file_size_affects_maintenance_after_refactor(): void
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
            'title' => 'Ticket dedup '.Str::uuid(),
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
            ['room_code' => 'DEDUP-01'],
            [
                'name' => 'Lab Dedup Test',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-dedup-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'DedupTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests de deduplicación'],
        );
    }

    /** @return array<string, mixed> */
    private function createPayload(): array
    {
        return [
            'title' => 'Ticket dedup trait test',
            'description' => 'Descripción detallada con longitud suficiente para pasar la validación del formulario de incidencias.',
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
            'title' => 'Título actualizado dedup test',
            'description' => 'Descripción actualizada con longitud suficiente para pasar la validación requerida por el formulario.',
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'priority' => 'medium',
        ];
    }
}
