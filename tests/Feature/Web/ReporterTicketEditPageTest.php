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
 * Real "Editar ticket" for the reporter — phase 3 (+ image evidence uploads).
 *
 * A reporter may edit ONLY their own request and ONLY while it is still open,
 * unassigned and unlocked (TicketPolicy@update). Once maintenance takes it
 * (assigned), it is locked, or it leaves `open`, editing is denied. The form
 * accepts ONLY safe fields (title, description, location_id, category_id,
 * priority); state and assignment columns can never be written. New images
 * (new_images[]) are additive — existing evidence is never deleted.
 */
class ReporterTicketEditPageTest extends TestCase
{
    use RefreshDatabase;

    // ── edit form (GET) access ───────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $ticket = $this->ticketFor($this->userWithRole('reporter'), 'open', 'X');

        $this->get(route('reporter.tickets.edit', $ticket->id))->assertRedirect(route('login'));
    }

    public function test_reporter_can_view_edit_form_for_own_open_unassigned_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Mi ticket editable');

        $response = $this->actingAs($me)->get(route('reporter.tickets.edit', $ticket->id));

        $response->assertOk();
        $response->assertViewIs('tickets.reporter.edit');
        $response->assertSeeText('Editar ticket');
        $response->assertSeeText('Actualiza la información de tu incidencia antes de que mantenimiento la tome.');
        // Form posts to the update route with method spoofing (PATCH), never DELETE.
        $response->assertSee(route('reporter.tickets.update', $ticket->id), false);
        $response->assertSee('value="PATCH"', false);
        $response->assertDontSee('value="DELETE"', false);
        $response->assertDontSeeText('Eliminar');
        // Form must be multipart so file uploads work.
        $response->assertSee('multipart/form-data', false);
    }

    public function test_reporter_gets_404_for_another_reporters_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $foreign = $this->ticketFor($other, 'open', 'Ajeno');

        $this->actingAs($me)->get(route('reporter.tickets.edit', $foreign->id))->assertNotFound();
        $this->actingAs($me)->patch(route('reporter.tickets.update', $foreign->id), $this->validPayload())->assertNotFound();
    }

    public function test_reporter_gets_404_for_unknown_or_malformed_id(): void
    {
        $me = $this->userWithRole('reporter');

        $this->actingAs($me)->get(route('reporter.tickets.edit', Str::uuid()->toString()))->assertNotFound();
        $this->actingAs($me)->get(route('reporter.tickets.edit', 'not-a-uuid'))->assertNotFound();
    }

    public function test_reporter_cannot_edit_non_open_tickets(): void
    {
        $me = $this->userWithRole('reporter');

        foreach (['in_progress', 'resolved', 'rejected', 'cancelled'] as $state) {
            $ticket = $this->ticketFor($me, $state, "Estado {$state}");

            $this->actingAs($me)->get(route('reporter.tickets.edit', $ticket->id))->assertForbidden();
            $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), $this->validPayload())->assertForbidden();
        }
    }

    public function test_reporter_cannot_edit_own_open_ticket_assigned_to_maintenance(): void
    {
        $me = $this->userWithRole('reporter');
        $tech = $this->userWithRole('maintenance');
        $ticket = $this->ticketFor($me, 'open', 'Tomado', ['assigned_to' => $tech->id]);

        $this->actingAs($me)->get(route('reporter.tickets.edit', $ticket->id))->assertForbidden();
        $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), $this->validPayload())->assertForbidden();
    }

    public function test_reporter_cannot_edit_own_open_locked_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Bloqueado', ['assignment_locked' => true]);

        $this->actingAs($me)->get(route('reporter.tickets.edit', $ticket->id))->assertForbidden();
        $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), $this->validPayload())->assertForbidden();
    }

    public function test_maintenance_admin_super_admin_cannot_access_reporter_edit(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($reporter, 'open', 'Solo reporter');

        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('reporter.tickets.edit', $ticket->id))->assertForbidden();
            $this->actingAs($user)->patch(route('reporter.tickets.update', $ticket->id), $this->validPayload())->assertForbidden();
        }
    }

    // ── update (PATCH) behaviour ─────────────────────────────────

    public function test_reporter_can_update_safe_fields_and_is_redirected_with_success(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Título viejo');
        $newLocation = $this->location('LAB-9', 'Laboratorio 9');
        $newCategory = $this->category('Redes');

        $response = $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), [
            'title' => 'Título corregido por el reporter',
            'description' => 'Descripción nueva con suficiente detalle para pasar la validación.',
            'location_id' => $newLocation->id,
            'category_id' => $newCategory->id,
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('reporter.tickets.show', $ticket->id));
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame('Título corregido por el reporter', $ticket->title);
        $this->assertSame('Descripción nueva con suficiente detalle para pasar la validación.', $ticket->description);
        $this->assertSame($newLocation->id, $ticket->location_id);
        $this->assertSame($newCategory->id, $ticket->category_id);
        $this->assertSame('high', $ticket->priority);
        // Unchanged operational fields.
        $this->assertSame('open', $ticket->state);
        $this->assertNull($ticket->assigned_to);
    }

    public function test_update_ignores_forbidden_fields(): void
    {
        $me = $this->userWithRole('reporter');
        $tech = $this->userWithRole('maintenance');
        $ticket = $this->ticketFor($me, 'open', 'Intento de escalada');
        $originalReporter = $ticket->reporter_id;

        $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), [
            'title' => 'Título legítimo del reporter',
            'description' => 'Una descripción válida y suficientemente larga para validar.',
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'priority' => 'medium',
            // Forbidden — must be ignored entirely.
            'state' => 'resolved',
            'assigned_to' => $tech->id,
            'reporter_id' => $tech->id,
            'assignment_locked' => true,
            'assignment_source' => 'admin_assigned',
            'resolved_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('reporter.tickets.show', $ticket->id));

        $ticket->refresh();
        $this->assertSame('open', $ticket->state);
        $this->assertNull($ticket->assigned_to);
        $this->assertSame($originalReporter, $ticket->reporter_id);
        $this->assertFalse($ticket->assignment_locked);
        $this->assertNull($ticket->assignment_source);
        $this->assertNull($ticket->resolved_at);
        // The legitimate edit still applied.
        $this->assertSame('Título legítimo del reporter', $ticket->title);
    }

    public function test_update_validates_required_fields(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Para validación');

        $this->actingAs($me)
            ->from(route('reporter.tickets.edit', $ticket->id))
            ->patch(route('reporter.tickets.update', $ticket->id), [
                'title' => 'no', // too short
                'description' => 'muy corta',
                'location_id' => '',
                'category_id' => '',
                'priority' => 'invalido',
            ])
            ->assertRedirect(route('reporter.tickets.edit', $ticket->id))
            ->assertSessionHasErrors(['title', 'description', 'location_id', 'category_id', 'priority']);

        // Nothing changed on a failed validation.
        $this->assertSame('Para validación', $ticket->refresh()->title);
    }

    // ── image / evidence upload ──────────────────────────────────

    public function test_update_with_valid_images_creates_ticket_media_records(): void
    {
        Storage::fake('public');

        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Con imagen');

        $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), array_merge(
            $this->validPayload(),
            ['new_images' => [UploadedFile::fake()->image('foto.jpg', 400, 300)->size(500)]],
        ))->assertRedirect(route('reporter.tickets.show', $ticket->id));

        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $me->id,
        ]);

        $this->assertCount(1, TicketMedia::where('ticket_id', $ticket->id)->get());
    }

    public function test_update_with_images_does_not_delete_existing_media(): void
    {
        Storage::fake('public');

        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Media existente');

        // Seed an existing TicketMedia record.
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.com/existing.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $me->id,
        ]);

        $this->actingAs($me)->patch(route('reporter.tickets.update', $ticket->id), array_merge(
            $this->validPayload(),
            ['new_images' => [UploadedFile::fake()->image('new.jpg', 200, 200)->size(200)]],
        ))->assertRedirect();

        // Both records must exist: the original + the new one.
        $this->assertCount(2, TicketMedia::where('ticket_id', $ticket->id)->get());
        $this->assertDatabaseHas('ticket_media', ['file_url' => 'https://example.com/existing.jpg']);
    }

    public function test_update_text_only_does_not_affect_existing_media(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Solo texto');

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.com/kept.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $me->id,
        ]);

        $this->actingAs($me)
            ->patch(route('reporter.tickets.update', $ticket->id), $this->validPayload())
            ->assertRedirect();

        // Media record must remain untouched.
        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.com/kept.jpg',
        ]);
    }

    public function test_update_rejects_invalid_file_type(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Archivo inválido');

        $this->actingAs($me)
            ->from(route('reporter.tickets.edit', $ticket->id))
            ->patch(route('reporter.tickets.update', $ticket->id), array_merge(
                $this->validPayload(),
                ['new_images' => [UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream')]],
            ))
            ->assertRedirect(route('reporter.tickets.edit', $ticket->id))
            ->assertSessionHasErrors(['new_images.0']);

        $this->assertDatabaseMissing('ticket_media', ['ticket_id' => $ticket->id]);
    }

    public function test_classic_admin_ticket_routes_remain_intact(): void
    {
        // Reporter edit is reporter-only; the classic /tickets surface keeps
        // working for an admin (regression guard for the role split).
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    }

    // ── Fixtures ─────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'title' => 'Un título válido para el ticket',
            'description' => 'Una descripción válida con la longitud mínima requerida por las reglas.',
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'priority' => 'medium',
        ];
    }

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ticketFor(User $reporter, string $state, string $title, array $attrs = []): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title.' con largo suficiente.',
            'reporter_id' => $reporter->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => $state,
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => $attrs['assignment_locked'] ?? false,
        ]);

        if (! empty($attrs['assigned_to'])) {
            $ticket->forceFill(['assigned_to' => $attrs['assigned_to']])->save();
        }

        return $ticket;
    }

    private function location(string $code = 'LAB-1', string $name = 'Laboratorio 1'): Location
    {
        return Location::firstOrCreate(
            ['room_code' => $code],
            [
                'name' => $name,
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(string $name = 'Hardware'): Category
    {
        return Category::firstOrCreate(
            ['name' => $name],
            ['icon' => 'monitor', 'description' => 'Categoría '.$name],
        );
    }
}
