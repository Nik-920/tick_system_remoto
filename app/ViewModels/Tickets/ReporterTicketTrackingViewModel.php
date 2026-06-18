<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

/**
 * Read-only data carrier for the reporter "Ver seguimiento" tracking screen.
 *
 * Built once by ReporterTicketTrackingQuery from a single ticket that has
 * ALREADY been resolved inside the reporter's ownership boundary
 * (reporter_id = them), so the view never re-checks access. Every figure is
 * derived from real columns / state_history / media — no invented dates: a
 * missing milestone reads "Pendiente" / "Sin registro".
 */
final class ReporterTicketTrackingViewModel
{
    /**
     * @param  array<string, mixed>  $ticket  Shaped header fields (title, ref, status, priority, location, …).
     * @param  list<array{key: string, label: string, icon: string, tone: string, state: string, at: string}>  $steps
     * @param  list<array{icon: string, tone: string, title: string, at: string, actor: ?string, note: ?string, highlight: bool}>  $timeline
     * @param  array{rows: list<array{icon: string, label: string, value: string}>, technician: ?array{name: string, initials: string, role: string}}  $details
     * @param  array{count: int, items: list<array{label: string, url: ?string, is_image: bool}>}  $evidence
     * @param  array{matchedTitle: string, matchedState: string, similarity: string|null, summary: string, topReasons: array<int, mixed>, warnings: array<int, mixed>, isFallback: bool}|null  $duplicate  Reporter-safe duplicate payload. Null when no active duplicate.
     */
    public function __construct(
        public readonly array $ticket,
        public readonly array $steps,
        public readonly array $timeline,
        public readonly array $details,
        public readonly array $evidence,
        public readonly string $notice,
        public readonly ?array $duplicate = null,
    ) {}

    public function hasEvidence(): bool
    {
        return $this->evidence['count'] > 0;
    }

    public function hasDuplicateNotice(): bool
    {
        return $this->duplicate !== null;
    }
}
