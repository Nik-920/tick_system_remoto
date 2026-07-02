<?php

declare(strict_types=1);

namespace App\ViewModels\Tickets;

use App\Models\CommunityCommentEditLog;
use App\Models\CommunityModerationLog;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Support\LocalTime;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Presentation-only data derived from a loaded Ticket for the show view.
 *
 * All properties that were previously computed in an @php block at the top of
 * tickets/show.blade.php live here. The controller builds one instance and
 * passes it as $vm; partials access it through Blade's shared scope.
 *
 * Assumptions:
 *  - $ticket is already eager-loaded (reporter, assignee, assignedBy, location,
 *    category, media.uploadedBy, stateHistory.changedBy, embedding.*).
 *  - $availableTransitions, $isMaintenance, $isAvailableForClaim,
 *    $canEditOperational, $categories, $priorities are passed in from the
 *    controller so that auth/policy decisions remain there.
 */
final class TicketShowViewModel
{
    private const STATE_LABELS = [
        'open' => 'Abierto',
        'in_progress' => 'En progreso',
        'resolved' => 'Resuelto',
        'rejected' => 'Rechazado',
        'cancelled' => 'Cancelado',
    ];

    private const STATE_BADGE_CLASSES = [
        'open' => 'ts-badge ts-badge--open',
        'in_progress' => 'ts-badge ts-badge--in_progress',
        'resolved' => 'ts-badge ts-badge--resolved',
        'rejected' => 'ts-badge ts-badge--rejected',
        'cancelled' => 'ts-badge ts-badge--cancelled',
    ];

    private const PRIORITY_LABELS = [
        'low' => 'Baja',
        'medium' => 'Media',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    private const PRIORITY_BADGE_CLASSES = [
        'low' => 'ts-badge ts-badge--prio-low',
        'medium' => 'ts-badge ts-badge--prio-medium',
        'high' => 'ts-badge ts-badge--prio-high',
        'critical' => 'ts-badge ts-badge--prio-critical',
    ];

    /** @param  array<int, string>  $availableTransitions */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly array $availableTransitions,
        public readonly bool $isMaintenance,
        public readonly bool $isAvailableForClaim,
        public readonly bool $canEditOperational,
    ) {}

    // ─── Badge / label maps (exposed for partials that iterate transitions) ───

    /** @return array<string, string> */
    public function stateLabels(): array
    {
        return self::STATE_LABELS;
    }

    /** @return array<string, string> */
    public function stateBadgeClasses(): array
    {
        return self::STATE_BADGE_CLASSES;
    }

    // ─── Ticket-specific derived values ───────────────────────────────────────

    public function stateBadge(): string
    {
        return self::STATE_BADGE_CLASSES[$this->ticket->state] ?? 'ts-badge ts-badge--neutral';
    }

    public function stateLabel(): string
    {
        return self::STATE_LABELS[$this->ticket->state]
            ?? ucfirst(str_replace('_', ' ', (string) $this->ticket->state));
    }

