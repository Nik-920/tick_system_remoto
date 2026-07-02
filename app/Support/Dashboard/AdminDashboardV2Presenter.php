<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Dashboard\AdminDashboardQuery;
use App\Support\Dashboard\Concerns\BuildsDashboardCharts;
use App\Support\LocalTime;
use Illuminate\Support\Collection;

/**
 * Builds the /dashboard view payload for the admin/super_admin profile.
 *
 * Card-based, no-table sibling of MaintenanceDashboardV2Presenter — same
 * visual grammar (header, KPI row, charts row, main+rail layout, bottom
 * cards) built from AdminDashboardQuery's global, unscoped figures. Admin
 * sees the whole platform (no ownership scope), so the widgets that are
 * unique to this profile are QR fleet health and the catalog/user bottom
 * cards rather than "my assignments".
 */
final class AdminDashboardV2Presenter
{
    use BuildsDashboardCharts;

    /** How many rows the recent-activity feed shows (query fetches 10). */
    private const ACTIVITY_LIMIT = 6;

    private const STATE_LABELS = [
        Ticket::STATE_OPEN => 'Abierto',
        Ticket::STATE_IN_PROGRESS => 'En progreso',
        Ticket::STATE_RESOLVED => 'Resuelto',
        Ticket::STATE_REJECTED => 'Rechazado',
        'cancelled' => 'Cancelado',
    ];

    private const PRIORITY_LABELS = [
        'critical' => 'Crítica',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ];

    private const QR_LABELS = [
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'failed' => 'Fallido',
        'ready' => 'Listo',
    ];

    public function __construct(private readonly AdminDashboardQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $data = $this->query->execute($user);

        return [
            'hero' => $data['hero'],
            'quickActions' => $data['quickActions'],
            'kpis' => $this->kpis($data),
            'statusDonut' => $this->statusDonut($data['stateBreakdown']),
            'priorityBars' => $this->priorityBars($data['priorityBreakdown']),
            'qrBars' => $this->qrBars($data['qrStatusSummary']),
            'closeRate' => [
                'value' => (int) round($data['resolutionRate7DaysValue']),
                'resolved' => $data['resolvedLast7DaysCount'],
                'total' => $data['createdLast7DaysCount'],
            ],
            'recentActivity' => $this->recentActivity($data['recentTickets']),
            'topLocations' => $this->topLocations($data['topLocationsByOpen']),
            'qrIssues' => $this->qrIssuesList($data['qrIssues']),
            'alerts' => $this->alerts($data),
            'bottomCards' => $this->bottomCards($data, $user),
        ];
    }

    // ── KPI row ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{value: string, label: string, note: string, icon: string, tone: string}>
     */
    private function kpis(array $data): array
    {
        $unassigned = (int) $data['openUnassignedCount'];
        $critical = (int) $data['criticalOpenCount'];
        $resolved7 = (int) $data['resolvedLast7DaysCount'];

        return [
            ['value' => (string) $data['totalTicketsCount'], 'label' => 'Tickets totales', 'note' => 'Volumen total registrado en plataforma.', 'icon' => 'clipboard-list', 'tone' => 'primary'],
            ['value' => (string) $unassigned, 'label' => 'Abiertos sin asignar', 'note' => $unassigned > 0 ? 'Backlog que requiere asignación inmediata.' : 'Backlog de asignación al día.', 'icon' => 'inbox', 'tone' => $unassigned > 0 ? 'warning' : 'success'],
            ['value' => (string) $critical, 'label' => 'Críticos abiertos', 'note' => $critical > 0 ? 'Incidencias de impacto alto en curso.' : 'Sin críticos activos.', 'icon' => 'flame', 'tone' => $critical > 0 ? 'high' : 'success'],
            ['value' => (string) $data['createdLast7DaysCount'], 'label' => 'Creados 7 días', 'note' => 'Nuevos tickets ingresados esta semana.', 'icon' => 'calendar-plus', 'tone' => 'info'],
            ['value' => (string) $resolved7, 'label' => 'Resueltos 7 días', 'note' => 'Tasa de cierre: '.$data['hero']['resolutionRate7Days'].'.', 'icon' => 'circle-check', 'tone' => $resolved7 > 0 ? 'success' : 'warning'],
        ];
    }

