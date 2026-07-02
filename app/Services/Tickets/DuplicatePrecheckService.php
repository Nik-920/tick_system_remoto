<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class DuplicatePrecheckService
{
    private const WINDOW_HOURS = 48;

    private const OVERLAP_FLAG = 0.5;

    private const OVERLAP_SOFT = 0.3;

    /** @var string[] */
    private const ACTIVE_STATES = [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS];

    /** @var string[] */
    private const STOP_WORDS = [
        'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'en', 'y', 'a', 'no',
        'se', 'que', 'con', 'por', 'al', 'es', 'su', 'le', 'lo', 'si', 'o', 'e',
        'ni', 'but', 'the', 'and', 'of', 'to', 'is', 'in', 'it', 'for',
    ];

    /**
     * Cheap deterministic precheck — no embeddings, no AI calls.
     *
     * Returns a reporter-safe summary when a recently-created active ticket in the
     * same location/category has enough title overlap with the submitted payload.
     * Returns null if no suspicious match is found.
     *
     * SECURITY: this payload is flashed into the session and rendered back to
     * the browser (see TicketController::store()), so it intentionally never
     * contains the candidate ticket's id — a reporter must not be able to
     * discover the id of a ticket they are not authorised to view. Server-side
     * code that needs the actual candidate (e.g. to persist which ticket was
     * flagged once the reporter confirms "caso distinto") must call
     * findMatch() instead and must never forward its result to the client.
     *
     * @param  array<string, mixed>  $payload
     * @return array{matchedTitle: string, matchedState: string, reason: string}|null
     */
    public function check(array $payload): ?array
    {
        $match = $this->findMatch($payload);

        if ($match === null) {
            return null;
        }

        return $this->buildResult(
            (string) $match['ticket']->title,
            (string) $match['ticket']->state,
            $match['reason']
        );
    }

    /**
     * Same detection logic as check(), but returns the actual candidate Ticket
     * (including its id) for SERVER-SIDE-ONLY use. Never expose this return
     * value to the client (no session flash, no JSON, no hidden form field) —
     * see the SECURITY note on check().
     *
     * @param  array<string, mixed>  $payload
     * @return array{ticket: Ticket, reason: string}|null
     */
    public function findMatch(array $payload): ?array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $locationId = (string) ($payload['location_id'] ?? '');
        $categoryId = (string) ($payload['category_id'] ?? '');

        if ($title === '' || $locationId === '' || $categoryId === '') {
            return null;
        }

        $candidates = $this->recentCandidates($locationId, $categoryId);

        return $this->bestOverlapMatch($title, $candidates);
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function recentCandidates(string $locationId, string $categoryId): Collection
    {
        return Ticket::query()
            ->whereIn('state', self::ACTIVE_STATES)
            ->where('location_id', $locationId)
            ->where('category_id', $categoryId)
            ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->select(['id', 'title', 'state'])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Flag-level overlap wins immediately (first match, in query order). If no
     * candidate reaches the flag threshold, the earliest soft-overlap match
     * (if any) is returned instead.
     *
     * @param  Collection<int, Ticket>  $candidates
     * @return array{ticket: Ticket, reason: string}|null
     */
    private function bestOverlapMatch(string $title, Collection $candidates): ?array
    {
        $softMatch = null;

        foreach ($candidates as $candidate) {
            $overlap = $this->jaccardOverlap($title, (string) $candidate->title);

            if ($overlap >= self::OVERLAP_FLAG) {
                return ['ticket' => $candidate, 'reason' => 'Misma ubicación, categoría y título similar'];
            }

            if ($softMatch === null && $overlap >= self::OVERLAP_SOFT) {
                $softMatch = ['ticket' => $candidate, 'reason' => 'Misma ubicación y categoría con descripción relacionada'];
            }
        }

        return $softMatch;
    }

    /**
     * @return array{matchedTitle: string, matchedState: string, reason: string}
     */
    private function buildResult(string $rawTitle, string $rawState, string $reason): array
    {
        return [
            'matchedTitle' => Str::limit($rawTitle, 80),
            'matchedState' => $this->stateLabel($rawState),
            'reason' => $reason,
        ];
    }

    private function jaccardOverlap(string $a, string $b): float
    {
        $tokensA = $this->tokenize($a);
        $tokensB = $this->tokenize($b);

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        return count($intersection) / count($union);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $tokens = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = is_array($tokens) ? $tokens : [];

        return array_values(array_diff($tokens, self::STOP_WORDS));
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Abierto',
            Ticket::STATE_IN_PROGRESS => 'En progreso',
            default => 'Activo',
        };
    }
}
