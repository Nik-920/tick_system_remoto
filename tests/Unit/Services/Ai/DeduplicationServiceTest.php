<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\DeduplicationService;
use App\Services\Ai\EmbeddingService;
use Tests\Fakes\FakeEmbeddingProvider;
use Tests\TestCase;

class DeduplicationServiceTest extends TestCase
{
    public function test_find_best_match_returns_null_when_disabled(): void
    {
        config([
            'ai.enabled' => false,
            'ai.dedup.enabled' => true,
        ]);

        $service = new DeduplicationService($this->makeEmbeddingService([1.0, 0.0]));
        $result = $service->findBestMatch([1.0, 0.0], [
            ['ticket' => 'a', 'embedding' => [1.0, 0.0]],
        ]);

        $this->assertNull($result);
    }

    public function test_find_best_match_selects_highest_similarity(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.8,
        ]);

        $service = new DeduplicationService($this->makeEmbeddingService([1.0, 0.0]));

        $result = $service->findBestMatch([1.0, 0.0], [
            ['ticket' => 'first', 'embedding' => [0.0, 1.0]],
            ['ticket' => 'best', 'embedding' => [1.0, 0.0]],
        ]);

        $this->assertIsArray($result);
        $this->assertSame('best', $result['ticket']);
        $this->assertSame(1.0, $result['similarity']);
        $this->assertTrue($result['is_duplicate']);
    }

    public function test_find_best_match_for_text_generates_embedding(): void
    {
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.dedup.similarity_threshold' => 0.5,
        ]);

        $service = new DeduplicationService($this->makeEmbeddingService([0.6, 0.8]));

        $result = $service->findBestMatchForText('Sample', [
            ['ticket' => 'ticket-a', 'embedding' => [0.6, 0.8]],
        ]);

        $this->assertIsArray($result);
        $this->assertSame('ticket-a', $result['ticket']);
        $this->assertSame(1.0, $result['similarity']);
        $this->assertTrue($result['is_duplicate']);
    }

    private function makeEmbeddingService(array $vector): EmbeddingService
    {
        return new EmbeddingService(new FakeEmbeddingProvider($vector));
    }
}
