<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Export ticket removal — confirms "Exportar ticket" and "Próximamente"
 * placeholder were removed from reporter and maintenance history views.
 */
class ReporterTicketHistoryExportRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    // ── Reporter history (/reporter/tickets/history) ──────────────────────────

    public function test_reporter_history_does_not_show_exportar_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->resolvedTicketFor($me);

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertDontSee('Exportar ticket');
    }

    public function test_reporter_history_does_not_show_proximamente(): void
    {
        $me = $this->userWithRole('reporter');

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertDontSee('Próximamente');
    }

    public function test_reporter_history_ver_detalle_still_works(): void
    {
        $me = $this->userWithRole('reporter');
        $this->resolvedTicketFor($me);

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertSee('Ver detalle');
    }

    public function test_reporter_history_kebab_has_no_export_action(): void
    {
        $me = $this->userWithRole('reporter');
        $this->resolvedTicketFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-kebab', $html);
        $this->assertStringNotContainsString('Exportar ticket', $html);
    }

    // ── Maintenance history (/tickets/history) ────────────────────────────────

    public function test_maintenance_history_does_not_show_exportar_ticket(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->actingAs($me)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertDontSee('Exportar ticket');
    }

    public function test_maintenance_history_does_not_show_proximamente(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->actingAs($me)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertDontSee('Próximamente');
    }

    public function test_maintenance_history_kebab_ver_detalle_still_present(): void
    {
        $me = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $this->resolvedTicketFor($reporter, assignedTo: $me);

        $this->actingAs($me)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee('Ver detalle');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function resolvedTicketFor(User $reporter, ?User $assignedTo = null): Ticket
    {
        $ticket = Ticket::create([
            'title' => 'Ticket export test '.Str::uuid(),
            'description' => 'Descripción de prueba con largo suficiente para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'low',
        ]);

        $ticket->forceFill(array_filter([
            'assigned_to' => $assignedTo?->id,
            'assigned_by' => $assignedTo?->id,
            'assigned_at' => $assignedTo ? now() : null,
            'assignment_source' => $assignedTo ? Ticket::ASSIGNMENT_SOURCE_SELF : null,
            'state' => 'resolved',
            'resolved_at' => now(),
        ], fn ($v): bool => $v !== null))->save();

        return $ticket;
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'EXPREM-01'],
            [
                'name' => 'Laboratorio Export Removal',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-export-removal-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'ExportRemovalTest'],
            ['icon' => 'download', 'description' => 'Categoría para tests de remoción de export'],
        );
    }
}
