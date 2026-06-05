<?php

namespace App\Contracts\Ai;

interface EmbeddingProvider
{
    /**
     * @return array<int, float>
     *
     * @throws \RuntimeException
     */
    public function generate(string $text): array;

    public function isAvailable(): bool;
}
