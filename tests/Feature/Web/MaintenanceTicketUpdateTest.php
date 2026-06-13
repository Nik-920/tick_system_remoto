<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Edición limitada operativa de Maintenance desde tickets.show
 * (PATCH tickets.maintenance.update) + render de fechas en hora de Perú.
 */
class MaintenanceTicketUpdateTest extends TestCase
{
    use RefreshDatabase;

    // ── Edición limitada: category_id ────────────────────────────────────────

    public function test_assigned_maintenance_can_update_category_from_maintenance_route(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $oldCategory = $this->createCategory(['name' => 'Categoría original']);
        $newCategory = $this->createCategory(['name' => 'Categoría corregida']);

        $ticket = $this->createTicket($reporter, $oldCategory);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $newCategory->id,
                'comment' => 'El reporter eligió mal la categoría.',
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_category_correction_records_administrative_state_history(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $oldCategory = $this->createCategory(['name' => 'Hardware']);
        $newCategory = $this->createCategory(['name' => 'Redes']);

        $ticket = $this->createTicket($reporter, $oldCategory);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $newCategory->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        // Entrada administrativa: mismo estado en ambos lados, sin transición.
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'open',
            'changed_by' => $maintenance->id,
        ]);

        $entry = StateHistory::query()
            ->where('ticket_id', $ticket->id)
            ->latest('created_at')
            ->first();

