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
 * Unit-level tests for the manual duplicate-review fields on TicketEmbedding.
 *
 * Covers:
 *  - Model fillable/casts for review columns
 *  - effective_duplicate accessor logic (all 4 combinations)
 *  - Helper methods (isDismissedDuplicate, isConfirmedDuplicate, isPendingReview)
 *  - AI columns are NOT changed when only review columns are updated
 */
class TicketEmbeddingReviewTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createTicket(string $title = 'Test ticket'): Ticket
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Aula Review',
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'T-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Review Cat '.Str::lower(Str::random(6)),
            'icon' => 'check',
            'description' => 'Category for review tests',
        ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para revisión de duplicados.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function createEmbedding(Ticket $ticket, bool $isDuplicate, ?string $reviewStatus = null): TicketEmbedding
    {
        return TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2, 0.3],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => $isDuplicate,
            'review_status' => $reviewStatus,
        ]);
    }

    // ── Test 1: Review columns are persisted correctly ────────────────────

    public function test_review_columns_are_persisted_correctly(): void
    {
        $ticket = $this->createTicket();
        $reviewer = User::factory()->create();
        $embedding = $this->createEmbedding($ticket, true);

        $embedding->review_status = TicketEmbedding::REVIEW_DISMISSED;
        $embedding->reviewed_by = $reviewer->id;
        $embedding->reviewed_at = now();
        $embedding->review_note = 'No es duplicado, es otro equipo.';
        $embedding->save();

        $fresh = TicketEmbedding::find($embedding->id);

        $this->assertSame(TicketEmbedding::REVIEW_DISMISSED, $fresh->review_status);
        $this->assertSame($reviewer->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertSame('No es duplicado, es otro equipo.', $fresh->review_note);
        // AI column must NOT change
        $this->assertTrue($fresh->is_duplicate);
    }

    // ── Test 2: effective_duplicate logic — all 4 combinations ───────────

    /** Case: is_duplicate=true, review_status=null → effective=true */
    public function test_effective_duplicate_true_when_ai_true_and_no_review(): void
    {
        $ticket = $this->createTicket();
        $embedding = $this->createEmbedding($ticket, true, null);

        $this->assertTrue($embedding->effective_duplicate);
        $this->assertTrue($embedding->isPendingReview());
    }

    /** Case: is_duplicate=true, review_status=dismissed → effective=false */
    public function test_effective_duplicate_false_when_ai_true_and_dismissed(): void
    {
        $ticket = $this->createTicket();
        $embedding = $this->createEmbedding($ticket, true, TicketEmbedding::REVIEW_DISMISSED);

        $this->assertFalse($embedding->effective_duplicate);
        $this->assertTrue($embedding->isDismissedDuplicate());
    }

    /** Case: is_duplicate=false, review_status=confirmed → effective=true */
    public function test_effective_duplicate_true_when_ai_false_and_confirmed(): void
    {
        $ticket = $this->createTicket();
        $embedding = $this->createEmbedding($ticket, false, TicketEmbedding::REVIEW_CONFIRMED);

        $this->assertTrue($embedding->effective_duplicate);
        $this->assertTrue($embedding->isConfirmedDuplicate());
    }

    /** Case: is_duplicate=false, review_status=null → effective=false */
    public function test_effective_duplicate_false_when_ai_false_and_no_review(): void
    {
        $ticket = $this->createTicket();
        $embedding = $this->createEmbedding($ticket, false, null);

        $this->assertFalse($embedding->effective_duplicate);
        $this->assertTrue($embedding->isPendingReview());
    }

    public function test_effective_duplicates_scope_returns_only_effective_matches(): void
    {
        $dupAi = $this->createTicket('Dup AI');
        $dismissed = $this->createTicket('Dup dismissed');
        $confirmed = $this->createTicket('Dup confirmed');
        $normal = $this->createTicket('Normal');

        $this->createEmbedding($dupAi, true, null);
        $this->createEmbedding($dismissed, true, TicketEmbedding::REVIEW_DISMISSED);
        $this->createEmbedding($confirmed, false, TicketEmbedding::REVIEW_CONFIRMED);
        $this->createEmbedding($normal, false, null);

        $ids = TicketEmbedding::query()->effectiveDuplicates()->pluck('ticket_id')->all();

        $this->assertContains($dupAi->id, $ids);
        $this->assertContains($confirmed->id, $ids);
        $this->assertNotContains($dismissed->id, $ids);
        $this->assertNotContains($normal->id, $ids);
    }

    // ── Test 3: reviewer() relation ──────────────────────────────────────

    public function test_reviewer_relation_loads_user(): void
    {
        $ticket = $this->createTicket();
        $reviewer = User::factory()->create();
        $embedding = $this->createEmbedding($ticket, true);

        $embedding->reviewed_by = $reviewer->id;
        $embedding->save();

        $loaded = TicketEmbedding::with('reviewer')->find($embedding->id);

        $this->assertNotNull($loaded->reviewer);
        $this->assertSame($reviewer->id, $loaded->reviewer->id);
    }

    // ── Test 4: AI columns unchanged after review update ─────────────────

    public function test_ai_columns_unchanged_after_review_update(): void
    {
        $ticket = $this->createTicket();
        $matched = $this->createTicket('Ticket similar');
        $embedding = TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.9, 0.1],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'similarity_score' => 0.97,
            'matched_ticket_id' => $matched->id,
        ]);

        // Simulate human review
        $embedding->review_status = TicketEmbedding::REVIEW_DISMISSED;
        $embedding->reviewed_at = now();
        $embedding->save();

        $fresh = TicketEmbedding::find($embedding->id);

        // AI data intact
        $this->assertTrue($fresh->is_duplicate);
        $this->assertEqualsWithDelta(0.97, $fresh->similarity_score, 0.001);
        $this->assertSame($matched->id, $fresh->matched_ticket_id);
        // Human review applied
        $this->assertSame(TicketEmbedding::REVIEW_DISMISSED, $fresh->review_status);
    }
}
