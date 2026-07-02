<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Concerns;

use App\Models\Ticket;

/**
 * Chart-shaping helpers shared by the V2 dashboard presenters (admin and
 * maintenance), so the donut/bar math and the ticket reference format live
 * in ONE place instead of being copy-pasted per profile.
 */
trait BuildsDashboardCharts
{
    /**
     * Turns an ordered list of bands (key + display fields) plus a
     * count-by-key distribution into cumulative donut segments.
     *
     * @param  list<array<string, mixed>>  $bands
     * @param  array<string, int>  $dist
     * @return array{total: int, segments: list<array<string, mixed>>}
     */
    private function buildDonutSegments(array $bands, array $dist, int $total): array
    {
        $cursor = 0.0;
        $segments = [];
        foreach ($bands as $band) {
            $count = $dist[$band['key']] ?? 0;
            $percent = $total > 0 ? round($count / $total * 100, 1) : 0.0;
            $start = $cursor;
            $cursor += $percent;
            $segments[] = [
                ...$band,
                'count' => $count,
                'percent' => $percent,
                'start' => round($start, 1),
                'end' => round($cursor, 1),
            ];
        }

        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * Turns an ordered list of bands (key/label/tone) plus a count-by-key
     * distribution into bar items with the shared "peak" (max, floor 1).
     *
     * @param  list<array{key: string, label: string, tone: string}>  $bands
     * @param  array<string, int>  $dist
     * @return array{peak: int, items: list<array{label: string, count: int, tone: string}>}
     */
    private function buildBarItems(array $bands, array $dist): array
    {
        $items = array_map(static fn (array $b): array => [
            'label' => $b['label'],
            'count' => $dist[$b['key']] ?? 0,
            'tone' => $b['tone'],
        ], $bands);

        $peak = max(1, ...array_map(static fn (array $i): int => $i['count'], $items));

        return ['peak' => $peak, 'items' => $items];
    }

    private function reference(Ticket $ticket): string
    {
        return '#'.strtoupper(substr((string) $ticket->id, 0, 8));
    }
}
