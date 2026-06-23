<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * History card v2 — /reporter/tickets/history visual contract.
 *
 * Verifies the new card structure (.rep-history-card) is rendered correctly
 * and that the old inline card markup is gone. Filters, pagination, and
 * ownership isolation are covered in ReporterHistoryPageTest; this suite
 * focuses on the card's visual anatomy and data mapping.
 */
class ReporterTicketHistoryCardV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    // ── Page-level structure (preserved) ─────────────────────────────────────

    public function test_page_renders_history_title(): void
    {
        $me = $this->reporter();

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertSeeText('Historial de mis tickets');
    }

    public function test_page_renders_search_and_filter_toolbar(): void
    {
        $me = $this->reporter();

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Buscar por título, ID, descripción', $html);
        $this->assertStringContainsString('rep-chips', $html);
    }

    public function test_page_renders_result_chips(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Todos', $html);
        $this->assertStringContainsString('Resueltos', $html);
        $this->assertStringContainsString('Cancelados', $html);
    }

    // ── Card shell ────────────────────────────────────────────────────────────

    public function test_card_renders_new_class(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card', $html);
    }

    public function test_old_card_class_is_not_rendered(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('rep-item__icon', $html);
        $this->assertStringNotContainsString('rep-hist-side', $html);
        $this->assertStringNotContainsString('rep-stamps', $html);
        $this->assertStringNotContainsString('rep-stamp__label', $html);
    }

    // ── Card data: ref + title + meta ─────────────────────────────────────────

    public function test_card_renders_ticket_ref(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Test ref ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card__ref', $html);
        $this->assertStringContainsString('#', $html);
    }

    public function test_card_renders_ticket_title(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Incidencia en laboratorio');

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertSeeText('Incidencia en laboratorio');
    }

    public function test_card_renders_location_and_category_in_meta(): void
    {
        $me = $this->reporter();
        $loc = $this->location('LAB-V2', 'Laboratorio v2');
        $cat = $this->category('Hardware v2');
        $this->resolvedFor($me, 'Meta ticket', $loc, $cat);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card__meta', $html);
        $this->assertStringContainsString('Laboratorio v2', $html);
        $this->assertStringContainsString('Hardware v2', $html);
    }

    public function test_card_renders_room_code_in_meta_when_present(): void
    {
        $me = $this->reporter();
        $loc = $this->location('AUL-99', 'Aula 99');
        $this->resolvedFor($me, 'Aula ticket', $loc);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('AUL-99', $html);
    }

    // ── Badges ────────────────────────────────────────────────────────────────

    public function test_resolved_ticket_shows_status_badge(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-status--resolved', $html);
        $this->assertStringContainsString('Resuelto', $html);
    }

    public function test_cancelled_ticket_shows_status_badge(): void
    {
        $me = $this->reporter();
        $this->cancelledFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-status--cancelled', $html);
        $this->assertStringContainsString('Cancelado', $html);
    }

    public function test_card_renders_priority_badge(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Ticket crítico', null, null, 'critical');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-card__prio-badge', $html);
        $this->assertStringContainsString('Crítica', $html);
    }

    // ── Dates ─────────────────────────────────────────────────────────────────

    public function test_card_renders_created_date_label(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card__date', $html);
        $this->assertStringContainsString('Creado', $html);
    }

    public function test_card_renders_closed_date_label_matching_status(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card__date-label--closed', $html);
        $this->assertStringContainsString('Resuelto', $html);
    }

    public function test_card_renders_date_values(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Dated ticket', null, null, 'medium', [
            'created_at' => Carbon::parse('2026-06-10 08:00:00', 'America/Lima'),
            'resolved_at' => Carbon::parse('2026-06-15 14:00:00', 'America/Lima'),
        ]);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('10/06/2026', $html);
        $this->assertStringContainsString('15/06/2026', $html);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function test_card_renders_ver_detalle_link_with_correct_route(): void
    {
        $me = $this->reporter();
        $ticket = $this->resolvedFor($me, 'Link ticket');

        $response = $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk();

        $response->assertSeeText('Ver detalle');
        $response->assertSee(route('reporter.tickets.show', $ticket->id), false);
    }

    public function test_card_renders_chevron_on_ver_detalle(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me);

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-history-card__detail', $html);
    }

    public function test_card_renders_kebab_menu_without_export(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Kebab ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-kebab', $html);
        $this->assertStringNotContainsString('Exportar ticket', $html);
    }

    // ── Social stats ─────────────────────────────────────────────────────────

    public function test_card_renders_heart_reaction_form(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Heart ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-card__reaction-form', $html);
        $this->assertStringContainsString('data-community-social-form', $html);
        $this->assertStringContainsString('Marcar como Me interesa', $html);
    }

    public function test_card_renders_comment_button(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Comment ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-card__comment-btn', $html);
        $this->assertStringContainsString('data-reporter-ticket-comments-trigger', $html);
    }

    public function test_card_renders_status_badge_with_icon(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Icon badge ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rep-card__status-badge', $html);
        $this->assertStringContainsString('rep-status--resolved', $html);
    }

    public function test_card_renders_building_icon_in_meta(): void
    {
        $me = $this->reporter();
        $this->resolvedFor($me, 'Building icon ticket');

        $html = (string) $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->getContent();

        // building-2 lucide icon is rendered as an SVG in the metadata
        $this->assertStringContainsString('rep-history-card__meta', $html);
    }

    // ── Pagination ────────────────────────────────────────────────────────────

    public function test_pagination_counter_is_still_rendered(): void
    {
        $me = $this->reporter();
        for ($i = 1; $i <= 6; $i++) {
            $this->resolvedFor($me, "Ticket paginado {$i}");
        }

        $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk()
            ->assertSeeText('Mostrando 1 a 5 de 6 tickets');
    }

    // ── No-leak ───────────────────────────────────────────────────────────────

    public function test_reporter_does_not_see_other_reporters_tickets(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $this->resolvedFor($me, 'Mi ticket propio');
        $this->resolvedFor($other, 'Ticket ajeno privado');

        $response = $this->actingAs($me)
            ->get(route('reporter.tickets.history'))
            ->assertOk();

        $response->assertSeeText('Mi ticket propio');
        $response->assertDontSeeText('Ticket ajeno privado');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        $u = User::factory()->create();
        $u->assignRole('reporter');

        return $u;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function resolvedFor(
        User $reporter,
        string $title = 'Ticket resuelto',
        ?Location $location = null,
        ?Category $category = null,
        string $priority = 'medium',
        array $attrs = [],
    ): Ticket {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => ($location ?? $this->location())->id,
            'category_id' => ($category ?? $this->category())->id,
            'state' => 'resolved',
            'priority' => $priority,
            'assignment_locked' => false,
        ]);

        $force = array_filter([
            'resolved_at' => $attrs['resolved_at'] ?? Carbon::now(),
            'created_at' => $attrs['created_at'] ?? null,
        ], fn ($v): bool => $v !== null);

        if ($force !== []) {
            $ticket->forceFill($force)->save();
        }

        return $ticket;
    }

    private function cancelledFor(User $reporter, string $title = 'Ticket cancelado'): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'cancelled',
            'priority' => 'medium',
            'assignment_locked' => false,
        ]);

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'cancelled',
            'changed_by' => $reporter->id,
            'comment' => null,
        ]);

        return $ticket;
    }

    private function location(string $code = 'LAB-HCV2', string $name = 'Laboratorio HCV2'): Location
    {
        return Location::firstOrCreate(
            ['room_code' => $code],
            [
                'name' => $name,
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-hcv2-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(string $name = 'Hardware HCV2'): Category
    {
        return Category::firstOrCreate(
            ['name' => $name],
            ['icon' => 'monitor', 'description' => 'Categoría '.$name],
        );
    }
}
