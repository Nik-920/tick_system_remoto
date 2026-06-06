<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Dashboard\MaintenanceDashboardV2Presenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MaintenanceDashboardV2Presenter $maintenancePresenter,
    ) {}

    public function index(DashboardDateRangeRequest $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $roleProfile = $this->resolveRoleProfile($user);

        if ($roleProfile === 'maintenance') {
            // Promoted: the maintenance role now gets the redesigned V2 dashboard,
            // built from the same shared presenter as /dashboard/maintenance-v2.
            return view('dashboard.maintenance-v2', $this->maintenancePresenter->payload($user, $request));
        }

        $dashboardView = $this->resolveDashboardView($roleProfile);
        $dashboardData = $roleProfile === 'admin'
            ? $this->buildAdminDashboardData($user)
            : $this->buildReporterDashboardData($user);

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

        $stateBreakdown = $this->countByColumn(
            Ticket::query()->where('reporter_id', $user->id),
            'state'
        );

        $priorityBreakdown = $this->countByColumn(
            Ticket::query()->where('reporter_id', $user->id),
            'priority'
        );

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
    private function buildAdminDashboardData(User $user): array
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

        $stateBreakdown = $this->countByColumn(Ticket::query(), 'state');
        $priorityBreakdown = $this->countByColumn(Ticket::query(), 'priority');

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

        $qrStatusSummary = $this->qrStatusSummary();

        return [
            'hero' => [
                'badge' => 'Panel admin',
                'title' => 'Centro de control operativo',
                'subtitle' => 'Supervisa flujo global, distribuye carga y anticipa riesgos en tickets, QR y catalogos.',
                'resolutionRate7Days' => $this->formatPercentage($resolutionRate7Days),
            ],
            'quickActions' => $this->adminQuickActions($user),
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

    /**
     * Agrupa y cuenta tickets por una columna sobre una query base ya filtrada.
     * Centraliza el patrón selectRaw/groupBy/pluck repetido en los 3 perfiles.
     *
     * @param  Builder<Ticket>  $query
     * @return array<string, int>
     */
    private function countByColumn(Builder $query, string $column): array
    {
        return $query
            ->selectRaw("{$column}, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', $column)
            ->toArray();
    }

    /**
     * @return array<string, int>
     */
    private function qrStatusSummary(): array
    {
        return [
            'pending' => Location::query()->where('qr_generation_status', 'pending')->count(),
            'processing' => Location::query()->where('qr_generation_status', 'processing')->count(),
            'failed' => Location::query()->where('qr_generation_status', 'failed')->count(),
            'ready' => Location::query()->where('qr_generation_status', 'ready')->count(),
        ];
    }

    /**
     * Acciones rápidas del panel admin. super_admin suma la gestión de usuarios.
     *
     * @return array<int, array{label:string,href:string,variant:string}>
     */
    private function adminQuickActions(User $user): array
    {
        $actions = [
            ['label' => 'Gestionar ubicaciones', 'href' => route('locations.index'), 'variant' => 'primary'],
            ['label' => 'Asignar tickets pendientes', 'href' => route('tickets.available'), 'variant' => 'secondary'],
            ['label' => 'Gestionar categorias', 'href' => route('categories.index'), 'variant' => 'secondary'],
            ['label' => 'Revisar tickets', 'href' => route('tickets.index'), 'variant' => 'secondary'],
        ];

        if ($this->hasRole($user, 'super_admin')) {
            $actions[] = ['label' => 'Gestionar usuarios', 'href' => route('users.index'), 'variant' => 'secondary'];
        }

        return $actions;
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
