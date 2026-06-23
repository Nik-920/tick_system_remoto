<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Evidence cards refactor — /tickets/{ticket} evidence section.
 *
 * Verifies that the evidence section no longer renders a <table> and instead
 * shows card-based markup, while preserving security (no raw file_url),
 * group separation (reporter vs maintenance), counts, and accessible actions.
 */
class TicketShowEvidenceCardsTest extends TestCase
{
    use RefreshDatabase;

    // ── Section presence ──────────────────────────────────────────────────────

    public function test_ticket_show_renders_evidence_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Evidencias / adjuntos', $html);
        $this->assertStringContainsString('Evidencias del reporter', $html);
        $this->assertStringContainsString('Evidencias de maintenance', $html);
    }

    // ── No table headers ──────────────────────────────────────────────────────

    public function test_no_table_column_headers_in_evidence_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/file.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<th scope="col">Archivo</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Tipo</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Fecha</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Subido por</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Ver</th>', $html);
    }

    // ── Counts ────────────────────────────────────────────────────────────────

    public function test_reporter_evidence_count_is_correct(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        // Count badge: {{ $count }} is surrounded by whitespace in the template
        $this->assertMatchesRegularExpression('/Evidencias del reporter.{0,600}>\s*1\s*<\/span>/s', $html);
    }

    public function test_maintenance_evidence_count_is_zero_when_none(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Evidencias de maintenance.{0,600}>\s*0\s*<\/span>/s', $html);
    }

    // ── Card content ──────────────────────────────────────────────────────────

    public function test_reporter_evidence_card_shows_filename(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/unique-evidence-file.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('unique-evidence-file.jpg', $html);
    }

    public function test_reporter_evidence_card_shows_file_type(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/report.pdf', 'application/pdf');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('pdf', $html);
    }

    public function test_reporter_evidence_card_shows_formatted_date(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        // LocalTime::format renders dd/mm/YYYY HH:ii
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $html);
    }

    public function test_reporter_evidence_card_renders_uploader_area(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__evidence-uploader', $html);
        $this->assertStringContainsString('ticket-show__evidence-uploader-name', $html);
    }

    // ── View link accessibility ───────────────────────────────────────────────

    public function test_evidence_view_link_has_aria_label_and_noopener(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Ver archivo', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    // ── Security: no raw file_url ─────────────────────────────────────────────

    public function test_no_raw_file_url_in_rendered_html(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $rawUrl = 'https://example.supabase.co/storage/v1/object/public/TableTicket/private.jpg';
        $this->mediaFor($ticket, $reporter, $rawUrl);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($rawUrl, $html);
        $this->assertStringNotContainsString('supabase.co', $html);
    }

    // ── Empty state ───────────────────────────────────────────────────────────

    public function test_maintenance_empty_state_renders_when_no_maintenance_evidence(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg'); // only reporter evidence

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No hay evidencias registradas.', $html);
    }

    public function test_both_groups_show_empty_state_when_no_evidence_at_all(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $count = substr_count($html, 'No hay evidencias registradas.');
        $this->assertGreaterThanOrEqual(2, $count, 'Both groups must render empty state when ticket has no evidence');
    }

    // ── Maintenance evidence cards ────────────────────────────────────────────

    public function test_maintenance_evidence_cards_render_when_present(): void
    {
        $admin = $this->userWithRole('admin');
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $maintenance, 'https://example.com/maint-photo-xyz.jpg');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('maint-photo-xyz.jpg', $html);
    }

    // ── Group separation ──────────────────────────────────────────────────────

    public function test_reporter_and_maintenance_evidence_are_both_rendered(): void
    {
        $admin = $this->userWithRole('admin');
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/reporter-unique.jpg');
        $this->mediaFor($ticket, $maintenance, 'https://example.com/maint-unique.jpg');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('reporter-unique.jpg', $html);
        $this->assertStringContainsString('maint-unique.jpg', $html);
        $this->assertStringContainsString('Evidencias del reporter', $html);
        $this->assertStringContainsString('Evidencias de maintenance', $html);
    }

    // ── Card CSS classes ──────────────────────────────────────────────────────

    public function test_evidence_card_css_classes_present_in_html(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__evidence-list', $html);
        $this->assertStringContainsString('ticket-show__evidence-card', $html);
        $this->assertStringContainsString('ticket-show__evidence-info', $html);
        $this->assertStringContainsString('ticket-show__evidence-actions', $html);
    }

    // ── No legacy table markup ────────────────────────────────────────────────

    public function test_no_legacy_table_css_classes_in_evidence_html(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->mediaFor($ticket, $reporter, 'https://example.com/photo.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ticket-show__table-wrap', $html);
        $this->assertStringNotContainsString('ticket-show__table', $html);
    }

    // ── View route proxy in links ─────────────────────────────────────────────

    public function test_evidence_links_use_proxy_route_not_storage_url(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $media = $this->mediaFor($ticket, $reporter, 'https://example.supabase.co/storage/v1/object/public/file.jpg');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $proxyUrl = route('tickets.media.view', [$ticket->id, $media->id]);
        $this->assertStringContainsString($proxyUrl, $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket evidencias '.Str::uuid(),
            'description' => 'Descripción de prueba con largo suficiente para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function mediaFor(Ticket $ticket, User $uploader, string $fileUrl, string $fileType = 'image'): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'EVCRD-01'],
            [
                'name' => 'Laboratorio Evidencias Cards',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-evidence-cards-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'EvidenceCardsTest'],
            ['icon' => 'paperclip', 'description' => 'Categoría para tests de evidence cards'],
        );
    }
}
