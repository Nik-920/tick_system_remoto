<?php

declare(strict_types=1);

namespace App\Support\Tickets;

use App\Models\Ticket;
use App\Models\TicketEmbedding;

/**
 * Turns the persisted Strategy decision (ticket_embeddings.strategy_results /
 * strategy_metadata) into a human-friendly, non-technical explanation for the
 * ticket detail screen: "why did the AI flag this as a possible duplicate?".
 *
 * Pure and side-effect-free: it only reads already-loaded relations
 * (embedding, embedding.matchedTicket, location, category) so it never causes
 * N+1 queries. It NEVER renders raw JSON and it NEVER invents reasons — if no
 * Strategy metadata was persisted (e.g. an old record), it returns a clear
 * fallback built from the legacy similarity_score + matched ticket.
 *
 * @phpstan-type ReasonRow array{label: string, detail: string, points: int, tone: string, icon: string}
 * @phpstan-type Explanation array{
 *     visible: bool,
 *     isFallback: bool,
 *     score: int|null,
 *     similarity: float|null,
 *     matchedTicket: array{id: string, title: string, state: string, url: string}|null,
 *     headline: string,
 *     summary: string,
 *     topReasons: array<int, ReasonRow>,
 *     warnings: array<int, ReasonRow>,
 *     technicalDetails: array<int, array{label: string, strategy: string, points: int, reason: string}>,
 * }
 */
final class DuplicateExplanationPresenter
{
    /** How many primary reasons to surface before the collapsible. */
    private const MAX_TOP_REASONS = 3;

    /**
     * Human label + icon for each Strategy identifier. Anything not listed
     * falls back to a generic label so the UI never shows a raw class name.
     *
     * @var array<string, array{icon: string, label: string}>
     */
    private const LABELS = [
        'embedding_similarity' => ['icon' => '🧠', 'label' => 'Alta similitud semántica'],
        'title_overlap' => ['icon' => '📝', 'label' => 'Coincidencia de título'],
        'same_location' => ['icon' => '📍', 'label' => 'Misma ubicación'],
        'same_category' => ['icon' => '🏷️', 'label' => 'Misma categoría'],
        'time_window' => ['icon' => '⏱️', 'label' => 'Reportado en una ventana cercana'],
        'contextual_duplicate' => ['icon' => '🎯', 'label' => 'Mismo lugar y tipo recientemente'],
        'candidate_state' => ['icon' => '🔓', 'label' => 'El ticket similar sigue activo'],
        'active_assignment' => ['icon' => '👷', 'label' => 'El ticket similar ya está en atención'],
        'recurrence_guard' => ['icon' => '🔁', 'label' => 'Posible recurrencia'],
        'generic_text_penalty' => ['icon' => '⚠️', 'label' => 'Texto poco específico'],
        'vision_evidence' => ['icon' => '🖼️', 'label' => 'Evidencia visual'],
        'historical_recurrence' => ['icon' => '📚', 'label' => 'Recurrencia histórica'],
        'location_incident_pattern' => ['icon' => '🗺️', 'label' => 'Patrón de incidencias en la ubicación'],
    ];

