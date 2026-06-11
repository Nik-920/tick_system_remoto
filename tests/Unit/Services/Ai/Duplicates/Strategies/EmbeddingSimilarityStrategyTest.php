<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\EmbeddingSimilarityStrategy;

final class EmbeddingSimilarityStrategyTest extends StrategyTestCase
{
    private EmbeddingSimilarityStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new EmbeddingSimilarityStrategy;
    }

    public function test_returns_zero_when_similarity_is_null(): void
    {
        $context = $this->makeContext(similarity: null);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(0, $result->points);
        $this->assertSame('embedding_similarity', $result->strategy);
    }

    public function test_returns_high_points_when_similarity_at_or_above_high_threshold(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => [
            'enabled' => true,
            'high_threshold' => 0.90,
            'medium_threshold' => 0.85,
            'low_threshold' => 0.80,
            'weight_high' => 50,
            'weight_medium' => 35,
            'weight_low' => 20,
        ]]);

        $context = $this->makeContext(similarity: 0.95);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(50, $result->points);
        $this->assertSame('high', $result->metadata['level']);
    }

    public function test_returns_medium_points_when_similarity_in_medium_range(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => [
            'enabled' => true,
            'high_threshold' => 0.90,
            'medium_threshold' => 0.85,
            'low_threshold' => 0.80,
            'weight_high' => 50,
            'weight_medium' => 35,
            'weight_low' => 20,
        ]]);

        $context = $this->makeContext(similarity: 0.87);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(35, $result->points);
        $this->assertSame('medium', $result->metadata['level']);
    }

    public function test_returns_low_points_when_similarity_in_low_range(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => [
            'enabled' => true,
            'high_threshold' => 0.90,
            'medium_threshold' => 0.85,
            'low_threshold' => 0.80,
            'weight_high' => 50,
            'weight_medium' => 35,
            'weight_low' => 20,
        ]]);

        $context = $this->makeContext(similarity: 0.82);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(20, $result->points);
        $this->assertSame('low', $result->metadata['level']);
    }

    public function test_returns_zero_when_similarity_below_all_thresholds(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => [
            'enabled' => true,
            'high_threshold' => 0.90,
            'medium_threshold' => 0.85,
            'low_threshold' => 0.80,
            'weight_high' => 50,
            'weight_medium' => 35,
            'weight_low' => 20,
        ]]);

        $context = $this->makeContext(similarity: 0.70);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(0, $result->points);
        $this->assertArrayHasKey('similarity', $result->metadata);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => ['enabled' => false]]);

        $context = $this->makeContext(similarity: 1.0);
        $result = $this->strategy->evaluate($context);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_similarity_and_threshold_used(): void
    {
        config(['ai.dedup.strategies.embedding_similarity' => [
            'enabled' => true,
            'high_threshold' => 0.90,
            'medium_threshold' => 0.85,
            'low_threshold' => 0.80,
            'weight_high' => 50,
            'weight_medium' => 35,
            'weight_low' => 20,
        ]]);

        $context = $this->makeContext(similarity: 0.95);
        $result = $this->strategy->evaluate($context);

        $this->assertArrayHasKey('similarity', $result->metadata);
        $this->assertArrayHasKey('threshold_used', $result->metadata);
        $this->assertSame(0.95, $result->metadata['similarity']);
    }
}
