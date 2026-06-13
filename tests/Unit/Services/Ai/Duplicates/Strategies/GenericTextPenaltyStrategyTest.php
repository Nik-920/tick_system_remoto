<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\GenericTextPenaltyStrategy;

final class GenericTextPenaltyStrategyTest extends StrategyTestCase
{
    private GenericTextPenaltyStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new GenericTextPenaltyStrategy;
        config(['ai.dedup.strategies.generic_text_penalty' => [
            'enabled' => true,
            'min_useful_words' => 4,
            'penalty' => -20,
        ]]);
    }

    public function test_penalises_title_with_too_few_tokens(): void
    {
        // "roto" → 1 token, less than min_useful_words (4)
        $ctx = $this->makeContext(ticketAttrs: ['title' => 'Roto']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-20, $result->points);
    }

    public function test_penalises_title_with_only_generic_words(): void
    {
        // All tokens match GENERIC_WORDS list
        $ctx = $this->makeContext(ticketAttrs: ['title' => 'Ayuda urgente problema falla error']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-20, $result->points);
    }

    public function test_returns_zero_for_specific_title_with_enough_tokens(): void
    {
        $ctx = $this->makeContext(ticketAttrs: [
            'title' => 'Proyector sala B-201 enciende parpadea constantemente',
        ]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.generic_text_penalty' => ['enabled' => false]]);

        $ctx = $this->makeContext(ticketAttrs: ['title' => 'ayuda']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_token_count(): void
    {
        $ctx = $this->makeContext(ticketAttrs: ['title' => 'Proyector roto encendido falla']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('token_count', $result->metadata);
        $this->assertIsInt($result->metadata['token_count']);
    }

    public function test_empty_title_is_penalised(): void
    {
        $ctx = $this->makeContext(ticketAttrs: ['title' => '']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-20, $result->points);
    }
}