    // ── Charts row ───────────────────────────────────────────────

    /**
     * @param  array<string, int>  $dist
     * @return array{total: int, segments: list<array{key: string, label: string, count: int, percent: float, start: float, end: float, color: string, tone: string}>}
     */
    private function statusDonut(array $dist): array
    {
        $bands = [
            ['key' => Ticket::STATE_OPEN, 'label' => self::STATE_LABELS[Ticket::STATE_OPEN], 'color' => '#2563eb', 'tone' => 'primary'],
            ['key' => Ticket::STATE_IN_PROGRESS, 'label' => self::STATE_LABELS[Ticket::STATE_IN_PROGRESS], 'color' => '#f59e0b', 'tone' => 'warning'],
            ['key' => Ticket::STATE_RESOLVED, 'label' => self::STATE_LABELS[Ticket::STATE_RESOLVED], 'color' => '#16a34a', 'tone' => 'success'],
            ['key' => Ticket::STATE_REJECTED, 'label' => self::STATE_LABELS[Ticket::STATE_REJECTED], 'color' => '#64748b', 'tone' => 'neutral'],
            ['key' => 'cancelled', 'label' => self::STATE_LABELS['cancelled'], 'color' => '#94a3b8', 'tone' => 'neutral'],
        ];

        return $this->buildDonutSegments($bands, $dist, array_sum($dist));
    }

    /**
     * @param  array<string, int>  $dist
     * @return array{peak: int, items: list<array{label: string, count: int, tone: string}>}
     */
    private function priorityBars(array $dist): array
    {
        return $this->buildBarItems([
            ['key' => 'critical', 'label' => self::PRIORITY_LABELS['critical'], 'tone' => 'purple'],
            ['key' => 'high', 'label' => self::PRIORITY_LABELS['high'], 'tone' => 'high'],
            ['key' => 'medium', 'label' => self::PRIORITY_LABELS['medium'], 'tone' => 'warning'],
            ['key' => 'low', 'label' => self::PRIORITY_LABELS['low'], 'tone' => 'success'],
        ], $dist);
    }

    /**
     * QR fleet health — the one chart with no equivalent on the reporter/
     * maintenance dashboards (institutional QR generation is admin's scope).
     *
     * @param  array<string, int>  $summary
     * @return array{total: int, peak: int, items: list<array{label: string, count: int, tone: string}>}
     */
    private function qrBars(array $summary): array
    {
        $bands = [
            ['key' => 'ready', 'label' => self::QR_LABELS['ready'], 'tone' => 'success'],
            ['key' => 'processing', 'label' => self::QR_LABELS['processing'], 'tone' => 'warning'],
            ['key' => 'pending', 'label' => self::QR_LABELS['pending'], 'tone' => 'neutral'],
            ['key' => 'failed', 'label' => self::QR_LABELS['failed'], 'tone' => 'high'],
        ];

        return ['total' => array_sum($summary), ...$this->buildBarItems($bands, $summary)];
    }

