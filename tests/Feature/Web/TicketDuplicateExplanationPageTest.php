<?php

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

class TicketDuplicateExplanationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_page_explains_why_ticket_was_flagged_as_duplicate(): void
    {
        [$ticket] = $this->makeDuplicatePair([
            'is_duplicate' => true,
            'similarity_score' => 0.97,
            'strategy_score' => 95,
            'strategy_suggests_recurrence' => false,
            'strategy_results' => $this->strategyResults(),
        ]);

        $reviewer = $this->createUserWithRole('maintenance');

        $response = $this
            ->actingAs($reviewer)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        // Existing banner is preserved.
        $response->assertSeeText('Posible duplicado detectado por IA');

        // New explanation section.
        $response->assertSeeText('¿Por qué la IA lo marcó como posible duplicado?');
        $response->assertSeeText('Alta similitud semántica');
        $response->assertSeeText('Misma ubicación');
        $response->assertSeeText('Misma categoría');

        // Score + similarity (reviewer-facing).
        $response->assertSeeText('Score IA: 95/100');
        $response->assertSeeText('Similitud: 0.97');

        // Collapsible technical breakdown.
        $response->assertSeeText('Ver detalles técnicos');

        // Existing review controls remain intact.
        $response->assertSeeText('Marcar como no duplicado');
        $response->assertSeeText('Confirmar duplicado');
        $response->assertSeeText('Ver ticket');
    }

    public function test_detail_page_shows_fallback_for_legacy_duplicate_without_strategy_metadata(): void
    {
        [$ticket] = $this->makeDuplicatePair([
            'is_duplicate' => true,
            'similarity_score' => 0.91,
            'strategy_results' => null,
        ]);

        $reviewer = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($reviewer)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('¿Por qué la IA lo marcó como posible duplicado?');
        $response->assertSeeText('No hay un desglose detallado disponible para este registro');
        $response->assertDontSeeText('Ver detalles técnicos');
    }

    public function test_reporter_sees_reasons_but_not_the_numeric_ai_score(): void
    {
        [$ticket, $reporter] = $this->makeDuplicatePair([
            'is_duplicate' => true,
            'similarity_score' => 0.97,
            'strategy_score' => 95,
            'strategy_results' => $this->strategyResults(),
        ]);

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSeeText('¿Por qué la IA lo marcó como posible duplicado?');
        $response->assertSeeText('Misma ubicación');

        // Numeric AI internals stay gated behind reviewDuplicate.
        $response->assertDontSeeText('Score IA');
        $response->assertDontSeeText('Similitud');
        $response->assertDontSeeText('Ver detalles técnicos');
    }

    public function test_explanation_hidden_when_human_dismissed_the_duplicate(): void
    {
        [$ticket] = $this->makeDuplicatePair([
            'is_duplicate' => true,
            'similarity_score' => 0.97,
            'strategy_score' => 95,
            'strategy_results' => $this->strategyResults(),
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        $reviewer = $this->createUserWithRole('maintenance');

        $response = $this
            ->actingAs($reviewer)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSeeText('¿Por qué la IA lo marcó como posible duplicado?');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a duplicate ticket + its matched ticket + a TicketEmbedding.
     *
     * @param  array<string, mixed>  $embeddingAttrs
     * @return array{0: Ticket, 1: User} [duplicateTicket, reporter]
     */
    private function makeDuplicatePair(array $embeddingAttrs): array
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation(['name' => 'Laboratorio 1']);
        $category = $this->createCategory(['name' => 'Audiovisuales']);

        $matched = Ticket::create([
            'title' => 'Problema con proyector en sala A-201',
            'description' => 'El proyector no prende y la luz power parpadea.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $ticket = Ticket::create([
            'title' => 'Proyector sala A-201 no enciende',
            'description' => 'El proyector de la sala A-201 no responde al intentar encenderlo.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        TicketEmbedding::create(array_merge([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'matched_ticket_id' => $matched->id,
        ], $embeddingAttrs));

        return [$ticket, $reporter];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function strategyResults(): array
    {
        return [
            [
                'strategy' => 'embedding_similarity',
                'points' => 50,
                'reason' => 'High embedding similarity (0.97) >= 0.9.',
                'metadata' => ['similarity' => 0.97],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
            [
                'strategy' => 'same_location',
                'points' => 25,
                'reason' => 'Ticket and candidate share the same location.',
                'metadata' => [],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
            [
                'strategy' => 'same_category',
                'points' => 15,
                'reason' => 'Ticket and candidate share the same category.',
                'metadata' => [],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
            [
                'strategy' => 'title_overlap',
                'points' => 10,
                'reason' => 'Medium title overlap ratio 0.5 (>= 0.45).',
                'metadata' => [],
                'blocksDuplicate' => false,
                'suggestsRecurrence' => false,
            ],
        ];
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
        return Location::create(array_merge([
            'name' => 'Aula Innovacion',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria '.Str::lower(Str::random(8)),
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ], $overrides));
    }
}
