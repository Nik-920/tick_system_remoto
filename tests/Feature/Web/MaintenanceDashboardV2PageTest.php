<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard Maintenance V2 — real-data phase at /dashboard/maintenance-v2.
 *
 * These tests pin the access boundary, the reused date-range wiring, the real
 * KPIs/charts (scoped to assigned_to = the technician), the activity and
 * assignments slices (exactly 5, no cross-technician leakage), the duplicate
 * count visibility, the PDF button preserving the range query string, and —
 * critically — that the current /dashboard is NOT replaced.
 */
class MaintenanceDashboardV2PageTest extends TestCase
{
    use RefreshDatabase;

    private int $locationSequence = 0;

    private ?Category $sharedCategory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    // ── Access boundary ──────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.maintenance-v2'))->assertRedirect(route('login'));
    }

    public function test_maintenance_can_view_the_v2_dashboard_with_real_data(): void
    {
        $me = $this->maintenance();
        $this->assignedTicket($me, 'in_progress', 'high');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $response->assertOk();
        $response->assertViewIs('dashboard.maintenance-v2');
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Resumen general de la operación de mantenimiento.');
    }

    public function test_reporter_cannot_view_the_v2_dashboard(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)->get(route('dashboard.maintenance-v2'))->assertForbidden();
    }

    public function test_admin_cannot_view_the_v2_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('dashboard.maintenance-v2'))->assertForbidden();
    }

    public function test_v2_dashboard_renders_all_sections(): void
    {
        $response = $this->actingAs($this->maintenance())->get(route('dashboard.maintenance-v2'));

        $response->assertOk();
        $response->assertSeeText('Tickets totales');
        $response->assertSeeText('Tiempo prom. resolución');
        $response->assertSeeText('Tickets por estado');
        $response->assertSeeText('Tickets por prioridad');
        $response->assertSeeText('Tickets por laboratorio');
        $response->assertSeeText('Actividad reciente');
        $response->assertSeeText('Mis asignaciones');
        $response->assertSeeText('Tasa de resolución');
        $response->assertSeeText('Tiempos de resolución');
        $response->assertSeeText('Tickets creados vs resueltos');
        $response->assertSeeText('Alertas importantes');
        $response->assertSeeText('Disponibles para tomar');
        $response->assertSeeText('Duplicados en tus tickets');
        $response->assertSeeText('Pendientes activos');
    }

    // ── Date range ───────────────────────────────────────────────

    public function test_default_range_is_last_30_days(): void
    {
        $response = $this->actingAs($this->maintenance())->get(route('dashboard.maintenance-v2'));

        $response->assertOk();
        $this->assertSame(DateRange::PRESET_LAST_30_DAYS, $response->viewData('rangePreset'));
        $response->assertSeeText('Últimos 30 días');
    }

    public function test_preset_last_7_days_changes_displayed_range(): void
    {
        $response = $this->actingAs($this->maintenance())
            ->get(route('dashboard.maintenance-v2', ['preset' => 'last_7_days']));

        $response->assertOk();
        $this->assertSame('last_7_days', $response->viewData('rangePreset'));
        // travelTo 2026-06-15 → last 7 days spans 09/06/2026 – 15/06/2026.
        $response->assertSeeText('09/06/2026');
        $response->assertSeeText('15/06/2026');
    }

    public function test_custom_date_range_shows_from_and_to_inputs_and_filters_kpis(): void
    {
        $me = $this->maintenance();

        // One ticket created inside the custom window, one well outside it.
        $this->assignedTicket($me, 'open', 'medium', createdAt: '2026-06-05 09:00:00');
        $this->assignedTicket($me, 'open', 'medium', createdAt: '2026-06-13 09:00:00');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2', [
            'preset' => 'custom',
            'from' => '2026-06-01',
            'to' => '2026-06-10',
        ]));

        $response->assertOk();
        $this->assertTrue($response->viewData('isCustomRange'));
        $response->assertSee('value="2026-06-01"', false);
        $response->assertSee('value="2026-06-10"', false);
        $this->assertSame('1', $this->kpiValue($response, 'total'));
    }

    public function test_invalid_inverted_range_returns_validation_error(): void
    {
        $this->actingAs($this->maintenance())
            ->get(route('dashboard.maintenance-v2', ['from' => '2026-06-10', 'to' => '2026-06-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_promoted_dashboard_for_maintenance_preserves_range(): void
    {
        $response = $this->actingAs($this->maintenance())
            ->get(route('dashboard.index', ['preset' => 'last_7_days']));

        $response->assertOk();
        $response->assertViewIs('dashboard.maintenance-v2');
        $this->assertSame('last_7_days', $response->viewData('rangePreset'));
        $this->assertStringContainsString('preset=last_7_days', (string) $response->viewData('pdfUrl'));
    }

    public function test_trend_chart_is_bucketed_to_at_most_eight_aligned_points(): void
    {
        // 30 daily points must collapse into ≤ 8 labelled markers, with the three
        // series kept the same length so every marker aligns with a label.
        $response = $this->actingAs($this->maintenance())
            ->get(route('dashboard.maintenance-v2', ['preset' => 'last_30_days']));

        $response->assertOk();
        $trend = $response->viewData('trend');
        $this->assertLessThanOrEqual(8, count($trend['labels']));
        $this->assertSameSize($trend['labels'], $trend['created']);
        $this->assertSameSize($trend['labels'], $trend['resolved']);
    }

    // ── KPIs (real, scoped) ──────────────────────────────────────

    public function test_kpi_resolved_count_is_correct(): void
    {
        $me = $this->maintenance();
        $this->resolvedTicket($me, '2026-06-10 08:00:00', '2026-06-10 09:00:00');
        $this->resolvedTicket($me, '2026-06-11 08:00:00', '2026-06-11 09:00:00');
        $this->assignedTicket($me, 'open', 'low'); // not resolved

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $this->assertSame('2', $this->kpiValue($response, 'resolved'));
    }

    public function test_kpi_rejected_count_is_correct(): void
    {
        $me = $this->maintenance();
        $this->assignedTicket($me, 'rejected', 'medium');
        $this->assignedTicket($me, 'rejected', 'high');
        $this->assignedTicket($me, 'open', 'low');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $this->assertSame('2', $this->kpiValue($response, 'rejected'));
    }

    public function test_average_resolution_uses_minutes_not_truncated_hours(): void
    {
        $me = $this->maintenance();
        // 90 minutes must read as 1.5 h, never 1 h.
        $this->resolvedTicket($me, '2026-06-10 08:30:00', '2026-06-10 10:00:00');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $response->assertSeeText('1.5 h');
        $this->assertSame('1.5 h', $this->kpiValue($response, 'avg'));
    }

    public function test_top_laboratories_only_include_safe_scoped_tickets(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();

        $mineLab = $this->createLocation('Laboratorio Propio');
        $foreignLab = $this->createLocation('Laboratorio Ajeno');

        $this->assignedTicketAt($me, $mineLab, 'open', 'medium');
        $this->assignedTicketAt($me, $mineLab, 'open', 'high');
        $this->assignedTicketAt($other, $foreignLab, 'open', 'critical');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $labNames = array_column($response->viewData('labBars')['items'], 'name');
        $this->assertContains('Laboratorio Propio', $labNames);
        $this->assertNotContains('Laboratorio Ajeno', $labNames);
    }

    // ── Activity feed ────────────────────────────────────────────

    public function test_recent_activity_shows_exactly_five_real_items(): void
    {
        $me = $this->maintenance();
        for ($i = 0; $i < 7; $i++) {
            $this->assignedTicket($me, 'in_progress', 'medium');
        }

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $this->assertCount(5, $response->viewData('activity'));
        $this->assertSame(7, $response->viewData('activityPager')['total']);
    }

    public function test_recent_activity_does_not_leak_other_maintenance_activity(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();

        $this->assignedTicket($me, 'in_progress', 'medium', title: 'TICKET-PROPIO-ACTIVIDAD');
        $this->assignedTicket($other, 'in_progress', 'medium', title: 'TICKET-AJENO-FUGA');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $response->assertSeeText('TICKET-PROPIO-ACTIVIDAD');
        $response->assertDontSeeText('TICKET-AJENO-FUGA');
    }

    // ── Assignments slice ────────────────────────────────────────

    public function test_assignments_section_shows_exactly_five_own_active_assignments(): void
    {
        $me = $this->maintenance();
        for ($i = 0; $i < 6; $i++) {
            $this->assignedTicket($me, 'open', 'medium');
        }
        // Resolved/rejected are not active and must be excluded.
        $this->resolvedTicket($me, '2026-06-10 08:00:00', '2026-06-10 09:00:00');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $this->assertCount(5, $response->viewData('assignments'));
        $this->assertSame(6, $response->viewData('assignmentsPager')['total']);
    }

    public function test_assignments_section_does_not_leak_other_maintenance_tickets(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();

        $this->assignedTicket($me, 'open', 'medium', title: 'ASIGNACION-PROPIA');
        $this->assignedTicket($other, 'open', 'medium', title: 'ASIGNACION-AJENA-FUGA');

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $titles = array_column($response->viewData('assignments'), 'title');
        $this->assertContains('ASIGNACION-PROPIA', $titles);
        $this->assertNotContains('ASIGNACION-AJENA-FUGA', $titles);
        $response->assertDontSeeText('ASIGNACION-AJENA-FUGA');
    }

    // ── Duplicate count ──────────────────────────────────────────

    public function test_duplicate_count_respects_visibility(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();

        $mine = $this->assignedTicket($me, 'open', 'medium');
        $this->flagDuplicate($mine);

        $foreign = $this->assignedTicket($other, 'open', 'medium');
        $this->flagDuplicate($foreign);

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2'));

        $this->assertSame('1', $this->bottomCardValue($response, 'Duplicados en tus tickets'));
    }

    // ── PDF export ───────────────────────────────────────────────

    public function test_export_pdf_button_reuses_the_existing_report_route_and_preserves_range(): void
    {
        $me = $this->maintenance();

        $response = $this->actingAs($me)->get(route('dashboard.maintenance-v2', ['preset' => 'last_7_days']));

        $response->assertOk();
        $response->assertSeeText('Exportar informe PDF');
        // Links to the EXISTING report route, carrying the active range.
        $response->assertSee(route('dashboard.maintenance.report.pdf', ['preset' => 'last_7_days']));
    }

    public function test_export_pdf_preserves_custom_range_query_string(): void
    {
        $response = $this->actingAs($this->maintenance())->get(route('dashboard.maintenance-v2', [
            'preset' => 'custom',
            'from' => '2026-06-01',
            'to' => '2026-06-10',
        ]));

        $response->assertOk();
        $pdfUrl = (string) $response->viewData('pdfUrl');
        $this->assertStringContainsString('preset=custom', $pdfUrl);
        $this->assertStringContainsString('from=2026-06-01', $pdfUrl);
        $this->assertStringContainsString('to=2026-06-10', $pdfUrl);
    }

    // ── Route + non-replacement guarantees ───────────────────────

    public function test_v2_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/dashboard/maintenance-v2', route('dashboard.maintenance-v2'));
    }

    public function test_promotion_keeps_other_role_dashboards_intact(): void
    {
        // After promotion only the maintenance role gets V2; reporter/admin/
        // super_admin keep their own dashboards, untouched.
        // Reporters are redirected to their own dedicated board.
        $this->actingAs($this->userWithRole('reporter'))
            ->get(route('dashboard.index'))
            ->assertRedirect(route('reporter.dashboard'));

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard.index'))
            ->assertViewIs('dashboard.admin');

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('dashboard.index'))
            ->assertViewIs('dashboard.admin');
    }

    public function test_maintenance_dashboard_index_renders_v2(): void
    {
        $this->actingAs($this->maintenance())
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewIs('dashboard.maintenance-v2');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function kpiValue(TestResponse $response, string $key): string
    {
        /** @var list<array{key:string,value:string}> $kpis */
        $kpis = $response->viewData('kpis');
        foreach ($kpis as $kpi) {
            if ($kpi['key'] === $key) {
                return $kpi['value'];
            }
        }

        return '';
    }

    private function bottomCardValue(TestResponse $response, string $label): string
    {
        /** @var list<array{value:string,label:string}> $cards */
        $cards = $response->viewData('bottomCards');
        foreach ($cards as $card) {
            if ($card['label'] === $label) {
                return $card['value'];
            }
        }

        return '';
    }

    private function maintenance(): User
    {
        return $this->userWithRole('maintenance');
    }

    private function assignedTicket(User $assignee, string $state, string $priority, ?string $title = null, ?string $createdAt = null): Ticket
    {
        return $this->assignedTicketAt($assignee, $this->createLocation(), $state, $priority, $title, $createdAt);
    }

    private function assignedTicketAt(User $assignee, Location $location, string $state, string $priority, ?string $title = null, ?string $createdAt = null): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title ?? 'Ticket '.$location->room_code,
            'description' => 'Ticket de prueba',
            'reporter_id' => $assignee->id,
            'location_id' => $location->id,
            'category_id' => $this->category()->id,
            'state' => $state,
            'priority' => $priority,
        ]);

        $fill = ['assigned_to' => $assignee->id];
        if ($createdAt !== null) {
            $fill['created_at'] = Carbon::parse($createdAt);
        }
        $ticket->forceFill($fill)->save();

        return $ticket;
    }

    private function resolvedTicket(User $assignee, string $createdAt, string $resolvedAt): Ticket
    {
        $ticket = $this->assignedTicket($assignee, 'resolved', 'medium', createdAt: $createdAt);
        $ticket->forceFill(['resolved_at' => Carbon::parse($resolvedAt)])->save();

        return $ticket;
    }

    private function flagDuplicate(Ticket $ticket): void
    {
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
        ]);
    }

    private function category(): Category
    {
        return $this->sharedCategory ??= Category::create([
            'name' => 'Hardware',
            'icon' => 'wrench',
            'description' => 'Categoria de prueba',
        ]);
    }

    private function createLocation(string $name = 'Laboratorio'): Location
    {
        $this->locationSequence++;
        $room = 'L-'.$this->locationSequence;

        return Location::create([
            'name' => $name,
            'building' => 'Edificio Z',
            'floor' => '1',
            'room_code' => $room,
            'qr_token' => 'qr-'.strtolower($room),
            'is_active' => true,
        ]);
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
}
