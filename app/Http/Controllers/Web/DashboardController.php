<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * @return View
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $roleProfile = $this->resolveRoleProfile($user);
        $dashboardData = match ($roleProfile) {
            'admin' => $this->buildAdminDashboardData(),
            'maintenance' => $this->buildMaintenanceDashboardData($user),
            default => $this->buildReporterDashboardData($user),
        };

        return view('dashboard.index', [
            'roleProfile' => $roleProfile,
            'roleLabel' => $this->resolveRoleLabel($roleProfile),
            'stateLabels' => $this->stateLabels(),
            'priorityLabels' => $this->priorityLabels(),
            ...$dashboardData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReporterDashboardData(User $user): array
    {
        $reportedOpen = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('state', 'open')
            ->count();

        $reportedResolved = Ticket::query()
            ->where('reporter_id', $user->id)
            ->where('state', 'resolved')
            ->count();

        $assignedActive = Ticket::query()
            ->where('assigned_to', $user->id)
            ->whereNotIn('state', ['resolved', 'rejected'])
            ->count();

        $recentTickets = Ticket::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('reporter_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->latest('created_at')
            ->limit(8)
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
            'headline' => 'Resumen personal de tickets',
            'subtitle' => 'Monitorea tus reportes y tareas asignadas en un solo lugar.',
            'kpis' => [
                [
                    'label' => 'Reportados abiertos',
                    'value' => $reportedOpen,
                    'hint' => 'Incidencias reportadas por ti que siguen en atencion.',
                ],
                [
                    'label' => 'Reportados resueltos',
                    'value' => $reportedResolved,
                    'hint' => 'Reportes cerrados exitosamente.',
                ],
                [
                    'label' => 'Asignados activos',
                    'value' => $assignedActive,
                    'hint' => 'Tickets donde participas como responsable.',
                ],
                [
                    'label' => 'Recientes visibles',
                    'value' => $recentTickets->count(),
                    'hint' => 'Ultimos tickets relacionados contigo.',
                ],
            ],
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'recentTickets' => $recentTickets,
            'qrIssues' => collect(),
            'showQrIssues' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMaintenanceDashboardData(User $user): array
    {
        $assignedOpen = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'open')
            ->count();

        $assignedInProgress = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'in_progress')
            ->count();

        $assignedResolved = Ticket::query()
            ->where('assigned_to', $user->id)
            ->where('state', 'resolved')
            ->count();

        $recentTickets = Ticket::query()
            ->where('assigned_to', $user->id)
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->latest('created_at')
            ->limit(8)
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
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        return [
            'headline' => 'Operacion de mantenimiento',
            'subtitle' => 'Enfoca tu trabajo en tickets asignados y su avance operativo.',
            'kpis' => [
                [
                    'label' => 'Asignados abiertos',
                    'value' => $assignedOpen,
                    'hint' => 'Pendientes por iniciar.',
                ],
                [
                    'label' => 'En progreso',
                    'value' => $assignedInProgress,
                    'hint' => 'Incidencias en ejecucion.',
                ],
                [
                    'label' => 'Resueltos',
                    'value' => $assignedResolved,
                    'hint' => 'Trabajos finalizados.',
                ],
                [
                    'label' => 'Recientes asignados',
                    'value' => $recentTickets->count(),
                    'hint' => 'Ultimos tickets bajo tu gestion.',
                ],
            ],
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'recentTickets' => $recentTickets,
            'qrIssues' => collect(),
            'showQrIssues' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAdminDashboardData(): array
    {
        $totalTickets = Ticket::query()->count();

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
        $activeLocations = Location::query()->where('is_active', true)->count();
        $totalCategories = Category::query()->count();

        $recentTickets = Ticket::query()
            ->with(['reporter', 'assignee', 'location', 'category'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $qrIssues = Location::query()
            ->whereIn('qr_generation_status', ['pending', 'processing', 'failed'])
            ->withCount('tickets')
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return [
            'headline' => 'Indicadores globales',
            'subtitle' => 'Controla el estado operativo de tickets, catalogos y QR institucional.',
            'kpis' => [
                [
                    'label' => 'Tickets totales',
                    'value' => $totalTickets,
                    'hint' => 'Volumen total registrado en plataforma.',
                ],
                [
                    'label' => 'Tickets abiertos',
                    'value' => (int) ($stateBreakdown['open'] ?? 0),
                    'hint' => 'Pendientes de atencion.',
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
            ],
            'stateBreakdown' => $stateBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'recentTickets' => $recentTickets,
            'qrIssues' => $qrIssues,
            'showQrIssues' => true,
        ];
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
     * @param list<string> $roles
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
