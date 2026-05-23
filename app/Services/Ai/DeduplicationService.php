<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    public function observationThreshold(): float
    {
        $threshold = (float) config('ai.dedup.observation_threshold', 0.82);
        $strong = $this->similarityThreshold();

        return $threshold > $strong ? $strong : $threshold;
    }

    public function titleOverlapMinTokens(): int
    {
        return (int) config('ai.dedup.title_overlap_min_tokens', 1);
    }

    public function windowHours(): int
    {
        return (int) config('ai.dedup.window_hours', 24);
    }

    public function isDuplicate(float $similarityScore): bool
    {
        return $this->isStrongDuplicate($similarityScore, true);
    }

    public function isStrongDuplicate(float $similarityScore, bool $titleAligned = true): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $titleAligned && $similarityScore >= $this->similarityThreshold();
    }

    public function isObservationCandidate(float $similarityScore, bool $titleAligned = true): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $titleAligned
            && $similarityScore >= $this->observationThreshold()
            && $similarityScore < $this->similarityThreshold();
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

    public function titleOverlapSatisfied(?string $sourceTitle, ?string $candidateTitle): bool
    {
        $minOverlap = $this->titleOverlapMinTokens();
        if ($minOverlap <= 0) {
            return true;
        }

        $sourceTokens = $this->titleTokens($sourceTitle);
        $candidateTokens = $this->titleTokens($candidateTitle);

        if ($sourceTokens === [] || $candidateTokens === []) {
            return true;
        }

        $overlap = array_intersect($sourceTokens, $candidateTokens);

        return count($overlap) >= $minOverlap;
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

    /**
     * @return array<int, string>
     */
    private function titleTokens(?string $title): array
    {
        $text = Str::lower(trim((string) $title));
        if ($text === '') {
            return [];
        }

        if (! preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches)) {
            return [];
        }

        $stopwords = $this->titleStopwords();
        $tokens = [];

        foreach ($matches[0] as $token) {
            if (preg_match('/\d/', $token)) {
                continue;
            }

            if (strlen($token) < 3) {
                continue;
            }

            if (in_array($token, $stopwords, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, string>
     */
    private function titleStopwords(): array
    {
        return [
            'de', 'la', 'el', 'en', 'y', 'a', 'un', 'una', 'para', 'del', 'los', 'las',
            'con', 'sin', 'no', 'problema', 'incidencia', 'ticket', 'sala', 'aula',
            'laboratorio', 'room', 'building', 'edificio',
        ];
    }
}
