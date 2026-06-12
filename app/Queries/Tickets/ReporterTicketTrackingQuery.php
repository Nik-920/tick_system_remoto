<?php

declare(strict_types=1);

namespace App\Queries\Tickets;

use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\ViewModels\Tickets\ReporterTicketTrackingViewModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds the reporter "Ver seguimiento" tracking screen for ONE ticket.
 *
 * Security contract: the ticket is resolved INSIDE the reporter's ownership
 * boundary (reporter_id = them) via findOrFail — a ticket filed by anyone else,
 * or a non-existent / malformed id, is a 404. No request data widens this.
 *
 * Everything shown is derived from real columns, state_history and media; no
 * dates are invented (a missing milestone reads "Pendiente"/"Sin registro").
 * Read-only: nothing here mutates a ticket and NO maintenance action is exposed.
 *
 * Portability: durations/dates are formatted in PHP, so the same code runs on
 * SQLite (tests) and PostgreSQL.
 */
final class ReporterTicketTrackingQuery
{
    /** Category name → Lucide icon (same vocabulary as the other boards). */
    private const CATEGORY_ICONS = [
        'hardware' => 'monitor', 'software' => 'cpu', 'seguridad' => 'shield-alert',
        'mobiliario' => 'armchair', 'equipos' => 'projector', 'conectividad' => 'cable',
        'redes' => 'cable', 'red' => 'cable', 'electricidad' => 'zap', 'servicios' => 'droplet',
    ];

    /** Datetime format shown to the reporter across the tracking screen. */
    private const DISPLAY_DATETIME_FORMAT = 'd/m/Y · H:i';

    public function __construct(
        private readonly Ticket $ticket,
    ) {}

    /**
     * Resolve a ticket owned by the reporter and build the tracking view model.
     * Throws a 404 for a malformed id or any ticket the reporter does not own.
     */
    public static function for(User $user, string $ticketId): ReporterTicketTrackingViewModel
    {
        if (! Str::isUuid($ticketId)) {
            throw new NotFoundHttpException('Ticket no encontrado.');
        }

        /** @var Ticket $ticket */
        $ticket = Ticket::query()
            ->where('reporter_id', (string) $user->id)
            ->with([
                'location',
                'category',
                'reporter',
                'assignee',
                'stateHistory' => fn ($q) => $q->with('changedBy')->oldest('created_at'),
                'media' => fn ($q) => $q->latest('created_at'),
            ])
            ->findOrFail($ticketId);

        return (new self($ticket))->build();
    }

    public function build(): ReporterTicketTrackingViewModel
    {
        return new ReporterTicketTrackingViewModel(
            ticket: $this->header(),
            steps: $this->steps(),
            timeline: $this->timeline(),
            details: $this->details(),
            evidence: $this->evidence(),
            notice: 'Recibirás una notificación cuando el estado de tu ticket cambie.',
        );
    }

    // ── Header ───────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function header(): array
    {
        $state = (string) $this->ticket->state;

        return [
            'id' => (string) $this->ticket->id,
            'ref' => $this->reference((string) $this->ticket->id),
            'title' => (string) $this->ticket->title,
            'description' => trim((string) $this->ticket->description),
            'location' => $this->ticket->location?->name ?? 'Sin ubicación',
            'category' => $this->ticket->category?->name ?? 'Sin categoría',
            'type' => (string) ($this->ticket->location?->room_code ?? ''),
            'icon' => $this->iconFor($this->ticket->category?->name),
            'status' => $state,
            'status_label' => $this->stateLabel($state),
            'status_tone' => $this->stateTone($state),
            'priority' => $this->priorityTone((string) $this->ticket->priority),
            'priority_label' => $this->priorityLabel((string) $this->ticket->priority),
            'updated' => $this->ticket->updated_at?->diffForHumans() ?? '—',
        ];
    }

    // ── Stepper (real milestones, no invented dates) ─────────────

