<?php

namespace Tests\Feature\Jobs;

use App\Events\DuplicateDetected;
use App\Jobs\DetectDuplicates;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Services\Ai\DeduplicationService;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateDetectionEngine;
use App\Services\Ai\EmbeddingService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakeEmbeddingProvider;
use Tests\TestCase;

class DetectDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_duplicate_and_dispatches_event(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Proyector principal');
        $matched = $this->createTicket('Proyector comparado', $ticket->location_id, $ticket->category_id);

        $vector = [1.0, 0.0];
        $hash = hash('sha256', $ticket->embeddingText());

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => $vector,
            'description_hash' => $hash,
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => 'other-hash',
            'is_duplicate' => false,
        ]);

        Event::fake([DuplicateDetected::class]);

        $deduplication = new DeduplicationService($this->makeEmbeddingService([0.0, 1.0]));
        $embeddings = $this->makeEmbeddingService([0.0, 1.0]);

        $job = new DetectDuplicates($ticket, 'corr-dup-001');
        $job->handle($deduplication, $embeddings, $this->makeLogger(), $this->makeEngine());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertSame($matched->id, $embedding->matched_ticket_id);
        $this->assertTrue($embedding->is_duplicate);
        $this->assertSame(1.0, $embedding->similarity_score);

        Event::assertDispatched(DuplicateDetected::class, function (DuplicateDetected $event) use ($ticket, $matched): bool {
            return $event->ticket->id === $ticket->id
                && $event->matchedTicket?->id === $matched->id
                && $event->similarityScore === 1.0;
        });
    }

    public function test_job_reuses_embedding_and_clears_duplicate_when_no_candidates(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket principal');
        $matched = $this->createTicket('Ticket comparado', $ticket->location_id, $ticket->category_id);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'similarity_score' => 0.9,
            'matched_ticket_id' => $matched->id,
            'is_duplicate' => true,
        ]);

        Event::fake([DuplicateDetected::class]);

        $deduplication = new DeduplicationService($this->makeEmbeddingService([0.0, 1.0]));
        $embeddings = $this->makeEmbeddingService([0.0, 1.0]);

        $job = new DetectDuplicates($ticket, 'corr-dup-002');
        $job->handle($deduplication, $embeddings, $this->makeLogger(), $this->makeEngine());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->matched_ticket_id);
        $this->assertNull($embedding->similarity_score);

        Event::assertNotDispatched(DuplicateDetected::class);
    }

    public function test_job_logs_warning_when_embedding_generation_fails(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
        ]);

        $ticket = $this->createTicket('Ticket con error');

        $embeddingService = $this->createMock(EmbeddingService::class);
        $embeddingService->method('isAvailable')->willReturn(true);
        $embeddingService->expects($this->once())
            ->method('generate')
            ->willThrowException(new \RuntimeException('fail'));

        $logger = $this->createMock(TicketQrLogger::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('ticket.duplicate.embedding_failed'),
                $this->callback(function (array $context) use ($ticket): bool {
                    return ($context['ticket_id'] ?? null) === $ticket->id
                        && ($context['correlation_id'] ?? null) === 'corr-dup-003';
                })
            );

        $job = new DetectDuplicates($ticket, 'corr-dup-003');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $embeddingService,
            $logger,
            $this->makeEngine()
        );

        $this->assertDatabaseMissing('ticket_embeddings', ['ticket_id' => $ticket->id]);
    }

    public function test_job_does_not_flag_duplicate_when_title_mismatch_in_observation_zone(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket(
            'Mesa rota en sala A-201',
            null,
            null,
            'La mesa del laboratorio A-201 tiene una pata rota y se mueve al apoyarse.'
        );
        $matched = $this->createTicket(
            'Proyector sala A-201 no enciende',
            $ticket->location_id,
            $ticket->category_id,
            'El proyector de la sala A-201 no responde al intentar encenderlo.'
        );

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [0.86, 0.51],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-dup-004');
        $job->handle(new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])), $this->makeEmbeddingService([0.0, 1.0]), $this->makeLogger(), $this->makeEngine());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->matched_ticket_id);
        $this->assertNull($embedding->similarity_score);
    }

    public function test_job_flags_duplicate_when_title_overlap_and_score_is_high(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket(
            'Proyector sala A-201 no enciende',
            null,
            null,
            'El proyector de la sala A-201 no responde al intentar encenderlo.'
        );
        $matched = $this->createTicket(
            'Problema con proyector en sala A-201',
            $ticket->location_id,
            $ticket->category_id,
            'El proyector no prende y la luz power parpadea.'
        );

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-dup-005');
        $job->handle(new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])), $this->makeEmbeddingService([0.0, 1.0]), $this->makeLogger(), $this->makeEngine());

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertTrue($embedding->is_duplicate);
        $this->assertSame($matched->id, $embedding->matched_ticket_id);
    }

    private function createTicket(
        string $title,
        ?string $locationId = null,
        ?string $categoryId = null,
        ?string $description = null
    ): Ticket {
        $user = User::factory()->create();

        $location = $locationId
            ? Location::query()->findOrFail($locationId)
            : Location::create([
                'name' => 'Aula Detect',
                'building' => 'Edificio D',
                'floor' => '2',
                'room_code' => 'D-201',
                'qr_token' => 'qr-d-201',
                'is_active' => true,
            ]);

        $category = $categoryId
            ? Category::query()->findOrFail($categoryId)
            : Category::create([
                'name' => 'Red',
                'icon' => 'wifi',
                'description' => 'Categoria red',
            ]);

        return Ticket::create([
            'title' => $title,
            'description' => $description ?? 'Descripcion para duplicados.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeEmbeddingService(array $vector): EmbeddingService
    {
        return new EmbeddingService(new FakeEmbeddingProvider($vector));
    }

    private function makeLogger(): TicketQrLogger
    {
        return new class extends TicketQrLogger
        {
            /**
             * @param  array<string, mixed>  $context
             */
            public function info(string $eventName, array $context = []): void {}

            /**
             * @param  array<string, mixed>  $context
             */
            public function warning(string $eventName, array $context = []): void {}
        };
    }

    /**
     * Build a DuplicateDetectionEngine with all strategies from the container.
     * Uses the real AppServiceProvider registrations.
     */
    private function makeEngine(): DuplicateDetectionEngine
    {
        return app(DuplicateDetectionEngine::class);
    }

    public function test_job_does_not_clear_review_status_when_no_candidates_found(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket con revisión previa');
        $reviewer = User::factory()->create();

        // Setup: embedding already dismissed by a human
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'review_status' => 'dismissed',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => 'Falso positivo.',
        ]);

        // Run job with no competing candidates → triggers resetEmbeddingMatch()
        $job = new DetectDuplicates($ticket, 'corr-review-001');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        // AI columns were reset by the job
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->similarity_score);
        $this->assertNull($embedding->matched_ticket_id);

        // Human review columns MUST be preserved
        $this->assertSame('dismissed', $embedding->review_status);
        $this->assertSame($reviewer->id, $embedding->reviewed_by);
        $this->assertNotNull($embedding->reviewed_at);
        $this->assertSame('Falso positivo.', $embedding->review_note);
    }

    public function test_job_does_not_clear_review_status_when_duplicate_found(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket('Proyector sala B-101');
        $matched = $this->createTicket('Proyector sala B-101 sin imagen', $ticket->location_id, $ticket->category_id);
        $reviewer = User::factory()->create();

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'review_status' => 'dismissed',
            'reviewed_by' => $reviewer->id,
            'review_note' => 'Revisado previamente.',
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-review-002');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        // AI updated is_duplicate = true again
        $this->assertTrue($embedding->is_duplicate);

        // Human review NOT cleared
        $this->assertSame('dismissed', $embedding->review_status);
        $this->assertSame($reviewer->id, $embedding->reviewed_by);
        $this->assertSame('Revisado previamente.', $embedding->review_note);
    }

    // ── New Strategy integration tests ────────────────────────────────────────

    public function test_strategy_engine_runs_in_parallel_and_does_not_block_duplicate_detection(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket('Proyector sala C-301 no enciende');
        $matched = $this->createTicket('Proyector sala C-301 falla encendido', $ticket->location_id, $ticket->category_id);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-strategy-001');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        // Legacy gate: duplicate should still be detected
        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertTrue($embedding->is_duplicate);

        // DuplicateDetected event must still fire
        Event::assertDispatched(DuplicateDetected::class);
    }

    public function test_strategy_engine_does_not_break_flow_when_no_candidates(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket('Ticket sin candidatos strategy');

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-strategy-002');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        // No candidates — embedding must be reset, no event dispatched
        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->matched_ticket_id);

        Event::assertNotDispatched(DuplicateDetected::class);
    }

    public function test_strategy_engine_evaluates_candidate_with_same_location_and_category(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Impresora fuera de servicio');
        $matched = $this->createTicket('Impresora sin toner', $ticket->location_id, $ticket->category_id);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $engine = $this->makeEngine();
        $context = new DuplicateCandidateContext(
            ticket: $ticket,
            candidate: $matched,
            now: Carbon::now(),
            embeddingSimilarity: 1.0,
        );

        $decision = $engine->evaluate($context);

        // Engine produces a decision with a positive score (same loc+cat+similarity)
        $this->assertGreaterThan(0, $decision->score);
        $this->assertNotEmpty($decision->results);
        $this->assertNotEmpty($decision->reasons);
    }

    public function test_strategy_engine_result_contains_strategy_metadata_for_audit(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Aire acondicionado roto sala D-401');
        $matched = $this->createTicket('Falla aire acondicionado D-401', $ticket->location_id, $ticket->category_id);

        $engine = $this->makeEngine();
        $context = new DuplicateCandidateContext(
            ticket: $ticket,
            candidate: $matched,
            now: Carbon::now(),
            embeddingSimilarity: 0.95,
        );

        $decision = $engine->evaluate($context);
        $logContext = $decision->toLogContext();

        $this->assertArrayHasKey('strategy_score', $logContext);
        $this->assertArrayHasKey('is_duplicate', $logContext);
        $this->assertArrayHasKey('is_recurrence', $logContext);
        $this->assertArrayHasKey('reasons', $logContext);
        $this->assertArrayHasKey('strategies_metadata', $logContext);
        $this->assertIsInt($logContext['strategy_score']);
    }

    public function test_job_persists_strategy_explanation_when_duplicate_detected(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket(
            'Proyector sala A-201 no enciende',
            null,
            null,
            'El proyector de la sala A-201 no responde al intentar encenderlo.'
        );
        $matched = $this->createTicket(
            'Problema con proyector en sala A-201',
            $ticket->location_id,
            $ticket->category_id,
            'El proyector no prende y la luz power parpadea.'
        );

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
        ]);

        TicketEmbedding::create([
            'ticket_id' => $matched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $matched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-strategy-persist-001');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        // Legacy decision intact.
        $this->assertTrue($embedding->is_duplicate);
        Event::assertDispatched(DuplicateDetected::class);

        // Strategy explainability persisted.
        $this->assertIsInt($embedding->strategy_score);
        $this->assertGreaterThan(0, $embedding->strategy_score);

        $this->assertIsArray($embedding->strategy_results);
        $this->assertNotEmpty($embedding->strategy_results);

        $first = $embedding->strategy_results[0];
        $this->assertArrayHasKey('strategy', $first);
        $this->assertArrayHasKey('points', $first);
        $this->assertArrayHasKey('reason', $first);

        $this->assertIsArray($embedding->strategy_metadata);
        $this->assertSame('legacy_with_strategy_metadata', $embedding->strategy_metadata['decision_source']);
        $this->assertTrue($embedding->strategy_metadata['legacy_is_duplicate']);
    }

    public function test_job_clears_strategy_explanation_when_no_candidates_found(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket con strategy previa');

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => true,
            'strategy_score' => 80,
            'strategy_results' => [
                ['strategy' => 'embedding_similarity', 'points' => 50, 'reason' => 'x', 'metadata' => [], 'blocksDuplicate' => false, 'suggestsRecurrence' => false],
            ],
            'strategy_metadata' => ['decision_source' => 'legacy_with_strategy_metadata'],
            'strategy_suggests_recurrence' => true,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-strategy-clear-001');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->strategy_score);
        $this->assertNull($embedding->strategy_results);
        $this->assertNull($embedding->strategy_metadata);
        $this->assertFalse($embedding->strategy_suggests_recurrence);
    }

    // ── Precheck lane isolation (2026_07_01_000100) ─────────────────────────
    // Mirrors test_job_does_not_clear_review_status_when_*: the reporter's
    // precheck confirmation ("caso distinto") is a third, independent column
    // lane the AI job must never read or write, whether it resets its own
    // columns (no candidate found) or confirms its own duplicate.

    public function test_job_does_not_clear_precheck_lane_when_no_candidates_found(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        $ticket = $this->createTicket('Ticket con precheck confirmado');
        // Shares location/category with $ticket (createTicket() would collide
        // on its default room_code otherwise), but has no TicketEmbedding row
        // of its own, so it can never become an AI candidate — this test's
        // premise is "no candidates found".
        $precheckMatch = $this->createTicket('Ticket relacionado por precheck', $ticket->location_id, $ticket->category_id);
        $confirmedAt = now()->subMinutes(5);

        // Setup: reporter confirmed "caso distinto" at creation time, but the
        // (stricter, independent) AI similarity check finds nothing of its own.
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [],
            'precheck_matched_ticket_id' => $precheckMatch->id,
            'precheck_reason' => 'Misma ubicación, categoría y título similar',
            'precheck_confirmed_at' => $confirmedAt,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-precheck-001');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        // AI columns were (re)computed by the job as usual
        $this->assertFalse($embedding->is_duplicate);
        $this->assertNull($embedding->matched_ticket_id);

        // Precheck lane MUST be preserved untouched
        $this->assertSame($precheckMatch->id, $embedding->precheck_matched_ticket_id);
        $this->assertSame('Misma ubicación, categoría y título similar', $embedding->precheck_reason);
        $this->assertNotNull($embedding->precheck_confirmed_at);
        $this->assertEqualsWithDelta($confirmedAt->timestamp, $embedding->precheck_confirmed_at->timestamp, 1);
    }

    public function test_job_does_not_clear_precheck_lane_when_it_finds_its_own_duplicate(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.90,
            'ai.dedup.observation_threshold' => 0.82,
            'ai.dedup.title_overlap_min_tokens' => 1,
            'ai.dedup.window_hours' => 24,
        ]);

        Event::fake([DuplicateDetected::class]);

        $ticket = $this->createTicket('Proyector sala C-301');
        $aiMatched = $this->createTicket('Proyector sala C-301 sin imagen', $ticket->location_id, $ticket->category_id);
        $precheckMatch = $this->createTicket('Ticket relacionado por precheck', $ticket->location_id, $ticket->category_id);

        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $ticket->embeddingText()),
            'is_duplicate' => false,
            'precheck_matched_ticket_id' => $precheckMatch->id,
            'precheck_reason' => 'Misma ubicación y categoría con descripción relacionada',
            'precheck_confirmed_at' => now()->subMinutes(2),
        ]);

        TicketEmbedding::create([
            'ticket_id' => $aiMatched->id,
            'embedding_vector' => [1.0, 0.0],
            'description_hash' => hash('sha256', $aiMatched->embeddingText()),
            'is_duplicate' => false,
        ]);

        $job = new DetectDuplicates($ticket, 'corr-precheck-002');
        $job->handle(
            new DeduplicationService($this->makeEmbeddingService([0.0, 1.0])),
            $this->makeEmbeddingService([0.0, 1.0]),
            $this->makeLogger(),
            $this->makeEngine()
        );

        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        // AI found and persisted its own (possibly different) match
        $this->assertTrue($embedding->is_duplicate);
        $this->assertSame($aiMatched->id, $embedding->matched_ticket_id);

        // Precheck lane still untouched, even though the AI's own candidate differs
        $this->assertSame($precheckMatch->id, $embedding->precheck_matched_ticket_id);
        $this->assertSame('Misma ubicación y categoría con descripción relacionada', $embedding->precheck_reason);
        $this->assertNotNull($embedding->precheck_confirmed_at);
    }
}
