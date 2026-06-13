<?php

namespace App\Services\Ai;

use App\Contracts\Ai\EmbeddingProvider;

class HuggingFaceEmbeddingAdapter implements EmbeddingProvider
{
    public function __construct(private HuggingFaceService $huggingFace) {}

    /**
     * @return array<int, float>
     */
    public function generate(string $text): array
    {
        return $this->huggingFace->embedding($text);
    }

    public function isAvailable(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.huggingface.enabled');
    }
}
