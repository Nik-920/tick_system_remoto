<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\EmbeddingService;
use InvalidArgumentException;
use Tests\Fakes\FakeEmbeddingProvider;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_generate_casts_values_to_float(): void
    {
        $service = new EmbeddingService(new FakeEmbeddingProvider(['1', 2, 3.5]));

        $this->assertSame([1.0, 2.0, 3.5], $service->generate('test'));
    }

    public function test_cosine_similarity_throws_for_mismatched_lengths(): void
    {
        $service = new EmbeddingService(new FakeEmbeddingProvider);

        $this->expectException(InvalidArgumentException::class);

        $service->cosineSimilarity([1.0], [1.0, 2.0]);
    }

    public function test_cosine_similarity_returns_zero_for_zero_norm(): void
    {
        $service = new EmbeddingService(new FakeEmbeddingProvider);

        $this->assertSame(0.0, $service->cosineSimilarity([0.0, 0.0], [1.0, 2.0]));
    }

    public function test_is_available_proxies_to_provider(): void
    {
        $this->assertTrue((new EmbeddingService(new FakeEmbeddingProvider))->isAvailable());
        $this->assertFalse((new EmbeddingService(FakeEmbeddingProvider::unavailable()))->isAvailable());
    }
}
