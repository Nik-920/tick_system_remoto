<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the professional maintenance report endpoints
 * (HTML preview + PDF): authorization, document sections, AI-duplicate
 * explainability rendering and personal-data isolation.
 */
class MaintenanceProfessionalReportTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->location = Location::create([
            'name' => 'Laboratorio 1',
            'building' => 'Edificio Z',
            'floor' => '2',
            'room_code' => 'L-201',
            'qr_token' => 'qr-l-201',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Hardware',
            'icon' => 'wrench',
            'description' => 'Categoría de prueba',
        ]);
    }

    // ── Autorización ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.maintenance.report'))->assertRedirect(route('login'));
    }

    public function test_reporter_and_admin_roles_cannot_access_the_report(): void
    {
        foreach (['reporter', 'admin', 'super_admin'] as $role) {
            $user = $this->createUserWithRole($role);

            $this->actingAs($user)
                ->get(route('dashboard.maintenance.report'))
                ->assertForbidden();
        }
    }

    public function test_maintenance_generates_their_own_report_without_technician_selector(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $other = $this->createUserWithRole('maintenance');

        $this->createAssignedTicket($other, ['title' => 'Confidencial de otro técnico']);

        // Intentar pedir datos de otro técnico vía query string no tiene efecto.
        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report', ['technician' => $other->id, 'user_id' => $other->id]));

        $response->assertOk();
        $response->assertDontSeeText('Confidencial de otro técnico');
    }

    // ── Documento ────────────────────────────────────────────────────────────

    public function test_preview_renders_all_professional_sections(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('Informe Profesional de Mantenimiento');
        $response->assertSeeText('Resumen ejecutivo');
        $response->assertSeeText('Alcance del informe');
        $response->assertSeeText('Indicadores clave');
        $response->assertSeeText('Asignaciones actuales del técnico');
        $response->assertSeeText('Tickets críticos y atrasados');
        $response->assertSeeText('Tickets por laboratorio');
        $response->assertSeeText('Tickets por categoría');
        $response->assertSeeText('Trabajo resuelto en el periodo');
        $response->assertSeeText('Tiempos de atención y resolución');
        $response->assertSeeText('Evidencias registradas');
        $response->assertSeeText('Incidencias recurrentes por laboratorio');
        $response->assertSeeText('Riesgos operativos');
        $response->assertSeeText('Recomendaciones técnicas');
        $response->assertSeeText('Alertas IA y posibles duplicados');
        $response->assertSeeText('Cola global disponible');
        $response->assertSeeText('Calidad de datos del informe');
        $response->assertSeeText('Anexo');
        $response->assertSeeText('Definiciones de métricas');
    }

    public function test_preview_handles_empty_states_without_errors(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('El técnico no tiene asignaciones activas');
        $response->assertSeeText('No se detectan tickets activos del técnico marcados como posibles duplicados por IA.');
        $response->assertSeeText('No se registran incidencias recurrentes relevantes');
    }

    public function test_preview_shows_assignment_with_location_evidence_and_recommended_action(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createAssignedTicket($maintenance, ['title' => 'Proyector sin señal', 'state' => 'in_progress']);

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://example.test/foto.jpg',
            'file_type' => 'image',
            'uploaded_by' => $maintenance->id,
        ]);

        $this->addTransition($ticket, 'open', 'in_progress', Carbon::parse('2026-06-12 09:00:00'), $maintenance);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('Proyector sin señal');
        $response->assertSeeText('Laboratorio 1');
        $response->assertSeeText('Edificio Z');
        $response->assertSeeText('L-201');
        $response->assertSeeText('#TIC-'.substr($ticket->id, 0, 8));
        $response->assertSeeText('Sí (1)');
    }

    public function test_preview_shows_human_readable_duplicate_reasons_and_no_raw_json(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $matched = Ticket::create([
            'title' => 'Proyector dañado reportado antes',
            'description' => 'Original',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = $this->createAssignedTicket($maintenance, ['title' => 'Proyector dañado de nuevo']);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matched->id,
            'similarity_score' => 0.95,
            'strategy_score' => 75,
            'strategy_results' => [
                [
                    'strategy' => 'embedding_similarity',
                    'points' => 50,
                    'reason' => 'High semantic similarity',
                    'metadata' => ['similarity' => 0.95],
                    'blocksDuplicate' => false,
                    'suggestsRecurrence' => false,
                ],
                [
                    'strategy' => 'same_location',
                    'points' => 15,
                    'reason' => 'Same location',
                    'metadata' => [],
                    'blocksDuplicate' => false,
                    'suggestsRecurrence' => false,
                ],
                [
                    'strategy' => 'same_category',
                    'points' => 10,
                    'reason' => 'Same category',
                    'metadata' => [],
                    'blocksDuplicate' => false,
                    'suggestsRecurrence' => false,
                ],
            ],
        ]);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('Proyector dañado reportado antes');
        $response->assertSeeText('Alta similitud semántica');
        $response->assertSeeText('Misma ubicación');
        $response->assertSeeText('Misma categoría');
        $response->assertSeeText('Pendiente de revisión');
        $response->assertSeeText('Revisar duplicidad antes de iniciar trabajo duplicado');

        // Nunca JSON crudo de strategy_results.
        $response->assertDontSee('blocksDuplicate');
        $response->assertDontSee('suggestsRecurrence');
        $response->assertDontSee('embedding_similarity');
    }

    public function test_preview_shows_fallback_for_legacy_duplicates_without_strategy_metadata(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $matched = Ticket::create([
            'title' => 'Registro antiguo similar',
            'description' => 'Original',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = $this->createAssignedTicket($maintenance, ['title' => 'Duplicado legado']);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'matched_ticket_id' => $matched->id,
            'similarity_score' => 0.91,
            'strategy_results' => null,
        ]);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('No hay un desglose detallado');
        $response->assertSeeText('Sin desglose');
    }

    public function test_preview_labels_global_queue_as_area_context(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        Ticket::create([
            'title' => 'Ticket libre en cola',
            'description' => 'Sin asignar',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => 'open',
            'priority' => 'low',
        ]);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('contexto del área');
        $response->assertSeeText('Ticket libre en cola');
        // La cola no contamina los KPIs personales.
        $response->assertSeeText('El técnico no tiene asignaciones activas');
    }

    public function test_preview_explains_cancelled_treatment(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createAssignedTicket($maintenance, ['title' => 'Retirado por reporter', 'state' => 'cancelled']);
        $this->addTransition($ticket, 'open', 'cancelled', Carbon::parse('2026-06-10 09:00:00'), $maintenance);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText('Retirado por reporter');
        $response->assertSeeText('no penaliza al técnico');
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    public function test_maintenance_can_download_the_professional_pdf(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $this->createAssignedTicket($maintenance, ['title' => 'Para PDF']);

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report.pdf', [
                'preset' => 'custom',
                'from' => '2026-06-01',
                'to' => '2026-06-15',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $response->assertDownload('informe-mantenimiento-2026-06-01_2026-06-15.pdf');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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
    private function createAssignedTicket(User $technician, array $overrides = []): Ticket
    {
        $ticket = Ticket::create(array_merge([
            'title' => 'Ticket de prueba '.Str::random(6),
            'description' => 'Descripción de prueba',
            'reporter_id' => $technician->id,
            'location_id' => $this->location->id,
            'category_id' => $this->category->id,
            'state' => 'open',
            'priority' => 'medium',
        ], $overrides));

        $ticket->forceFill([
            'assigned_to' => $technician->id,
            'assigned_at' => Carbon::now(),
        ])->save();

        return $ticket->refresh();
    }

    private function addTransition(Ticket $ticket, ?string $from, string $to, Carbon $at, User $by): void
    {
        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
            'comment' => null,
        ]);

        $history->forceFill(['created_at' => $at])->save();
    }
}