        $this->assertStringContainsString('Hardware', (string) $entry->comment);
        $this->assertStringContainsString('Redes', (string) $entry->comment);
    }

    public function test_no_state_history_is_recorded_when_nothing_changes(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $category->id, // misma categoría: sin cambios
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseCount('state_history', 0);
    }

    // ── Autorización ─────────────────────────────────────────────────────────

    public function test_reporter_cannot_use_maintenance_update_route(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();
        $otherCategory = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);

        $this->actingAs($reporter)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $otherCategory->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_maintenance_cannot_update_ticket_assigned_to_someone_else(): void
    {
        $maintenanceA = $this->createUserWithRole('maintenance');
        $maintenanceB = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();
        $otherCategory = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenanceB->id])->save();

        $this->actingAs($maintenanceA)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $otherCategory->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();
    }

    public function test_maintenance_cannot_update_resolved_ticket(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();
        $otherCategory = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category, 'resolved');
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $otherCategory->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_category_from_maintenance_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();
        $newCategory = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);

        $this->actingAs($admin)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $newCategory->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category_id' => $newCategory->id,
        ]);
    }

    // ── Campos prohibidos ────────────────────────────────────────────────────

    public function test_maintenance_cannot_edit_title_or_description_even_if_request_is_crafted(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();
        $newCategory = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $originalTitle = $ticket->title;
        $originalDescription = $ticket->description;
        $originalReporterId = $ticket->reporter_id;

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'category_id' => $newCategory->id,
                'title' => 'Título hackeado por maintenance',
                'description' => 'Descripción hackeada que no debe persistirse jamás.',
                'reporter_id' => $maintenance->id,
                'state' => 'resolved',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame($originalTitle, $ticket->title);
        $this->assertSame($originalDescription, $ticket->description);
        $this->assertSame($originalReporterId, $ticket->reporter_id);
        $this->assertSame('open', $ticket->state);
        $this->assertSame($newCategory->id, $ticket->category_id);
    }

    // ── Evidencias ───────────────────────────────────────────────────────────

    public function test_maintenance_can_upload_evidence_and_ticket_media_is_created(): void
    {
        Storage::fake('public');

        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'evidence' => [
                    UploadedFile::fake()->image('evidencia-reparacion.jpg', 200, 200),
                ],
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_media', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $maintenance->id,
        ]);

        // El historial registra la evidencia sin cambiar estado.
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'open',
            'changed_by' => $maintenance->id,
        ]);
    }

    public function test_uploading_evidence_does_not_delete_reporter_evidence(): void
    {
        Storage::fake('public');

        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.com/reporter-evidence.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($maintenance)
            ->patch(route('tickets.maintenance.update', $ticket), [
                'evidence' => [UploadedFile::fake()->image('nueva.jpg', 100, 100)],
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertCount(2, TicketMedia::where('ticket_id', $ticket->id)->get());
        $this->assertDatabaseHas('ticket_media', [
            'file_url' => 'https://example.com/reporter-evidence.jpg',
            'uploaded_by' => $reporter->id,
        ]);
    }

    public function test_evidence_rejects_disallowed_file_types(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.maintenance.update', $ticket), [
                'evidence' => [UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream')],
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHasErrors(['evidence.0']);

        $this->assertDatabaseCount('ticket_media', 0);
    }

    public function test_evidence_rejects_file_larger_than_max_file_kb(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->actingAs($maintenance)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.maintenance.update', $ticket), [
                // 6 MiB is larger than 5 MiB
                'evidence' => [UploadedFile::fake()->image('large.jpg')->size(6144)],
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHasErrors(['evidence.0']);
    }

    public function test_evidence_rejects_more_than_max_files(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("img{$i}.jpg")->size(100);
        }

        $this->actingAs($maintenance)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.maintenance.update', $ticket), [
                'evidence' => $files,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHasErrors(['evidence']);
    }

    public function test_evidence_rejects_total_accumulated_size(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $files = [];
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->image("img{$i}.jpg")->size(5200);
        }

        $this->actingAs($maintenance)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.maintenance.update', $ticket), [
                'evidence' => $files,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHasErrors(['evidence']);
    }

    // ── Vista: edición limitada visible solo para quien corresponde ─────────

    public function test_show_renders_category_select_and_save_button_for_assigned_maintenance(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory(['name' => 'Categoría visible en select']);

        $ticket = $this->createTicket($reporter, $category);
        $ticket->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('Guardar cambios');
        $response->assertSeeText('Adjuntar evidencia');
        $response->assertSee('maintenance-edit-form');
        $response->assertSee('Categoría visible en select');
        $response->assertSeeText('Los datos originales del reporte no se modifican.');
    }

    public function test_show_does_not_render_maintenance_edit_form_for_reporter(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category);

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSeeText('Guardar cambios');
        $response->assertDontSee('maintenance-edit-form');
    }

    // ── Timezone Perú (presentación) ─────────────────────────────────────────

    public function test_show_renders_dates_in_lima_timezone(): void
    {
        config(['app.display_timezone' => 'America/Lima']);

        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory();

        $ticket = $this->createTicket($reporter, $category, 'resolved');

        // 15:00 UTC == 10:00 en Lima (UTC-5, sin DST).
        $createdUtc = Carbon::parse('2026-06-10 15:00:00', 'UTC');
        $resolvedUtc = Carbon::parse('2026-06-10 20:30:00', 'UTC');
        $ticket->forceFill([
            'created_at' => $createdUtc,
            'resolved_at' => $resolvedUtc,
        ])->save();

        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => 'in_progress',
            'to_state' => 'resolved',
            'changed_by' => $admin->id,
            'comment' => 'Cerrado para test de timezone',
        ]);
        $historyUtc = Carbon::parse('2026-06-10 18:45:00', 'UTC');
        StateHistory::query()->whereKey($history->id)->update(['created_at' => $historyUtc]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        // created_at: 15:00 UTC → 10:00 Lima
        $response->assertSee('10/06/2026 10:00');
        // resolved_at: 20:30 UTC → 15:30 Lima
        $response->assertSee('10/06/2026 15:30');
        // state_history.created_at: 18:45 UTC → 13:45 Lima
        $response->assertSee('10/06/2026 13:45');
    }

    public function test_local_time_helper_converts_to_display_timezone(): void
    {
        config(['app.display_timezone' => 'America/Lima']);

        $utc = Carbon::parse('2026-01-15 03:00:00', 'UTC');

        // 03:00 UTC = 22:00 del día anterior en Lima.
        $this->assertSame('14/01/2026 22:00', LocalTime::format($utc));
        $this->assertNull(LocalTime::format(null));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createTicket(User $reporter, Category $category, string $state = 'open'): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket de edición limitada',
            'description' => 'Ticket usado para probar la edición limitada de maintenance.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->createLocation()->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLocation(array $overrides = []): Location
    {
        $base = [
            'name' => 'Aula Maintenance Edit',
            'building' => 'Edificio M',
            'floor' => '3',
            'room_code' => 'M-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ];

        return Location::create(array_merge($base, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCategory(array $overrides = []): Category
    {
        $base = [
            'name' => 'Categoria '.Str::lower(Str::random(8)),
            'icon' => 'bolt',
            'description' => 'Categoría para edición limitada',
        ];

        return Category::create(array_merge($base, $overrides));
    }
}