    /**
     * Build the explanation payload for a ticket detail view.
     *
     * @return Explanation
     */
    public static function present(?Ticket $ticket): array
    {
        $embedding = $ticket?->embedding;
        $matched = $embedding?->matchedTicket;

        $visible = $embedding instanceof TicketEmbedding
            && $matched instanceof Ticket
            && $embedding->effective_duplicate;

        if (! $visible) {
            return self::hidden();
        }

        /** @var TicketEmbedding $embedding */
        /** @var Ticket $matched */
        $matchedTicket = [
            'id' => (string) $matched->getKey(),
            'title' => (string) ($matched->title ?? 'Ticket relacionado'),
            'state' => (string) ($matched->state ?? ''),
            'url' => route('tickets.show', $matched),
        ];

        $similarity = $embedding->similarity_score;
        $rawResults = self::sanitiseResults($embedding->strategy_results);

        // No Strategy breakdown persisted (legacy record) → graceful fallback.
        if ($rawResults === []) {
            return [
                'visible' => true,
                'isFallback' => true,
                'score' => null,
                'similarity' => $similarity,
                'matchedTicket' => $matchedTicket,
                'headline' => 'La IA detectó alta similitud con otro ticket.',
                'summary' => 'La IA detectó alta similitud con otro ticket. No hay un desglose detallado disponible para este registro.',
                'topReasons' => [],
                'warnings' => [],
                'technicalDetails' => [],
            ];
        }

        $positive = [];
        $warnings = [];
        $technical = [];

        foreach ($rawResults as $result) {
            $row = self::humanise($result, $ticket);
            $technical[] = [
                'label' => $row['label'],
                'strategy' => $result['strategy'],
                'points' => $row['points'],
                'reason' => (string) ($result['reason'] ?? ''),
            ];

            if ($row['tone'] === 'warning') {
                $warnings[] = $row;
            } elseif ($row['points'] > 0) {
                $positive[] = $row;
            }
        }

        // Highest-impact reasons first.
        usort($positive, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);
        $topReasons = array_slice($positive, 0, self::MAX_TOP_REASONS);

        $suggestsRecurrence = (bool) $embedding->strategy_suggests_recurrence;

        return [
            'visible' => true,
            'isFallback' => false,
            'score' => $embedding->strategy_score,
            'similarity' => $similarity,
            'matchedTicket' => $matchedTicket,
            'headline' => $suggestsRecurrence
                ? 'La IA detecta una posible recurrencia.'
                : 'La IA encontró un ticket muy parecido.',
            'summary' => self::summary($topReasons, $suggestsRecurrence),
            'topReasons' => array_values($topReasons),
            'warnings' => array_values($warnings),
            'technicalDetails' => $technical,
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Normalise the persisted results into a predictable shape, dropping any
     * corrupt/incomplete entries so the presenter never crashes on bad data.
     *
     * @return array<int, array{strategy: string, points: int, reason: string, metadata: array<string, mixed>, blocksDuplicate: bool, suggestsRecurrence: bool}>
     */
    private static function sanitiseResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $clean = [];

        foreach ($results as $result) {
            if (! is_array($result) || ! isset($result['strategy']) || ! is_string($result['strategy'])) {
                continue;
            }

            $clean[] = [
                'strategy' => $result['strategy'],
                'points' => (int) ($result['points'] ?? 0),
                'reason' => is_string($result['reason'] ?? null) ? $result['reason'] : '',
                'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
                'blocksDuplicate' => (bool) ($result['blocksDuplicate'] ?? false),
                'suggestsRecurrence' => (bool) ($result['suggestsRecurrence'] ?? false),
            ];
        }

        return $clean;
    }

    /**
     * Map one sanitised result to a presentation row (label, detail, tone, icon).
     *
     * @param  array{strategy: string, points: int, reason: string, metadata: array<string, mixed>, blocksDuplicate: bool, suggestsRecurrence: bool}  $result
     * @return ReasonRow
     */
    private static function humanise(array $result, ?Ticket $ticket): array
    {
        $strategy = $result['strategy'];
        $meta = self::LABELS[$strategy] ?? ['icon' => '•', 'label' => self::titleise($strategy)];

        $isWarning = $result['points'] < 0
            || $result['blocksDuplicate']
            || $result['suggestsRecurrence'];

        return [
            'label' => $meta['label'],
            'detail' => self::detailFor($strategy, $result['metadata'], $ticket),
            'points' => $result['points'],
            'tone' => $isWarning ? 'warning' : 'positive',
            'icon' => $meta['icon'],
        ];
    }

    /**
     * Non-technical, per-strategy detail sentence. Uses already-loaded ticket
     * context (location/category names) when available; never queries.
     *
     * @param  array<string, mixed>  $metadata
     */
    private static function detailFor(string $strategy, array $metadata, ?Ticket $ticket): string
    {
        $locationName = $ticket?->location?->name;
        $categoryName = $ticket?->category?->name;

        return match ($strategy) {
            'embedding_similarity' => isset($metadata['similarity'])
                ? 'Las descripciones de ambos tickets son muy parecidas (similitud '.self::formatSimilarity($metadata['similarity']).').'
                : 'Las descripciones de ambos tickets son muy parecidas.',
            'title_overlap' => 'Los títulos de ambos tickets comparten varias palabras clave.',
            'same_location' => $locationName
                ? 'Ambos tickets pertenecen a '.$locationName.'.'
                : 'Ambos tickets están en la misma ubicación.',
            'same_category' => $categoryName
                ? 'Ambos tickets son de la categoría '.$categoryName.'.'
                : 'Ambos tickets son de la misma categoría.',
            'time_window' => 'El ticket similar se reportó dentro de una ventana de tiempo cercana.',
            'contextual_duplicate' => 'Coinciden la ubicación y la categoría en menos de 24 horas.',
            'candidate_state' => self::candidateStateDetail($metadata),
            'active_assignment' => 'El ticket similar ya tiene a alguien trabajando en él.',
            'recurrence_guard' => 'El ticket similar es antiguo; podría tratarse de un incidente recurrente más que de un duplicado.',
            'generic_text_penalty' => 'El título es muy genérico, por lo que la coincidencia podría ser casual.',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function candidateStateDetail(array $metadata): string
    {
        $state = is_string($metadata['candidate_state'] ?? null) ? $metadata['candidate_state'] : '';

        return match ($state) {
            Ticket::STATE_OPEN => 'El ticket similar sigue abierto y sin resolver.',
            Ticket::STATE_IN_PROGRESS => 'El ticket similar está actualmente en progreso.',
            Ticket::STATE_RESOLVED => 'El ticket similar se resolvió recientemente.',
            Ticket::STATE_CANCELLED => 'El ticket similar fue cancelado, así que probablemente no sea el mismo caso.',
            Ticket::STATE_REJECTED => 'El ticket similar fue rechazado, así que probablemente no sea el mismo caso.',
            default => 'El estado del ticket similar influye en la comparación.',
        };
    }

    /**
     * Build a one-line natural-language summary from the top reasons.
     *
     * @param  array<int, ReasonRow>  $topReasons
     */
    private static function summary(array $topReasons, bool $suggestsRecurrence): string
    {
        if ($topReasons === []) {
            return $suggestsRecurrence
                ? 'La IA cree que este reporte podría repetir un incidente anterior.'
                : 'La IA encontró señales de que este ticket coincide con otro reporte.';
        }

        $labels = array_map(
            static fn (array $reason): string => mb_strtolower($reason['label']),
            $topReasons,
        );

        $joined = self::joinSpanish($labels);

        return 'Este ticket coincide con otro reporte por '.$joined.'.';
    }

    /**
     * Join phrases in natural Spanish: "a", "a y b", "a, b y c".
     *
     * @param  array<int, string>  $items
     */
    private static function joinSpanish(array $items): string
    {
        $items = array_values($items);
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' y '.$last;
    }

    private static function formatSimilarity(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2) : (string) $value;
    }

    /** Convert a snake_case strategy id into a readable fallback label. */
    private static function titleise(string $strategy): string
    {
        return ucfirst(str_replace('_', ' ', $strategy));
    }

    /**
     * @return Explanation
     */
    private static function hidden(): array
    {
        return [
            'visible' => false,
            'isFallback' => false,
            'score' => null,
            'similarity' => null,
            'matchedTicket' => null,
            'headline' => '',
            'summary' => '',
            'topReasons' => [],
            'warnings' => [],
            'technicalDetails' => [],
        ];
    }
}
