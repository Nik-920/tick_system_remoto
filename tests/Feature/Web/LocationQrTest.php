<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocationQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Web routes enforce CSRF via ValidateCsrfToken. In the test environment
        // this is normally bypassed by Application::runningUnitTests() returning true
        // when APP_ENV=testing. Disabling it explicitly makes the tests robust
        // when the Docker container env var APP_ENV=local takes precedence over
        // phpunit.xml. Auth and role middleware remain active.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_store_location_dispatches_qr_job_to_media_queue(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Aula QR Test',
            'building' => 'Edificio QR',
            'floor' => '1',
            'room_code' => 'QR-101',
            'is_active' => '1',
        ]);

        $response->assertRedirect();

        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job): bool {
            $location = Location::query()->where('room_code', 'QR-101')->first();

            return $location !== null
                && $job->locationId === $location->id
                && $job->jobTrackingId !== null
                && $job->jobTrackingId !== '';
        });
    }

    public function test_store_location_marks_qr_status_pending_before_dispatch(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Aula Status Test',
            'building' => 'Edificio S',
            'floor' => '2',
            'room_code' => 'QR-201',
            'is_active' => '1',
        ]);

        $location = Location::query()->where('room_code', 'QR-201')->firstOrFail();

        $this->assertSame('pending', $location->qr_generation_status);
        $this->assertNotNull($location->qr_job_id);
        $this->assertNull($location->qr_image_url);
        $this->assertNull($location->qr_generated_at);
    }

    public function test_regenerate_qr_dispatches_job_to_media_queue(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');
        $location = $this->locationWithStatus('ready', 'https://example.test/old-qr.png');

        $response = $this->actingAs($admin)
            ->post(route('locations.regenerate-qr', $location));

        $response->assertRedirect(route('locations.edit', $location));

        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($location): bool {
            return $job->locationId === $location->id
                && $job->jobTrackingId !== null
                && $job->jobTrackingId !== '';
        });
    }

    public function test_regenerate_qr_marks_status_pending_before_dispatch(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');
        $location = $this->locationWithStatus('ready', 'https://example.test/old-qr.png');

        $this->actingAs($admin)
            ->post(route('locations.regenerate-qr', $location));

        $location->refresh();

        $this->assertSame('pending', $location->qr_generation_status);
        $this->assertNotNull($location->qr_job_id);
        $this->assertNull($location->qr_last_error);
    }

    public function test_reporter_cannot_regenerate_qr(): void
    {
        Queue::fake();

        $reporter = $this->userWithRole('reporter');
        $location = $this->locationWithStatus('ready');

        $this->actingAs($reporter)
            ->post(route('locations.regenerate-qr', $location))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_store_same_area_different_room_code_creates_without_confirmation_or_warning(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        Location::create([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-001',
            'qr_token' => 'token-aul-001',
            'qr_generation_status' => 'pending',
            'is_active' => true,
        ]);

        // AUL-002 is a different physical space in the same area.
        // A different room_code must let it pass without any similarity warning.
        $response = $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-002',
            'is_active' => '1',
        ]);

        $created = Location::query()->where('room_code', 'AUL-002')->firstOrFail();
        $response->assertRedirect(route('locations.edit', $created));
        $response->assertSessionMissing('similar_locations_warning');
        $response->assertSessionMissing('confirmation_required');

        $this->assertDatabaseHas('locations', ['room_code' => 'AUL-002']);
        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
    }

    public function test_store_aul_003_with_existing_aul_001_and_aul_002_creates_and_dispatches_qr(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        foreach (['AUL-001' => 'token-aul-001', 'AUL-002' => 'token-aul-002'] as $code => $token) {
            Location::create([
                'name' => 'Pabellon de aulas',
                'building' => '1',
                'floor' => '1',
                'room_code' => $code,
                'qr_token' => $token,
                'qr_generation_status' => 'ready',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-003',
            'is_active' => '1',
        ]);

        $created = Location::query()->where('room_code', 'AUL-003')->firstOrFail();
        $response->assertRedirect(route('locations.edit', $created));
        $response->assertSessionMissing('similar_locations_warning');

        $this->assertDatabaseHas('locations', ['room_code' => 'AUL-003']);
        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($created): bool {
            return $job->locationId === $created->id;
        });
    }

    public function test_store_similar_location_with_confirmation_saves_and_dispatches_qr_job(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        Location::create([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-001',
            'qr_token' => 'token-aul-001',
            'qr_generation_status' => 'pending',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-002',
            'is_active' => '1',
            'confirm_similar_location' => '1',
        ]);

        $created = Location::query()->where('room_code', 'AUL-002')->firstOrFail();
        $response->assertRedirect(route('locations.edit', $created));

        $this->assertDatabaseHas('locations', ['room_code' => 'AUL-002']);

        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($created): bool {
            return $job->locationId === $created->id
                && $job->jobTrackingId !== null
                && $job->jobTrackingId !== '';
        });
    }

    public function test_store_duplicate_room_code_is_rejected_even_with_confirmation(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');

        Location::create([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-001',
            'qr_token' => 'token-aul-001',
            'qr_generation_status' => 'pending',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Diferente nombre',
            'building' => '2',
            'floor' => '2',
            'room_code' => 'AUL-001',
            'is_active' => '1',
            'confirm_similar_location' => '1',
        ]);

        $response->assertSessionHasErrors(['room_code']);
        $this->assertDatabaseCount('locations', 1);
        Queue::assertNothingPushed();
    }

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function locationWithStatus(string $status, ?string $imageUrl = null): Location
    {
        return Location::create([
            'name' => 'Aula Regenerate',
            'building' => 'Edificio R',
            'floor' => '1',
            'room_code' => 'RQ-'.Str::upper(Str::random(4)),
            'qr_token' => 'token-'.Str::uuid(),
            'qr_generation_status' => $status,
            'qr_image_url' => $imageUrl,
            'is_active' => true,
        ]);
    }
}
