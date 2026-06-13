<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * One operational AI-duplicate alert for the "Alertas IA" section.
 *
 * These rows are operational signals, never productivity metrics: they do not
 * feed the close rate nor penalise the technician. The explanation is read
 * from the persisted Strategy metadata (ticket_embeddings.strategy_results /
 * strategy_metadata) — never recalculated and never rendered as raw JSON.
 * Legacy records without Strategy metadata fall back to the legacy
 * similarity_score + matched ticket (isFallback = true).
 */
final class DuplicateAlertRow
{
    /**
     * @param  array<int, array{label: string, detail: string}>  $topReasons
     */
    public function __construct(
        public readonly string $ticketId,
        public readonly string $ticketIdShort,
        public readonly string $title,
        public readonly ?string $matchedTicketId,
        public readonly ?string $matchedTicketIdShort,
        public readonly ?string $matchedTicketTitle,
        public readonly ?float $similarity,
        public readonly ?int $strategyScore,
        public readonly ?string $reviewStatus,
        public readonly string $reviewStatusLabel,
        public readonly bool $suggestsRecurrence,
        public readonly array $topReasons,
        public readonly string $summary,
        public readonly string $recommendedAction,
        public readonly bool $isFallback,
    ) {}

    public function displayId(): string
    {
        return '#TIC-'.$this->ticketIdShort;
    }

    public function matchedDisplayId(): string
    {
        return $this->matchedTicketIdShort !== null ? '#TIC-'.$this->matchedTicketIdShort : '—';
    }
}
