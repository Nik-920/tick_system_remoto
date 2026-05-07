<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $roleProfile = $this->resolveRoleProfile($user);
        $dashboardView = $this->resolveDashboardView($roleProfile);
        $dashboardData = match ($roleProfile) {
            'admin' => $this->buildAdminDashboardData(),
            'maintenance' => $this->buildMaintenanceDashboardData($user),
            default => $this->buildReporterDashboardData($user),
        };

        return view($dashboardView, [
            'roleProfile' => $roleProfile,
            'roleLabel' => $this->resolveRoleLabel($roleProfile),
            'stateLabels' => $this->stateLabels(),
            'priorityLabels' => $this->priorityLabels(),
            ...$dashboardData,
        ]);
    }

    private function resolveDashboardView(string $roleProfile): string
    {
        return match ($roleProfile) {
            'admin' => 'dashboard.admin',
            'maintenance' => 'dashboard.maintenance',
            default => 'dashboard.reporter',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReporterDashboardData(User $user): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        $reportedPendingUnassigned = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('state', 'open')
            ->whereNull('assigned_to')
            ->count();

        $reportedCriticalOpen = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('state', 'open')
            ->where('priority', 'critical')
            ->count();

        $reportedResolvedLast30Days = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('state', 'resolved')
            ->where('resolved_at', '>=', $thirtyDaysAgo)
            ->count();

        $reportedCreatedLast7Days = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        $myRecentTimeline = Ticket::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('reporter_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $attentionItems = Ticket::query()
            ->where('reporter_id', $user->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->with(['location', 'category', 'assignee'])
            ->orderByRaw($this->priorityOrderExpression())
            ->oldest('created_at')
            ->limit(6)
            ->get();

        $stateBreakdown = Ticket::query()
            ->selectRaw('state, COUNT(*) as total')
            ->where('reporter_id', $user->id)
            ->groupBy('state')
            ->pluck('total', 'state')
            ->toArray();

        $priorityBreakdown = Ticket::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->where('reporter_id', $user->id)
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        return [
            'hero' => [
                'badge' => 'Panel reporter',
                'title' => 'Centro personal de reportes',
                'subtitle' => 'Sigue en tiempo real tus incidencias, prioriza pendientes sin asignar y detecta bloqueos antes de que escalen.',
            ],
            'quickActions' => [
                [
                    'label' => 'Crear ticket',
                    'href' => route('tickets.create'),
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Ver mis tickets',
                    'href' => route('tickets.index'),
                    'variant' => 'secondary',
                ],
            ],
            'kpis' => [
                [
                    'label' => 'Pendientes sin asignar',
                    'value' => $reportedPendingUnassigned,
                    'hint' => 'Tickets abiertos que todavia no tienen responsable.',
                ],
                [
                    'label' => 'Criticos abiertos',
                    'value' => $reportedCriticalOpen,
                    'hint' => 'Reportes urgentes que requieren seguimiento cercano.',
                ],
                [
                    'label' => 'Resueltos en 30 dias',
                    'value' => $reportedResolvedLast30Days,
                    'hint' => 'Cierres recientes de tus reportes.',
                ],
                [
                    'label' => 'Nuevos en 7 dias',
                    'value' => $reportedCreatedLast7Days,
                    'hint' => 'Incidencias creadas recientemente por ti.',
                ],
            ],
            'attentionItems' => $attentionItems,
            'myRecentTimeline' => $myRecentTimeline,
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMaintenanceDashboardData(User $user): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        $assignedOpenCount = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'open')
            ->count();

        $assignedInProgressCount = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'in_progress')
            ->count();

        $assignedCriticalCount = Ticket::query()
            ->where('assigned_to', $user->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->where('priority', 'critical')
            ->count();

        $resolvedLast7Days = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'resolved')
            ->where('resolved_at', '>=', $sevenDaysAgo)
            ->count();

        $resolvedTicketsLast30Days = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $thirtyDaysAgo)
            ->get(['created_at', 'resolved_at']);

        $avgResolutionHoursLast30Days = (int) round($resolvedTicketsLast30Days->avg(function (Ticket $ticket): int {
            if ($ticket->resolved_at === null) {
                return 0;
            }

            return (int) $ticket->created_at->diffInHours($ticket->resolved_at);
        }) ?? 0);

        $workloadQueue = Ticket::query()
            ->where('assigned_to', $user->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->orderByRaw($this->priorityOrderExpression())
            ->oldest('created_at')
            ->limit(10)
            ->get();

        $recentResolved = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'resolved')
            ->with(['reporter', 'location', 'category'])
            ->latest('resolved_at')
            ->limit(6)
            ->get();

        $stateBreakdown = Ticket::query()
            ->selectRaw('state, COUNT(*) as total')
            ->where('assigned_to', $user->id)
            ->groupBy('state')
            ->pluck('total', 'state')
            ->toArray();

        $priorityBreakdown = Ticket::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->where('assigned_to', $user->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        return [
            'hero' => [
                'badge' => 'Panel maintenance',
                'title' => 'Consola de mantenimiento',
                'subtitle' => 'Gestiona tu cola operativa con enfoque en urgencias, tiempos de respuesta y cierres de calidad.',
            ],
            'quickActions' => [
                [
                    'label' => 'Ir a cola de tickets',
                    'href' => route('tickets.index'),
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Registrar incidencia',
                    'href' => route('tickets.create'),
                    'variant' => 'secondary',
                ],
            ],
            'kpis' => [
                [
                    'label' => 'Asignados abiertos',
                    'value' => $assignedOpenCount,
                    'hint' => 'Pendientes por iniciar.',
                ],
                [
                    'label' => 'En progreso',
                    'value' => $assignedInProgressCount,
                    'hint' => 'Trabajo tecnico actualmente activo.',
                ],
                [
                    'label' => 'Criticos activos',
                    'value' => $assignedCriticalCount,
                    'hint' => 'Casos de alta prioridad en tu cola.',
                ],
                [
                    'label' => 'Resueltos 7 dias',
                    'value' => $resolvedLast7Days,
                    'hint' => 'Cierres concretados en la ultima semana.',
                ],
                [
                    'label' => 'Promedio resolucion 30 dias',
                    'value' => $avgResolutionHoursLast30Days.' h',
                    'hint' => 'Tiempo medio de resolucion para tickets cerrados.',
                ],
            ],
            'workloadQueue' => $workloadQueue,
            'recentResolved' => $recentResolved,
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAdminDashboardData(): array
    {
        $sevenDaysAgo = now()->subDays(7);

        $totalTickets = Ticket::query()->count();
        $globalOpenUnassigned = Ticket::query()
            ->where('state', 'open')
            ->whereNull('assigned_to')
            ->count();
        $globalCriticalOpen = Ticket::query()
            ->where('state', 'open')
            ->where('priority', 'critical')
            ->count();
        $createdLast7Days = Ticket::query()
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
        $resolvedLast7Days = Ticket::query()
            ->where('state', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $sevenDaysAgo)
            ->count();
        $resolutionRate7Days = $createdLast7Days > 0
            ? round(($resolvedLast7Days / $createdLast7Days) * 100, 1)
            : 0.0;

        $stateBreakdown = Ticket::query()
            ->selectRaw('state, COUNT(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')
            ->toArray();

        $priorityBreakdown = Ticket::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $totalLocations = Location::query()->count();
        $activeLocations = Location::query()->active()->count();
        $totalCategories = Category::query()->count();

        $recentTickets = Ticket::query()
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        $topLocationsByOpen = Location::query()
            ->withCount([
                'tickets as open_tickets_count' => function (Builder $query): void {
                    $query->where('state', 'open');
                },
                'tickets as in_progress_tickets_count' => function (Builder $query): void {
                    $query->where('state', 'in_progress');
                },
            ])
            ->orderByDesc('open_tickets_count')
            ->orderByDesc('in_progress_tickets_count')
            ->limit(8)
            ->get()
            ->filter(function (Location $location): bool {
                $openTicketsCount = (int) $location->getAttribute('open_tickets_count');
                $inProgressTicketsCount = (int) $location->getAttribute('in_progress_tickets_count');

                return ($openTicketsCount + $inProgressTicketsCount) > 0;
            })
            ->take(5)
            ->values();

        $qrIssues = Location::query()
            ->whereIn('qr_generation_status', ['pending', 'processing', 'failed'])
            ->withCount('tickets')
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $qrStatusSummary = [
            'pending' => Location::query()->where('qr_generation_status', 'pending')->count(),
            'processing' => Location::query()->where('qr_generation_status', 'processing')->count(),
            'failed' => Location::query()->where('qr_generation_status', 'failed')->count(),
            'ready' => Location::query()->where('qr_generation_status', 'ready')->count(),
        ];

        return [
            'hero' => [
                'badge' => 'Panel admin',
                'title' => 'Centro de control operativo',
                'subtitle' => 'Supervisa flujo global, distribuye carga y anticipa riesgos en tickets, QR y catalogos.',
                'resolutionRate7Days' => $this->formatPercentage($resolutionRate7Days),
            ],
            'quickActions' => [
                [
                    'label' => 'Gestionar ubicaciones',
                    'href' => route('locations.index'),
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Gestionar categorias',
                    'href' => route('categories.index'),
                    'variant' => 'secondary',
                ],
                [
                    'label' => 'Revisar tickets',
                    'href' => route('tickets.index'),
                    'variant' => 'secondary',
                ],
            ],
            'kpis' => [
                [
                    'label' => 'Tickets totales',
                    'value' => $totalTickets,
                    'hint' => 'Volumen total registrado en plataforma.',
                ],
                [
                    'label' => 'Abiertos sin asignar',
                    'value' => $globalOpenUnassigned,
                    'hint' => 'Backlog que requiere asignacion inmediata.',
                ],
                [
                    'label' => 'Criticos abiertos',
                    'value' => $globalCriticalOpen,
                    'hint' => 'Incidencias de impacto alto en curso.',
                ],
                [
                    'label' => 'Ubicaciones activas',
                    'value' => $activeLocations,
                    'hint' => "{$activeLocations} activas de {$totalLocations} registradas.",
                ],
                [
                    'label' => 'Categorias',
                    'value' => $totalCategories,
                    'hint' => 'Catalogo disponible para clasificacion.',
                ],
                [
                    'label' => 'Creados 7 dias',
                    'value' => $createdLast7Days,
                    'hint' => 'Nuevos tickets ingresados esta semana.',
                ],
                [
                    'label' => 'Resueltos 7 dias',
                    'value' => $resolvedLast7Days,
                    'hint' => 'Tickets cerrados en los ultimos 7 dias.',
                ],
            ],
            'topLocationsByOpen' => $topLocationsByOpen,
            'qrStatusSummary' => $qrStatusSummary,
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'recentTickets' => $recentTickets,
            'qrIssues' => $qrIssues,
        ];
    }

    private function priorityOrderExpression(): string
    {
        return "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";
    }

    private function formatPercentage(float $value): string
    {
        return sprintf('%.1f%%', $value);
    }

    private function resolveRoleProfile(User $user): string
    {
        if ($this->hasAnyRole($user, ['admin', 'super_admin'])) {
            return 'admin';
        }

        if ($this->hasRole($user, 'maintenance')) {
            return 'maintenance';
        }

        return 'reporter';
    }

    private function resolveRoleLabel(string $roleProfile): string
    {
        return match ($roleProfile) {
            'admin' => 'Administracion',
            'maintenance' => 'Mantenimiento',
            default => 'Reporter',
        };
    }

    /**
     * @return array<string, string>
     */
    private function stateLabels(): array
    {
        return [
            'open' => 'Abierto',
            'in_progress' => 'En progreso',
            'resolved' => 'Resuelto',
            'rejected' => 'Rechazado',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function priorityLabels(): array
    {
        return [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        if (! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    private function hasRole(User $user, string $role): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($role);
    }
}