    public function priorityBadge(): string
    {
        return self::PRIORITY_BADGE_CLASSES[$this->ticket->priority] ?? 'ts-badge ts-badge--neutral';
    }

    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->ticket->priority]
            ?? ucfirst((string) $this->ticket->priority);
    }

    /** @return array<string, string> */
    public function priorityLabels(): array
    {
        return self::PRIORITY_LABELS;
    }

    /** Visual code derived from the UUID (tickets has no `code` column). */
    public function ticketCode(): string
    {
        return 'INC-'.Str::upper(Str::substr((string) $this->ticket->id, 0, 8));
    }

    /** Format a date in Perú display time; returns null for null input. */
    public function fmtDate(?DateTimeInterface $value, string $format = 'd/m/Y H:i'): ?string
    {
        return LocalTime::format($value, $format);
    }

    // ─── State history derivatives ────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Collection<int, StateHistory> */
    public function stateHistory(): Collection
    {
        return $this->ticket->stateHistory;
    }

    public function lastHistory(): mixed
    {
        return $this->ticket->stateHistory->last();
    }

    public function lastComment(): ?string
    {
        $comment = $this->ticket->stateHistory->reverse()->first(
            fn ($entry) => trim((string) $entry->comment) !== ''
        )?->comment;

        return $comment !== null && $comment !== '' ? $comment : null;
    }

    /** Entry that closed the ticket (resolved / rejected / cancelled). */
    public function closureEntry(): mixed
    {
        return $this->ticket->stateHistory->reverse()->first(
            fn ($entry) => in_array($entry->to_state, ['resolved', 'rejected', 'cancelled'], true)
                && $entry->from_state !== $entry->to_state
        );
    }

    /**
     * Human-readable action type derived from a from→to transition.
     * state_history has no action_type column; this logic was previously in @php.
     */
    public function actionFor(?string $from, ?string $to): string
    {
        if ($from === null || $from === '') {
            return 'Creación';
        }

        if ($from === $to) {
            return 'Actualización técnica';
        }

        return match ($to) {
            'in_progress' => 'Inicio de atención',
            'resolved' => 'Resolución',
            'rejected' => 'Rechazo',
            'cancelled' => 'Cancelación',
            'open' => 'Reapertura / actualización',
            default => 'Cambio de estado',
        };
    }

    // ─── Media / evidence splits ──────────────────────────────────────────────

    /** Evidence files uploaded by the reporter. */
    public function reporterEvidence(): Collection
    {
        return $this->ticket->media->filter(
            fn ($media) => $media->uploaded_by !== null
                && (string) $media->uploaded_by === (string) $this->ticket->reporter_id
        )->values();
    }

    /** Evidence files uploaded by maintenance/admin (anyone who is not the reporter). */
    public function maintenanceEvidence(): Collection
    {
        return $this->ticket->media->reject(
            fn ($media) => $media->uploaded_by !== null
                && (string) $media->uploaded_by === (string) $this->ticket->reporter_id
        )->values();
    }

    public function reporterEvidenceCount(): int
    {
        return $this->reporterEvidence()->count();
    }

    public function maintenanceEvidenceCount(): int
    {
        return $this->maintenanceEvidence()->count();
    }

    public function hasReporterEvidence(): bool
    {
        return $this->reporterEvidenceCount() > 0;
    }

    public function hasMaintenanceEvidence(): bool
    {
        return $this->maintenanceEvidenceCount() > 0;
    }

    /** Base filename from a media file_url (for display in evidence tables). */
    public function fileNameFor(mixed $media): string
    {
        return basename(parse_url((string) $media->file_url, PHP_URL_PATH) ?: (string) $media->file_url);
    }

    // ─── Assignment presentation ──────────────────────────────────────────────

    public function assignee(): mixed
    {
        return $this->ticket->assignee;
    }

    public function assignedBy(): mixed
    {
        return $this->ticket->assignedBy;
    }

    public function assignmentLocked(): bool
    {
        return (bool) $this->ticket->assignment_locked;
    }

    public function assignmentTypeLabel(): string
    {
        return match ($this->ticket->assignment_source) {
            Ticket::ASSIGNMENT_SOURCE_SELF => 'Tomado por mantenimiento',
            Ticket::ASSIGNMENT_SOURCE_ADMIN => 'Asignación fija por administración',
            default => 'Sin tipo registrado',
        };
    }

    // ─── Auth-derived flags ───────────────────────────────────────────────────

    public function isAssignedToMe(): bool
    {
        $authId = auth()->id();

        return $authId !== null
            && $this->ticket->assigned_to !== null
            && (string) $this->ticket->assigned_to === (string) $authId;
    }

    public function isUnassigned(): bool
    {
        return $this->ticket->assigned_to === null;
    }

    public function isClosed(): bool
    {
        return in_array($this->ticket->state, ['resolved', 'rejected', 'cancelled'], true);
    }

    // ─── Recommended action text ──────────────────────────────────────────────

    public function recommendedAction(): string
    {
        return match (true) {
            $this->ticket->state === 'open' && $this->isUnassigned() && $this->isMaintenance => 'Toma el ticket para iniciar atención.',
            $this->ticket->state === 'open' && $this->isAssignedToMe() => 'Inicia la atención del ticket.',
            $this->ticket->state === 'in_progress' && $this->isAssignedToMe() => 'Continúa la atención o resuelve el ticket si corresponde.',
            $this->ticket->state === 'resolved' => 'Ticket resuelto. Revisa la información de cierre.',
            $this->ticket->state === 'rejected' => 'Ticket rechazado. Revisa el motivo en el historial.',
            $this->ticket->state === 'cancelled' => 'Ticket cancelado. Solo lectura.',
            default => 'Revisa el estado actual y actúa según corresponda.',
        };
    }

    // ─── Operational timers ───────────────────────────────────────────────────

    public function timeInProgress(): ?string
    {
        $inProgressSince = $this->ticket->stateHistory->first(
            fn ($entry) => $entry->to_state === 'in_progress'
        )?->created_at;

        if ($inProgressSince === null) {
            return null;
        }

        $closureEntry = $this->closureEntry();
        $progressEnd = $this->ticket->resolved_at ?? $closureEntry?->created_at;

        if ($this->isClosed() && $progressEnd !== null) {
            return $inProgressSince->diffForHumans($progressEnd, true, true, 2);
        }

        if ($this->ticket->state === 'in_progress') {
            return $inProgressSince->diffForHumans(null, true, true, 2);
        }

        return null;
    }

    // ─── Action availability flags ────────────────────────────────────────────

    public function canStart(): bool
    {
        return in_array('in_progress', $this->availableTransitions, true)
            && $this->ticket->state === 'open';
    }

    public function canContinue(): bool
    {
        return $this->ticket->state === 'in_progress' && $this->isAssignedToMe();
    }

    public function canResolve(): bool
    {
        return in_array('resolved', $this->availableTransitions, true);
    }

    // ─── Form helpers ─────────────────────────────────────────────────────────

    public function generateIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }

    // ─── Avatar helpers ───────────────────────────────────────────────────────

    public function initials(?string $name, int $length = 2, string $fallback = 'U'): string
    {
        $source = trim((string) ($name ?: $fallback));

        if ($source === '') {
            $source = $fallback;
        }

        return Str::upper(Str::substr($source, 0, max(1, $length)));
    }

    // ─── Duplicate banner ─────────────────────────────────────────────────────

    public function duplicateEmbedding(): ?TicketEmbedding
    {
        return $this->ticket->embedding;
    }

    public function duplicateMatchedTicket(): ?Ticket
    {
        return $this->duplicateEmbedding()?->matchedTicket;
    }

    public function duplicateReviewer(): ?User
    {
        return $this->duplicateEmbedding()?->reviewer;
    }

    public function duplicateReviewStatus(): ?string
    {
        return $this->duplicateEmbedding()?->review_status;
    }

    /**
     * True when the banner warning should be shown.
     *
     * Mirrors the original @php logic: effective_duplicate is true (respects
     * human review_status override) AND the matched ticket is still active.
     */
    public function shouldShowDuplicateWarning(): bool
    {
        $embedding = $this->duplicateEmbedding();

        if (! $embedding || ! $embedding->effective_duplicate) {
            return false;
        }

        $matched = $embedding->matchedTicket;

        return $matched !== null
            && in_array($matched->state, ['open', 'in_progress'], true);
    }

    // ─── Duplicate precheck notice (reporter confirmed "caso distinto") ───────

    public function precheckMatchedTicket(): ?Ticket
    {
        return $this->duplicateEmbedding()?->precheckMatchedTicket;
    }

    public function precheckReason(): ?string
    {
        return $this->duplicateEmbedding()?->precheck_reason;
    }

    public function precheckConfirmedAt(): ?DateTimeInterface
    {
        return $this->duplicateEmbedding()?->precheck_confirmed_at;
    }

    /**
     * True when there is a precheck candidate to surface AND the main AI
     * duplicate warning is not already covering it — the precheck notice is
     * a softer, read-only, secondary signal (see the 2026_07_01_000100
     * migration for why it never feeds is_duplicate/effective_duplicate).
     */
    public function shouldShowPrecheckNotice(): bool
    {
        $embedding = $this->duplicateEmbedding();

        return $embedding !== null
            && $embedding->hasPrecheckCandidate()
            && ! $this->shouldShowDuplicateWarning();
    }

    // ─── Community visibility ─────────────────────────────────────────────────

    public function isCommunityVisible(): bool
    {
        return (bool) $this->ticket->community_visible;
    }

    public function communityHiddenAtLabel(): ?string
    {
        return $this->fmtDate($this->ticket->community_hidden_at);
    }

    public function communityVisibilityReason(): ?string
    {
        $reason = $this->ticket->community_visibility_reason;

        return ($reason !== null && $reason !== '') ? $reason : null;
    }

    // ─── Community moderation history ────────────────────────────────────────

    /** @return Collection<int, CommunityModerationLog> */
    public function communityModerationLogs(): Collection
    {
        return $this->ticket->communityModerationLogs->take(5);
    }

    public function hasCommunityModerationLogs(): bool
    {
        return $this->ticket->communityModerationLogs->isNotEmpty();
    }

    public function communityLogActionLabel(string $action): string
    {
        return match ($action) {
            CommunityModerationLog::ACTION_HIDDEN => 'Ocultado',
            CommunityModerationLog::ACTION_RESTORED => 'Restaurado',
            default => ucfirst($action),
        };
    }

    // ─── Community comment edit history (audit, admin-only) ───────────────────

    /** @return Collection<int, CommunityCommentEditLog> */
    public function communityCommentEditLogs(): Collection
    {
        return $this->ticket->communityCommentEditLogs->take(5);
    }

    public function hasCommunityCommentEditLogs(): bool
    {
        return $this->ticket->communityCommentEditLogs->isNotEmpty();
    }

    // ─── Navigation ──────────────────────────────────────────────────────────

    public function backUrl(): string
    {
        return $this->isMaintenance
            ? route('tickets.assignments')
            : route('tickets.index');
    }

    public function backLabel(): string
    {
        return $this->isMaintenance ? 'Volver a mis asignaciones' : 'Volver a tickets';
    }
}
