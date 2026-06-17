<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Dashboard\AdminDashboardQuery;
use App\Support\Dashboard\MaintenanceDashboardV2Presenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MaintenanceDashboardV2Presenter $maintenancePresenter,
        private readonly AdminDashboardQuery $adminDashboardQuery,
    ) {}

    public function index(DashboardDateRangeRequest $request): View|RedirectResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $roleProfile = $this->resolveRoleProfile($user);

        if ($roleProfile === 'reporter') {
            return redirect()->route('reporter.dashboard');
        }

        if ($roleProfile === 'maintenance') {
            // Promoted: the maintenance role now gets the redesigned V2 dashboard,
            // built from the same shared presenter as /dashboard/maintenance-v2.
            return view('dashboard.maintenance-v2', $this->maintenancePresenter->payload($user, $request));
        }

        return view('dashboard.admin', [
            'roleProfile' => $roleProfile,
            'roleLabel' => $this->resolveRoleLabel($roleProfile),
            'stateLabels' => $this->stateLabels(),
            'priorityLabels' => $this->priorityLabels(),
            ...$this->adminDashboardQuery->execute($user),
        ]);
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