    // ── Main column lists ────────────────────────────────────────

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return list<array{id: string, title: string, ref: string, location: string, state: string, state_label: string, tone: string, icon: string, at: string}>
     */
    private function recentActivity(Collection $tickets): array
    {
        return $tickets
            ->take(self::ACTIVITY_LIMIT)
            ->map(fn (Ticket $t): array => [
                'id' => (string) $t->id,
                'title' => (string) $t->title,
                'ref' => $this->reference($t),
                'location' => $t->location?->name ?? 'Sin ubicación',
                'state' => (string) $t->state,
                'state_label' => self::STATE_LABELS[$t->state] ?? ucfirst((string) $t->state),
                'tone' => $this->stateTone((string) $t->state),
                'icon' => $this->stateIcon((string) $t->state),
                'at' => LocalTime::format($t->created_at, 'd/m/Y h:i A') ?? '—',
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array{id: string, name: string, meta: string, open: int, in_progress: int, percent: int}>
     */
    private function topLocations(Collection $locations): array
    {
        $peak = max(1, (int) $locations->max(
            fn (Location $l): int => (int) $l->getAttribute('open_tickets_count') + (int) $l->getAttribute('in_progress_tickets_count')
        ));

        return $locations
            ->map(function (Location $l) use ($peak): array {
                $open = (int) $l->getAttribute('open_tickets_count');
                $inProgress = (int) $l->getAttribute('in_progress_tickets_count');

                return [
                    'id' => (string) $l->id,
                    'name' => (string) $l->name,
                    'meta' => trim(($l->room_code ?? '').' · '.($l->building ?? ''), " \t\n\r\0\x0B·"),
                    'open' => $open,
                    'in_progress' => $inProgress,
                    'percent' => (int) round(($open + $inProgress) / $peak * 100),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array{id: string, name: string, room: string, status: string, status_label: string, tickets_count: int}>
     */
    private function qrIssuesList(Collection $locations): array
    {
        return $locations
            ->map(fn (Location $l): array => [
                'id' => (string) $l->id,
                'name' => (string) $l->name,
                'room' => (string) ($l->room_code ?? ''),
                'status' => (string) ($l->qr_generation_status ?? 'pending'),
                'status_label' => self::QR_LABELS[$l->qr_generation_status ?? 'pending'] ?? ucfirst((string) $l->qr_generation_status),
                'tickets_count' => (int) $l->getAttribute('tickets_count'),
            ])
            ->all();
    }

    // ── Rail: alerts ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{count: int, title: string, note: string, tone: string, icon: string}>
     */
    private function alerts(array $data): array
    {
        $critical = (int) $data['criticalOpenCount'];
        $unassigned = (int) $data['openUnassignedCount'];
        $qrFailed = (int) ($data['qrStatusSummary']['failed'] ?? 0);

        $alerts = [];

        if ($critical > 0) {
            $alerts[] = [
                'count' => $critical,
                'title' => $critical === 1 ? 'ticket crítico abierto' : 'tickets críticos abiertos',
                'note' => 'Impacto alto sin resolver: prioriza su asignación.',
                'tone' => 'high',
                'icon' => 'flame',
            ];
        }

        if ($unassigned > 0) {
            $alerts[] = [
                'count' => $unassigned,
                'title' => $unassigned === 1 ? 'ticket abierto sin asignar' : 'tickets abiertos sin asignar',
                'note' => 'Backlog pendiente de asignación a mantenimiento.',
                'tone' => 'warning',
                'icon' => 'inbox',
            ];
        }

        if ($qrFailed > 0) {
            $alerts[] = [
                'count' => $qrFailed,
                'title' => $qrFailed === 1 ? 'ubicación con QR fallido' : 'ubicaciones con QR fallido',
                'note' => 'Requiere regenerar el código QR institucional.',
                'tone' => 'purple',
                'icon' => 'qr-code',
            ];
        }

        return $alerts;
    }

    // ── Bottom cards ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{value: string, label: string, note: string, icon: string, tone: string}>
     */
    private function bottomCards(array $data, User $user): array
    {
        $cards = [
            [
                'value' => (string) $data['activeLocationsCount'],
                'label' => 'Ubicaciones activas',
                'note' => "{$data['activeLocationsCount']} activas de {$data['totalLocationsCount']} registradas.",
                'icon' => 'map-pin',
                'tone' => 'primary',
            ],
            [
                'value' => (string) $data['totalCategoriesCount'],
                'label' => 'Categorías',
                'note' => 'Catálogo disponible para clasificación.',
                'icon' => 'tag',
                'tone' => 'info',
            ],
        ];

        if ($this->hasRole($user, 'super_admin')) {
            $cards[] = [
                'value' => (string) $data['totalUsersCount'],
                'label' => 'Usuarios registrados',
                'note' => 'Cuentas activas en la plataforma.',
                'icon' => 'users',
                'tone' => 'purple',
            ];
        }

        return $cards;
    }

    // ── Small shared helpers ─────────────────────────────────────

    private function stateTone(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'primary',
            Ticket::STATE_IN_PROGRESS => 'warning',
            Ticket::STATE_RESOLVED => 'success',
            Ticket::STATE_REJECTED => 'neutral',
            default => 'neutral',
        };
    }

    private function stateIcon(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'inbox',
            Ticket::STATE_IN_PROGRESS => 'loader-circle',
            Ticket::STATE_RESOLVED => 'circle-check',
            Ticket::STATE_REJECTED => 'x-circle',
            default => 'activity',
        };
    }

    private function hasRole(User $user, string $role): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($role);
    }
}
