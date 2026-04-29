<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;

class DeduplicationService
{
    public function __construct(private EmbeddingService $embeddings) {}

    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.dedup.enabled');
    }

    public function similarityThreshold(): float
    {
        return (float) config('ai.dedup.similarity_threshold', 0.70);
    }

    public function windowHours(): int
    {
        return (int) config('ai.dedup.window_hours', 24);
    }

    public function isDuplicate(float $similarityScore): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $similarityScore >= $this->similarityThreshold();
    }

    public function withinWindow(Carbon $createdAt): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo(Carbon::now()->subHours($this->windowHours()));
    }

    /**
     * @param  array<int, float|int|string>  $sourceEmbedding
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    public function findBestMatch(array $sourceEmbedding, array $candidates): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $best = null;
        $bestScore = -1.0;

        foreach ($candidates as $candidate) {
            if (! isset($candidate['embedding']) || ! is_array($candidate['embedding'])) {
                continue;
            }

            $score = $this->embeddings->cosineSimilarity($sourceEmbedding, $candidate['embedding']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null) {
            return null;
        }

        $best['similarity'] = $bestScore;
        $best['is_duplicate'] = $this->isDuplicate($bestScore);

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    public function findBestMatchForText(string $text, array $candidates): ?array
    {
        $embedding = $this->embeddings->generate($text);

        return $this->findBestMatch($embedding, $candidates);
    }
}
