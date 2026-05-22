<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\EmbeddingService;
use App\Services\Ai\HuggingFaceService;
use InvalidArgumentException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_generate_casts_values_to_float(): void
    {
        $service = new EmbeddingService(new class extends HuggingFaceService
        {
            public function embedding(string $text, ?string $model = null): array
            {
                return ['1', 2, 3.5];
            }
        });

        $this->assertSame([1.0, 2.0, 3.5], $service->generate('test'));
    }

    public function test_cosine_similarity_throws_for_mismatched_lengths(): void
    {
        $service = new EmbeddingService(new class extends HuggingFaceService
        {
            public function embedding(string $text, ?string $model = null): array
            {
                return [];
            }
        });

        $this->expectException(InvalidArgumentException::class);

        $service->cosineSimilarity([1.0], [1.0, 2.0]);
    }

    public function test_cosine_similarity_returns_zero_for_zero_norm(): void
    {
        $service = new EmbeddingService(new class extends HuggingFaceService
        {
            public function embedding(string $text, ?string $model = null): array
            {
                return [];
            }
        });

        $this->assertSame(0.0, $service->cosineSimilarity([0.0, 0.0], [1.0, 2.0]));
    }
}