    /**
     * @return list<array{key: string, label: string, icon: string, tone: string, state: string, at: string}>
     */
    private function steps(): array
    {
        $state = (string) $this->ticket->state;
        $history = $this->ticket->stateHistory;

        $createdAt = $this->ticket->created_at;

        // Cancelled is a reporter withdrawal that happens BEFORE any review or
        // assignment, so its stepper is intentionally short and terminal:
        // Reportado → Cancelado (no assigned / in_progress / resolved milestones).
        if ($state === Ticket::STATE_CANCELLED) {
            $cancelledAt = $history->where('to_state', Ticket::STATE_CANCELLED)->last()?->created_at;

            return [
                $this->step('created', 'Reportado', 'inbox', 'done', $createdAt, null),
                $this->finalStep('cancelled', 'Cancelado', 'ban', 'cancelled', 'neutral', $cancelledAt),
            ];
        }

        $firstHistoryAt = $history->first()?->created_at;
        $firstInProgressAt = $history->firstWhere('to_state', Ticket::STATE_IN_PROGRESS)?->created_at;
        $rejectedAt = $history->where('to_state', Ticket::STATE_REJECTED)->last()?->created_at;
        $assignedAt = $this->ticket->assigned_at;
        $resolvedAt = $this->ticket->resolved_at;

        // The step that represents the ticket's CURRENT live stage.
        $currentKey = match ($state) {
            Ticket::STATE_OPEN => 'in_review',
            Ticket::STATE_IN_PROGRESS => 'in_progress',
            default => null, // resolved / rejected are terminal
        };

        $steps = [];
        $steps[] = $this->step('created', 'Reportado', 'inbox', 'done', $createdAt, $currentKey);
        $steps[] = $this->step('in_review', 'Revisado', 'search', $firstHistoryAt !== null ? 'done' : 'todo', $firstHistoryAt, $currentKey);
        $steps[] = $this->step('assigned', 'Asignado', 'user-check', $assignedAt !== null ? 'done' : 'todo', $assignedAt, $currentKey);
        $steps[] = $this->step('in_progress', 'En progreso', 'wrench', $firstInProgressAt !== null ? 'done' : 'todo', $firstInProgressAt, $currentKey);

        if ($state === Ticket::STATE_RESOLVED) {
            $steps[] = $this->finalStep('resolved', 'Resuelto', 'circle-check', 'done', 'success', $resolvedAt);
        } elseif ($state === Ticket::STATE_REJECTED) {
            $steps[] = $this->finalStep('rejected', 'Rechazado', 'x', 'rejected', 'high', $rejectedAt);
        } else {
            $steps[] = $this->finalStep('resolved', 'Resuelto', 'circle-check', 'todo', 'neutral', null);
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, icon: string, tone: string, state: string, at: string}
     */
    private function step(string $key, string $label, string $icon, string $baseState, ?CarbonInterface $at, ?string $currentKey): array
    {
        // The current live stage wins over a "done"/"todo" computed from signals.
        $state = $key === $currentKey ? 'current' : $baseState;

        $tone = match ($state) {
            'done' => 'success',
            'current' => 'primary',
            'rejected' => 'high',
            default => 'neutral',
        };

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'tone' => $tone,
            'state' => $state,
            'at' => $this->stampFor($at, $state),
        ];
    }

