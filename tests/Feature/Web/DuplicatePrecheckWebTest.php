<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuplicatePrecheckWebTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Warning intercepts first submit ────────────────────────────────

    public function test_warning_shown_and_ticket_not_created_when_similar_ticket_exists(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'El proyector del lab no emite señal desde el lunes.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio A-201 no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('duplicate_precheck');
        $this->assertDatabaseCount('tickets', 1);
    }

    // ── 2. Ticket created on second submit with duplicate_ack ─────────────

    public function test_ticket_created_when_duplicate_ack_is_present(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'El proyector del lab no emite señal.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio A-201 no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'duplicate_ack' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── 3. No warning when location differs ───────────────────────────────

    public function test_no_warning_when_existing_ticket_has_different_location(): void
    {
        $reporter = $this->makeReporter();
        $location1 = $this->makeLocation('A-201');
        $location2 = $this->makeLocation('B-101');
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $reporter->id,
            'location_id' => $location1->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del lab no funciona desde hoy.',
            'location_id' => $location2->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── 4. No warning when category differs ──────────────────────────────

    public function test_no_warning_when_existing_ticket_has_different_category(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category1 = $this->makeCategory('Electricidad');
        $category2 = $this->makeCategory('Plomería');

        Ticket::create([
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category1->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del lab no funciona desde hoy.',
            'location_id' => $location->id,
            'category_id' => $category2->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── 5. No warning when title is completely different ──────────────────

    public function test_no_warning_when_title_is_dissimilar(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Filtración agua techo baño',
            'description' => 'Hay una gotera en el baño del segundo piso que moja el suelo.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── 6. No-leak: precheck session only exposes safe fields ─────────────

    public function test_precheck_session_does_not_expose_sensitive_data(): void
    {
        $reporter = $this->makeReporter();
        $otherReporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'Descripcion confidencial del otro reportero que no debe verse.',
            'reporter_id' => $otherReporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector del laboratorio A-201 no enciende luz',
            'description' => 'Prueba no-leak del sistema de precheck.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $precheck = session('duplicate_precheck');
        $this->assertIsArray($precheck);
        $this->assertArrayHasKey('matchedTitle', $precheck);
        $this->assertArrayHasKey('matchedState', $precheck);
        $this->assertArrayHasKey('reason', $precheck);
        $this->assertArrayNotHasKey('reporter', $precheck);
        $this->assertArrayNotHasKey('reporter_id', $precheck);
        $this->assertArrayNotHasKey('description', $precheck);
        $this->assertArrayNotHasKey('id', $precheck);
    }

    // ── 7. Idempotency: normal flow still works when no match ─────────────

    public function test_idempotency_not_broken_when_no_precheck_match(): void
    {
        config(['idempotency.allow_missing' => false]);

        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $idempotencyKey = (string) Str::uuid();

        $first = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Cable cargador roto sala cómputo',
            'description' => 'El cable del cargador en la sala de cómputo está pelado y es peligroso.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'idempotency_key' => $idempotencyKey,
        ]);

        $first->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);

        $second = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Cable cargador roto sala cómputo',
            'description' => 'El cable del cargador en la sala de cómputo está pelado y es peligroso.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'idempotency_key' => $idempotencyKey,
        ]);

        $second->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    // ── 8. Cancelled/rejected tickets do not trigger warning ─────────────

    public function test_no_warning_when_only_cancelled_tickets_match(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio no enciende',
            'description' => 'El proyector del lab no funciona.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_CANCELLED,
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── 9. hadAttachments=true when files submitted with first trigger ────

    public function test_precheck_payload_has_had_attachments_true_when_files_submitted(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'El proyector del lab no emite señal desde el lunes.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio A-201 no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'media_files' => [UploadedFile::fake()->image('foto.jpg')],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('duplicate_precheck');
        $this->assertDatabaseCount('tickets', 1);

        $precheck = session('duplicate_precheck');
        $this->assertIsArray($precheck);
        $this->assertTrue($precheck['hadAttachments']);

        $followUp = $this->actingAs($reporter)->get(route('tickets.create'));
        $followUp->assertSee('vuelve a adjuntarlas');
    }

    // ── 10. hadAttachments=false when no files submitted ─────────────────

    public function test_precheck_payload_has_had_attachments_false_when_no_files_submitted(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'El proyector del lab no emite señal.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio A-201 no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('duplicate_precheck');

        $precheck = session('duplicate_precheck');
        $this->assertIsArray($precheck);
        $this->assertFalse($precheck['hadAttachments']);

        $followUp = $this->actingAs($reporter)->get(route('tickets.create'));
        $followUp->assertDontSee('vuelve a adjuntarlas');
    }

    // ── 11. Second submit with duplicate_ack and files creates ticket ─────

    public function test_ticket_created_on_second_submit_with_files_and_duplicate_ack(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Proyector laboratorio A-201 no enciende',
            'description' => 'El proyector del lab no emite señal.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)->post(route('tickets.store'), [
            'title' => 'Proyector laboratorio A-201 no enciende luz',
            'description' => 'El proyector del laboratorio no prende desde esta mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'idempotency_key' => (string) Str::uuid(),
            'duplicate_ack' => '1',
            'media_files' => [UploadedFile::fake()->image('evidencia.jpg')],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_precheck');
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('tickets', 2);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeLocation(string $roomCode = ''): Location
    {
        return Location::create([
            'name' => 'Aula Precheck',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => $roomCode !== '' ? $roomCode : 'PC-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoría de prueba precheck',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
