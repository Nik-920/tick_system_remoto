<?php

namespace App\Services\Ai;

use InvalidArgumentException;

class EmbeddingService
{
    public function __construct(private HuggingFaceService $huggingFace)
    {
    }

    /**
     * @return array<int, float>
     */
    public function generate(string $text): array
    {
        return $this->toFloatVector($this->huggingFace->embedding($text));
    }

    /**
     * @param array<int, float|int|string> $a
     * @param array<int, float|int|string> $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $a = $this->toFloatVector($a);
        $b = $this->toFloatVector($b);

        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            throw new InvalidArgumentException('Embedding vectors must have the same non-zero length.');
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @param array<int, float|int|string> $vector
     * @return array<int, float>
     */
    private function toFloatVector(array $vector): array
    {
        return array_map(static fn ($value) => (float) $value, $vector);
    }
}
