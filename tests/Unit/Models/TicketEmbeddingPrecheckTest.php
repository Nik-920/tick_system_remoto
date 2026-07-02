<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit-level tests for the reporter duplicate-precheck fields on
 * TicketEmbedding (precheck_matched_ticket_id, precheck_reason,
 * precheck_confirmed_at) — see the 2026_07_01_000100 migration.
 *
 * Covers:
 *  - Model fillable/casts for the precheck columns
 *  - precheckMatchedTicket() relation
 *  - hasPrecheckCandidate() helper
 *  - Independence from the AI lane (is_duplicate/effective_duplicate) and the
 *    human-review lane (review_status)
 */
class TicketEmbeddingPrecheckTest extends TestCase
{
    use RefreshDatabase;

    private function createTicket(string $title = 'Test ticket'): Ticket
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Aula Precheck Model',
            'building' => 'Edificio P',
            'floor' => '1',
            'room_code' => 'P-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Precheck Cat '.Str::lower(Str::random(6)),
            'icon' => 'check',
            'description' => 'Category for precheck model tests',
        ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para precheck.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    public function test_precheck_columns_are_persisted_correctly(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');
        $confirmedAt = now();

        $embedding = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'precheck_matched_ticket_id' => $matched->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => $confirmedAt,
        ]);

        $fresh = TicketEmbedding::find($embedding->id);

        $this->assertSame($matched->id, $fresh->precheck_matched_ticket_id);
        $this->assertSame('Misma ubicación, categoría y título similar', $fresh->precheck_reason);
        $this->assertNotNull($fresh->precheck_confirmed_at);
        $this->assertEqualsWithDelta($confirmedAt->timestamp, $fresh->precheck_confirmed_at->timestamp, 1);
    }

    public function test_has_precheck_candidate_reflects_matched_ticket_presence(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');

        $withCandidate = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'precheck_matched_ticket_id' => $matched->id,
            'precheck_reason' => 'Misma ubicación y categoría con descripción relacionada',
            'precheck_confirmed_at' => now(),
        ]);

        $withoutCandidate = TicketEmbedding::create([
            'ticket_id' => $this->createTicket('Sin precheck')->id,
            'embedding_vector' => [0.1, 0.2],
        ]);

        $this->assertTrue($withCandidate->hasPrecheckCandidate());
        $this->assertFalse($withoutCandidate->hasPrecheckCandidate());
    }

    public function test_precheck_matched_ticket_relation_loads_ticket(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');

        $embedding = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'precheck_matched_ticket_id' => $matched->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => now(),
        ]);

        $loaded = TicketEmbedding::with('precheckMatchedTicket')->find($embedding->id);

        $this->assertNotNull($loaded->precheckMatchedTicket);
        $this->assertSame($matched->id, $loaded->precheckMatchedTicket->id);
    }

    /**
     * The precheck lane must never influence is_duplicate/effective_duplicate:
     * a reporter-confirmed "caso distinto" is not an AI or human duplicate
     * verdict — see the business-rule note in the 2026_07_01_000100 migration.
     */
    public function test_precheck_candidate_does_not_affect_effective_duplicate(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');

        $embedding = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'is_duplicate' => false,
            'precheck_matched_ticket_id' => $matched->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => now(),
        ]);

        $this->assertFalse($embedding->effective_duplicate);
        $this->assertTrue($embedding->isPendingReview());
        $this->assertTrue($embedding->hasPrecheckCandidate());
    }

    public function test_precheck_columns_survive_an_unrelated_review_status_update(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');
        $reviewer = User::factory()->create();

        $embedding = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.5, 0.5],
            'is_duplicate' => true,
            'precheck_matched_ticket_id' => $matched->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => now(),
        ]);

        $embedding->review_status = TicketEmbedding::REVIEW_CONFIRMED;
        $embedding->reviewed_by = $reviewer->id;
        $embedding->reviewed_at = now();
        $embedding->save();

        $fresh = TicketEmbedding::find($embedding->id);

        $this->assertSame(TicketEmbedding::REVIEW_CONFIRMED, $fresh->review_status);
        $this->assertSame($matched->id, $fresh->precheck_matched_ticket_id);
        $this->assertSame('Misma ubicación, categoría y título similar', $fresh->precheck_reason);
        $this->assertNotNull($fresh->precheck_confirmed_at);
    }
}
