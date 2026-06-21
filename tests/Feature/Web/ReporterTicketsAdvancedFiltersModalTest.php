<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reporter Tickets — Advanced Filter Modal.
 *
 * Verifies that /reporter/tickets exposes the new advanced-filter modal
 * (render, status counts, functional filters, lab label polish, no-leak).
 */
class ReporterTicketsAdvancedFiltersModalTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Render / modal presence ───────────────────────────────

    public function test_reporter_sees_filter_button(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open');

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('rep-filter-btn', false)
            ->assertSee('Abrir filtros avanzados', false);
    }

    public function test_html_contains_advanced_filters_modal_title(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('rep-filter-modal', false)
            ->assertSeeText('Filtros avanzados');
    }

    public function test_modal_contains_estado_section(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('name="status"', false);
    }

    public function test_modal_contains_prioridad_section(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('name="priority"', false)
            ->assertSeeText('Prioridad');
    }

    public function test_modal_contains_laboratorio_section(): void
    {
        $me = $this->reporter();
        $loc = $this->location('LAB-1', 'Laboratorio 1', 'Pabellon A');
        $this->ticketFor($me, 'open', ['location_id' => $loc->id]);

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('name="location_id"', false)
            ->assertSeeText('Laboratorio');
    }

    public function test_modal_contains_categoria_section(): void
    {
        $me = $this->reporter();
        // Ensure at least one category exists so the section renders.
        $this->category('Hardware');

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('name="category_id"', false)
            ->assertSeeText('Categoría');
    }

    public function test_modal_contains_ordenar_por_section(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('name="sort"', false)
            ->assertSeeText('Ordenar por');
    }

    public function test_modal_contains_desde_and_hasta_inputs(): void
    {
        $me = $this->reporter();

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));
        $response->assertOk()
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSeeText('Desde')
            ->assertSeeText('Hasta');
    }

    public function test_modal_contains_aplicar_filtros_button(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSeeText('Aplicar filtros');
    }

    public function test_modal_contains_limpiar_link(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSeeText('Limpiar');
    }

    // ── 2. Estado + conteos ──────────────────────────────────────

    public function test_modal_shows_todos_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open');
        $this->ticketFor($me, 'resolved');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $all = collect($board->chips)->firstWhere('key', 'all');

        $this->assertSame(2, $all['count']);
    }

    public function test_modal_shows_abiertos_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open');
        $this->ticketFor($me, 'open');
        $this->ticketFor($me, 'resolved');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'open');

        $this->assertSame(2, $chip['count']);
    }

    public function test_modal_shows_en_progreso_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'in_progress');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'in_progress');

        $this->assertSame(1, $chip['count']);
    }

    public function test_modal_shows_resueltos_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'resolved');
        $this->ticketFor($me, 'resolved');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'resolved');

        $this->assertSame(2, $chip['count']);
    }

    public function test_modal_shows_rechazados_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'rejected');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'rejected');

        $this->assertSame(1, $chip['count']);
    }

    public function test_modal_shows_cancelados_with_correct_count(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'cancelled');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'cancelled');

        $this->assertSame(1, $chip['count']);
    }

    public function test_modal_shows_posible_duplicado_with_correct_count(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open');
        $this->markDuplicate($ticket);

        // Another reporter's duplicate must not inflate the count.
        $other = $this->reporter();
        $this->markDuplicate($this->ticketFor($other, 'open'));

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chip = collect($board->chips)->firstWhere('key', 'duplicate');

        $this->assertSame(1, $chip['count']);
    }

    // ── 3. Functional filters ────────────────────────────────────

    public function test_filter_by_resolved_shows_only_resolved(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open', [], 'Abierto mío');
        $this->ticketFor($me, 'resolved', [], 'Resuelto mío');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSeeText('Resuelto mío')
            ->assertDontSeeText('Abierto mío');
    }

    public function test_filter_by_cancelled_shows_only_cancelled(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open', [], 'Abierto mío');
        $this->ticketFor($me, 'cancelled', [], 'Cancelado mío');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSeeText('Cancelado mío')
            ->assertDontSeeText('Abierto mío');
    }

    public function test_filter_by_duplicate_shows_only_duplicates(): void
    {
        $me = $this->reporter();
        $dup = $this->ticketFor($me, 'open', [], 'Posible duplicado');
        $this->markDuplicate($dup);
        $this->ticketFor($me, 'open', [], 'Normal sin señal');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'duplicate']))
            ->assertOk()
            ->assertSeeText('Posible duplicado')
            ->assertDontSeeText('Normal sin señal');
    }

    public function test_filter_by_priority_works(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open', ['priority' => 'high'], 'Alta prioridad');
        $this->ticketFor($me, 'open', ['priority' => 'low'], 'Baja prioridad');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['priority' => 'high']))
            ->assertOk()
            ->assertSeeText('Alta prioridad')
            ->assertDontSeeText('Baja prioridad');
    }

    public function test_filter_by_location_works(): void
    {
        $me = $this->reporter();
        $loc1 = $this->location('LAB-1', 'Laboratorio 1', 'Pabellon A');
        $loc2 = $this->location('LAB-2', 'Laboratorio 2', 'Pabellon B');

        $this->ticketFor($me, 'open', ['location_id' => $loc1->id], 'En Lab 1');
        $this->ticketFor($me, 'open', ['location_id' => $loc2->id], 'En Lab 2');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['location_id' => $loc1->id]))
            ->assertOk()
            ->assertSeeText('En Lab 1')
            ->assertDontSeeText('En Lab 2');
    }

    public function test_filter_by_category_works(): void
    {
        $me = $this->reporter();
        $cat1 = $this->category('Hardware');
        $cat2 = $this->category('Software');

        $this->ticketFor($me, 'open', ['category_id' => $cat1->id], 'Ticket Hardware');
        $this->ticketFor($me, 'open', ['category_id' => $cat2->id], 'Ticket Software');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['category_id' => $cat1->id]))
            ->assertOk()
            ->assertSeeText('Ticket Hardware')
            ->assertDontSeeText('Ticket Software');
    }

    public function test_filter_by_date_range_from_works(): void
    {
        $me = $this->reporter();
        $old = $this->ticketFor($me, 'open', ['created_at' => '2026-01-01 09:00:00'], 'Antiguo');
        $new = $this->ticketFor($me, 'open', ['created_at' => '2026-06-15 09:00:00'], 'Reciente');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['from' => '2026-06-01']))
            ->assertOk()
            ->assertSeeText('Reciente')
            ->assertDontSeeText('Antiguo');
    }

    public function test_filter_by_date_range_to_works(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open', ['created_at' => '2026-01-05 09:00:00'], 'Enero');
        $this->ticketFor($me, 'open', ['created_at' => '2026-06-15 09:00:00'], 'Junio');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['to' => '2026-02-01']))
            ->assertOk()
            ->assertSeeText('Enero')
            ->assertDontSeeText('Junio');
    }

    public function test_sort_oldest_works(): void
    {
        $me = $this->reporter();
        // Create 'Reciente' first so insertion order != created_at order.
        $this->ticketFor($me, 'open', ['created_at' => '2026-06-01 09:00:00'], 'Reciente');
        $this->ticketFor($me, 'open', ['created_at' => '2026-01-01 09:00:00'], 'Antiguo');

        // sort=oldest orders by created_at ASC → Antiguo must come first.
        $board = $this->actingAs($me)->get(route('reporter.tickets.index', ['sort' => 'oldest']))->viewData('board');

        $this->assertSame('Antiguo', $board->tickets[0]['title']);
    }

    public function test_clear_filters_url_returns_to_base_board(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'resolved', [], 'Resuelto');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'resolved', 'priority' => 'high']));
        $response->assertOk();

        // The "Limpiar" link in the modal points to the base index with no params.
        $response->assertSee(route('reporter.tickets.index'), false);

        // Navigating to that URL shows all tickets.
        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSeeText('Resuelto');
    }

    // ── 4. Lab label polish ──────────────────────────────────────

    public function test_lab_card_uses_descriptive_label_not_building_only(): void
    {
        $me = $this->reporter();
        $loc = $this->location('AUL-004', 'Laboratorio 4', 'Pabellon de aulas');
        $this->ticketFor($me, 'open', ['location_id' => $loc->id]);

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');

        $this->assertNotEmpty($board->labs['items']);
        $label = $board->labs['items'][0]['label'];

        // Must contain building, code, and name — not just the building.
        $this->assertStringContainsString('Pabellon de aulas', $label);
        $this->assertStringContainsString('AUL-004', $label);
        $this->assertStringContainsString('Laboratorio 4', $label);
    }

    public function test_two_labs_in_same_building_have_distinct_labels(): void
    {
        $me = $this->reporter();
        $loc1 = $this->location('AUL-001', 'Aula 101', 'Pabellon de aulas');
        $loc2 = $this->location('AUL-002', 'Aula 201', 'Pabellon de aulas');

        $this->ticketFor($me, 'open', ['location_id' => $loc1->id]);
        $this->ticketFor($me, 'open', ['location_id' => $loc2->id]);

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $labels = array_column($board->labs['items'], 'label');

        $this->assertCount(2, array_unique($labels), 'Labels must be distinct for locations in the same building');
    }

    public function test_location_select_in_modal_uses_descriptive_label(): void
    {
        $me = $this->reporter();
        $loc = $this->location('AUL-004', 'Laboratorio 4', 'Pabellon de aulas');
        $this->ticketFor($me, 'open', ['location_id' => $loc->id]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));
        $response->assertOk();

        // The select option must show the full descriptive label, not just the name.
        $response->assertSee('Pabellon de aulas · AUL-004 · Laboratorio 4', false);
    }

    // ── 5. Accessibility ─────────────────────────────────────────

    public function test_modal_close_button_has_aria_label(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('aria-label="Cerrar filtros"', false);
    }

    public function test_filter_button_has_aria_expanded(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('aria-expanded="false"', false);
    }

    public function test_date_inputs_have_labels(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertSee('for="rep-fm-from"', false)
            ->assertSee('for="rep-fm-to"', false);
    }

    // ── 6. No-leak ───────────────────────────────────────────────

    public function test_filter_does_not_expose_other_reporters_tickets(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $loc = $this->location('LAB-Z', 'Laboratorio Z', 'Edificio Z');

        $this->ticketFor($me, 'open', ['location_id' => $loc->id], 'Mío en lab Z');
        $this->ticketFor($other, 'open', ['location_id' => $loc->id], 'Ajeno en lab Z');

        $this->actingAs($me)->get(route('reporter.tickets.index', ['location_id' => $loc->id]))
            ->assertOk()
            ->assertSeeText('Mío en lab Z')
            ->assertDontSeeText('Ajeno en lab Z');
    }

    public function test_html_does_not_expose_email_or_phone(): void
    {
        $me = $this->reporter();
        // Use a non-@example.com domain so the topbar dropdown (which correctly
        // shows the authenticated user's own email) does not trip the assertion.
        $me->update(['email' => 'reporter@institution.test']);
        $this->ticketFor($me, 'open');

        $html = $this->actingAs($me)->get(route('reporter.tickets.index'))->getContent();

        // The page must not expose emails beyond the authenticated user's own.
        $this->assertStringNotContainsString('@example.com', (string) $html);
    }

    public function test_html_does_not_expose_assigned_to_or_assigned_by(): void
    {
        $me = $this->reporter();
        $tech = $this->userWithRole('maintenance');
        $ticket = $this->ticketFor($me, 'open');
        $ticket->forceFill(['assigned_to' => $tech->id])->save();

        $html = $this->actingAs($me)->get(route('reporter.tickets.index'))->getContent();

        // assigned_to UUID must not appear as a standalone data attribute or text.
        $this->assertStringNotContainsString('assigned_to', (string) $html);
        $this->assertStringNotContainsString('assigned_by', (string) $html);
    }

    public function test_html_does_not_expose_duplicate_detection_internals(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open');
        $this->markDuplicate($ticket);

        $html = $this->actingAs($me)->get(route('reporter.tickets.index'))->getContent();

        // The reporter sees the badge but not technical AI internals.
        $this->assertStringNotContainsString('embedding_vector', (string) $html);
        $this->assertStringNotContainsString('description_hash', (string) $html);
        $this->assertStringNotContainsString('review_status', (string) $html);
    }

    // ── 7. Regression ────────────────────────────────────────────

    public function test_reporter_tickets_page_test_still_passes_board_view(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open', [], 'Board visible');

        $this->actingAs($me)->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertViewIs('tickets.reporter.index')
            ->assertSeeText('Mis tickets')
            ->assertSeeText('Board visible');
    }

    public function test_filter_badge_shows_count_when_filters_active(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me, 'open');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index', ['priority' => 'high', 'status' => 'open']))->viewData('board');

        $this->assertSame(2, $board->activeFiltersCount());
    }

    public function test_filter_badge_is_zero_with_no_active_filters(): void
    {
        $me = $this->reporter();

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');

        $this->assertSame(0, $board->activeFiltersCount());
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function reporter(): User
    {
        return $this->userWithRole('reporter');
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ticketFor(User $reporter, string $state, array $attrs = [], string $title = 'Test ticket'): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => $state,
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => false,
        ]);

        if (! empty($attrs['created_at'])) {
            $ticket->forceFill(['created_at' => $attrs['created_at']])->save();
        }

        return $ticket;
    }

    private function markDuplicate(Ticket $ticket): void
    {
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2, 0.3],
            'description_hash' => Str::uuid()->toString(),
            'is_duplicate' => true,
        ]);
    }

    private function location(
        string $code = 'LAB-1',
        string $name = 'Laboratorio 1',
        string $building = 'Edificio A',
    ): Location {
        return Location::firstOrCreate(
            ['room_code' => $code],
            [
                'name' => $name,
                'building' => $building,
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
