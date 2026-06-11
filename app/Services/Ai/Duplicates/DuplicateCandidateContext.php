<?php

namespace App\Services\Ai\Duplicates;

use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Value object that carries all available context for evaluating
 * one duplicate candidate pair (ticket → candidate).
 *
 * Strategies read from this context; they never mutate it.
 */
final class DuplicateCandidateContext
{
    /** Similarity from cosine distance of embedding vectors (null if unavailable). */
    public readonly ?float $embeddingSimilarity;

    /** Age of candidate in hours relative to $now (calculated once, cached). */
    public readonly ?int $candidateAgeHours;

    /** Whether ticket and candidate share the same location_id. */
    public readonly ?bool $sameLocation;

    /** Whether ticket and candidate share the same category_id. */
    public readonly ?bool $sameCategory;

    /** Normalised tokens from ticket title (lowercased, stopwords removed). */
    public readonly array $ticketTokens;

    /** Normalised tokens from candidate title (lowercased, stopwords removed). */
    public readonly array $candidateTokens;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Ticket $candidate,
        public readonly CarbonInterface $now,
        ?float $embeddingSimilarity = null,
    ) {
        $this->embeddingSimilarity = $embeddingSimilarity;

        // Compute candidate age
        $createdAt = $candidate->created_at;
        $this->candidateAgeHours = $createdAt instanceof Carbon
            ? (int) $createdAt->diffInHours($now)
            : null;

        // Location / category flags (use getAttribute to get nullable values correctly)
        $locA = $ticket->getAttribute('location_id');
        $locB = $candidate->getAttribute('location_id');
        $this->sameLocation = ($locA !== null && $locB !== null) ? ($locA === $locB) : null;

        $catA = $ticket->getAttribute('category_id');
        $catB = $candidate->getAttribute('category_id');
        $this->sameCategory = ($catA !== null && $catB !== null) ? ($catA === $catB) : null;

        // Pre-tokenise titles for strategies that need it
        $this->ticketTokens = self::tokenise($ticket->getAttribute('title') ?? '');
        $this->candidateTokens = self::tokenise($candidate->getAttribute('title') ?? '');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Tokenise a title string: lowercase, remove punctuation, filter stopwords
     * and very short words. Pure function, no side effects.
     *
     * @return array<int, string>
     */
    public static function tokenise(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }

        if (! preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches)) {
            return [];
        }

        $stopwords = self::stopwords();
        $tokens = [];

        foreach ($matches[0] as $token) {
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
    private static function stopwords(): array
    {
        return [
            'de', 'la', 'el', 'en', 'y', 'a', 'un', 'una', 'para', 'del', 'los', 'las',
            'con', 'sin', 'no', 'incidencia', 'ticket', 'sala', 'aula',
            'laboratorio', 'room', 'building', 'edificio',
        ];
    }
}
