<?php

namespace Tests\Fakes;

use App\Contracts\Ai\EmbeddingProvider;

class FakeEmbeddingProvider implements EmbeddingProvider
{
    /** @var array<int, mixed> */
    private array $vector;

    private bool $available;

    /** @param array<int, mixed> $vector */
    public function __construct(array $vector = [0.1, 0.2, 0.3], bool $available = true)
    {
        $this->vector = $vector;
        $this->available = $available;
    }

    /** @return array<int, mixed> */
    public function generate(string $text): array
    {
        return $this->vector;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public static function unavailable(): self
    {
        return new self([], false);
    }
}