    /**
     * @return array{key: string, label: string, icon: string, tone: string, state: string, at: string}
     */
    private function finalStep(string $key, string $label, string $icon, string $state, string $tone, ?CarbonInterface $at): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'tone' => $tone,
            'state' => $state,
            'at' => $this->stampFor($at, $state),
        ];
    }

    private function stampFor(?CarbonInterface $at, string $state): string
    {
        if ($at !== null) {
            return $at->format(self::DISPLAY_DATETIME_FORMAT);
        }

        return $state === 'current' ? 'En curso' : 'Pendiente';
    }

    // ── Timeline (real state_history) ────────────────────────────

    /**
     * @return list<array{icon: string, tone: string, title: string, at: string, actor: ?string, note: ?string, highlight: bool}>
     */
    private function timeline(): array
    {
        $events = [];

        $events[] = [
            'icon' => 'inbox',
            'tone' => 'neutral',
            'title' => 'Ticket creado',
            'at' => $this->ticket->created_at?->format(self::DISPLAY_DATETIME_FORMAT) ?? '—',
            'actor' => $this->displayName($this->ticket->reporter),
            'note' => 'Reportaste esta incidencia.',
            'highlight' => false,
        ];

        foreach ($this->ticket->stateHistory as $h) {
            $events[] = $this->mapHistory($h);
        }

        // Highlight the most recent event.
        $last = count($events) - 1;
        if ($last >= 0) {
            $events[$last]['highlight'] = true;
        }

        return $events;
    }

    /**
     * @return array{icon: string, tone: string, title: string, at: string, actor: ?string, note: ?string, highlight: bool}
     */
    private function mapHistory(StateHistory $h): array
    {
        $to = (string) $h->to_state;

        [$title, $icon, $tone] = match ($to) {
            Ticket::STATE_IN_PROGRESS => ['Trabajo iniciado', 'wrench', 'primary'],
            Ticket::STATE_RESOLVED => ['Ticket resuelto', 'circle-check', 'success'],
            Ticket::STATE_REJECTED => ['Ticket rechazado', 'x', 'high'],
            Ticket::STATE_CANCELLED => ['Solicitud cancelada', 'ban', 'neutral'],
            Ticket::STATE_OPEN => ['Ticket reabierto', 'rotate-ccw', 'warning'],
            default => [$this->stateLabel($to), 'circle-dot', 'neutral'],
        };

        $comment = trim((string) $h->comment);

        return [
            'icon' => $icon,
            'tone' => $tone,
            'title' => $title,
            'at' => $h->created_at?->format(self::DISPLAY_DATETIME_FORMAT) ?? '—',
            'actor' => $this->displayName($h->changedBy),
            'note' => $comment !== '' ? $comment : null,
            'highlight' => false,
        ];
    }

    // ── Details ──────────────────────────────────────────────────

    /**
     * @return array{rows: list<array{icon: string, label: string, value: string}>, technician: ?array{name: string, initials: string, role: string}}
     */
    private function details(): array
    {
        $location = $this->ticket->location?->name ?? 'Sin ubicación';
        $room = (string) ($this->ticket->location?->room_code ?? '');
        if ($room !== '') {
            $location .= ' · '.$room;
        }

        $assignee = $this->ticket->assignee;

        return [
            'rows' => [
                ['icon' => 'flag', 'label' => 'Prioridad', 'value' => $this->priorityLabel((string) $this->ticket->priority)],
                ['icon' => 'map-pin', 'label' => 'Ubicación', 'value' => $location],
                ['icon' => 'folder', 'label' => 'Categoría', 'value' => $this->ticket->category?->name ?? 'Sin categoría'],
                ['icon' => 'calendar', 'label' => 'Reportado', 'value' => $this->ticket->created_at?->format(self::DISPLAY_DATETIME_FORMAT) ?? '—'],
                ['icon' => 'clock', 'label' => 'Última actualización', 'value' => $this->ticket->updated_at?->format(self::DISPLAY_DATETIME_FORMAT) ?? '—'],
            ],
            'technician' => $assignee !== null
                ? ['name' => $this->displayName($assignee), 'initials' => $this->initials($assignee), 'role' => 'Mantenimiento']
                : null,
        ];
    }

    // ── Evidence (real media) ────────────────────────────────────

    /**
     * @return array{count: int, items: list<array{label: string, url: ?string, is_image: bool}>}
     */
    private function evidence(): array
    {
        $media = $this->ticket->media;

        $items = $media
            ->values()
            ->map(function (TicketMedia $m, int $i): array {
                $type = (string) $m->file_type;

                return [
                    'label' => 'Evidencia '.($i + 1),
                    'url' => $m->file_url !== null ? (string) $m->file_url : null,
                    'is_image' => str_starts_with($type, 'image'),
                ];
            })
            ->all();

        return ['count' => $media->count(), 'items' => $items];
    }

    // ── Small helpers ────────────────────────────────────────────

    private function displayName(?User $user): string
    {
        if ($user === null) {
            return 'Sistema';
        }

        $name = trim((string) $user->name.' '.(string) ($user->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string) ($user->email ?? 'Usuario');
    }

    private function initials(User $user): string
    {
        $name = trim((string) $user->name);
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr((string) ($parts[0] ?? ''), 0, 1);
        $second = mb_substr((string) ($parts[1] ?? ''), 0, 1);
        $initials = strtoupper($first.$second);

        return $initials !== '' ? $initials : 'U';
    }

    private function reference(string $id): string
    {
        return '#'.strtoupper(substr($id, 0, 8));
    }

    private function iconFor(?string $category): string
    {
        return self::CATEGORY_ICONS[strtolower(trim((string) $category))] ?? 'wrench';
    }

    private function stateTone(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'purple',
            Ticket::STATE_IN_PROGRESS => 'primary',
            Ticket::STATE_RESOLVED => 'success',
            Ticket::STATE_REJECTED => 'high',
            Ticket::STATE_CANCELLED => 'neutral',
            default => 'neutral',
        };
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Abierto',
            Ticket::STATE_IN_PROGRESS => 'En progreso',
            Ticket::STATE_RESOLVED => 'Resuelto',
            Ticket::STATE_REJECTED => 'Rechazado',
            Ticket::STATE_CANCELLED => 'Cancelado',
            default => ucfirst($state),
        };
    }

    private function priorityTone(string $priority): string
    {
        return match ($priority) {
            'critical', 'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
            default => ucfirst($priority),
        };
    }
}
