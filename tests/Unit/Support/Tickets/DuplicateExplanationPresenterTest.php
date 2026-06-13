<?php

namespace Tests\Unit\Support\Tickets;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Support\Tickets\DuplicateExplanationPresenter;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicateExplanationPresenterTest extends TestCase
{
    public function test_not_visible_when_ticket_is_null(): void
    {
        $payload = DuplicateExplanationPresenter::present(null);

        $this->assertFalse($payload['visible']);
    }

    public function test_not_visible_when_there_is_no_embedding(): void
    {
        $ticket = $this->makeTicket();
        $ticket->setRelation('embedding', null);

        $payload = DuplicateExplanationPresenter::present($ticket);

        $this->assertFalse($payload['visible']);
    }

    public function test_not_visible_when_embedding_has_no_matched_ticket(): void
    {
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding(null, ['is_duplicate' => true]);
        $ticket->setRelation('embedding', $embedding);

        $payload = DuplicateExplanationPresenter::present($ticket);

        $this->assertFalse($payload['visible']);
    }

    public function test_not_visible_when_human_dismissed_the_duplicate(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);
        $ticket->setRelation('embedding', $embedding);

        $payload = DuplicateExplanationPresenter::present($ticket);

        $this->assertFalse($payload['visible']);
    }

    public function test_visible_for_pending_ai_duplicate_with_matched_ticket(): void
    {
        $matched = $this->makeTicket(['title' => 'Problema con proyector en sala A-201']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'review_status' => null,
            'similarity_score' => 0.97,
            'strategy_score' => 95,
            'strategy_results' => $this->sampleResults(),
        ]);

        $payload = $this->present($ticket, $embedding);

        $this->assertTrue($payload['visible']);
        $this->assertFalse($payload['isFallback']);
        $this->assertSame(95, $payload['score']);
        $this->assertSame(0.97, $payload['similarity']);
        $this->assertSame('Problema con proyector en sala A-201', $payload['matchedTicket']['title']);
    }

    public function test_fallback_when_no_strategy_results(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket antiguo']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'similarity_score' => 0.91,
            'strategy_results' => null,
        ]);

        $payload = $this->present($ticket, $embedding);

        $this->assertTrue($payload['visible']);
        $this->assertTrue($payload['isFallback']);
        $this->assertNull($payload['score']);
        $this->assertSame(0.91, $payload['similarity']);
        $this->assertSame([], $payload['topReasons']);
        $this->assertStringContainsString('No hay un desglose detallado', $payload['summary']);
    }

    public function test_shows_top_three_reasons_ordered_by_points_descending(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_score' => 100,
            'strategy_results' => $this->sampleResults(),
        ]);

        $payload = $this->present($ticket, $embedding);

        $this->assertCount(3, $payload['topReasons']);
        $points = array_column($payload['topReasons'], 'points');
        $this->assertSame([50, 25, 15], $points);
    }

    public function test_converts_strategy_identifiers_into_human_labels(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $location = new Location;
        $location->forceFill(['name' => 'Laboratorio 1']);
        $category = new Category;
        $category->forceFill(['name' => 'Audiovisuales']);

        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_results' => $this->sampleResults(),
        ]);

        $payload = $this->present($ticket, $embedding, $location, $category);

        $labels = array_column($payload['topReasons'], 'label');
        $this->assertContains('Alta similitud semántica', $labels);
        $this->assertContains('Misma ubicación', $labels);
        $this->assertContains('Misma categoría', $labels);

        // Detail sentences use the already-loaded ticket context, not raw ids.
        $details = array_column($payload['topReasons'], 'detail');
        $this->assertContains('Ambos tickets pertenecen a Laboratorio 1.', $details);
        $this->assertContains('Ambos tickets son de la categoría Audiovisuales.', $details);
    }

    public function test_shows_warnings_when_strategy_suggests_recurrence(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $results = $this->sampleResults();
        $results[] = [
            'strategy' => 'recurrence_guard',
            'points' => -10,
            'reason' => 'Candidate is old in same location+category — likely recurrence.',
            'metadata' => ['candidate_age_hours' => 900],
            'blocksDuplicate' => false,
            'suggestsRecurrence' => true,
        ];

        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_suggests_recurrence' => true,
            'strategy_results' => $results,
        ]);

        $payload = $this->present($ticket, $embedding);

        $this->assertNotEmpty($payload['warnings']);
        $this->assertContains('Posible recurrencia', array_column($payload['warnings'], 'label'));
        $this->assertSame('La IA detecta una posible recurrencia.', $payload['headline']);
    }

    public function test_negative_candidate_state_is_surfaced_as_warning(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket cancelado']);
        $ticket = $this->makeTicket();
        $results = $this->sampleResults();
        $results[] = [
            'strategy' => 'candidate_state',
            'points' => -30,
            'reason' => 'Candidate was cancelled — strong counter-evidence.',
            'metadata' => ['candidate_state' => Ticket::STATE_CANCELLED],
            'blocksDuplicate' => false,
            'suggestsRecurrence' => false,
        ];

        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_results' => $results,
        ]);

        $payload = $this->present($ticket, $embedding);

        $warningDetails = array_column($payload['warnings'], 'detail');
        $this->assertContains('El ticket similar fue cancelado, así que probablemente no sea el mismo caso.', $warningDetails);
    }

    public function test_does_not_crash_on_corrupt_or_incomplete_metadata(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_results' => [
                'not-an-array',
                ['points' => 5], // missing strategy → dropped
                ['strategy' => 123], // non-string strategy → dropped
                ['strategy' => 'same_location', 'points' => 25, 'metadata' => 'corrupt', 'reason' => null],
            ],
        ]);

        $payload = $this->present($ticket, $embedding);

        $this->assertTrue($payload['visible']);
        $this->assertFalse($payload['isFallback']);
        $this->assertContains('Misma ubicación', array_column($payload['topReasons'], 'label'));
    }

    public function test_technical_details_include_all_persisted_results(): void
    {
        $matched = $this->makeTicket(['title' => 'Ticket similar']);
        $ticket = $this->makeTicket();
        $embedding = $this->makeEmbedding($matched, [
            'is_duplicate' => true,
            'strategy_results' => $this->sampleResults(),
        ]);

        $payload = $this->present($ticket, $embedding);

        // sampleResults() has 4 contributing strategies.
        $this->assertCount(4, $payload['technicalDetails']);
        $this->assertSame('embedding_similarity', $payload['technicalDetails'][0]['strategy']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTicket(array $attrs = []): Ticket
    {
        $ticket = new Ticket;
        $ticket->forceFill(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Ticket origen',
            'state' => Ticket::STATE_OPEN,
        ], $attrs));

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeEmbedding(?Ticket $matched, array $attrs = []): TicketEmbedding
    {
        $embedding = new TicketEmbedding;
        $embedding->forceFill(array_merge([
            'id' => (string) Str::uuid(),
            'similarity_score' => 0.97,
        ], $attrs));
        $embedding->setRelation('matchedTicket', $matched);

        return $embedding;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(
        Ticket $ticket,
        TicketEmbedding $embedding,
        ?Location $location = null,
        ?Category $category = null
    ): array {
        $ticket->setRelation('embedding', $embedding);
        $ticket->setRelation('location', $location);
        $ticket->setRelation('category', $category);

        return DuplicateExplanationPresenter::present($ticket);
    }

    /**
     * Four positive contributing strategies (50/25/15/10).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleResults(): array
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
}
