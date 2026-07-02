<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the admin/super_admin dashboard data array.
 *
 * Security contract: all counts are global (no ownership scope) — this is
 * intentional; admin has full visibility. The caller (DashboardController) is
 * responsible for ensuring only admin/super_admin profiles reach this query.
 *
 * Returns the exact same keys that DashboardController::buildAdminDashboardData()
 * returned inline, so no Blade changes are required.
 */
final class AdminDashboardQuery
{
    public function execute(User $user): array
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

            // Raw scalars — kept alongside the shaped keys above so a presenter
            // (e.g. AdminDashboardV2Presenter) can build KPI/gauge/alert widgets
            // without re-deriving numbers already computed here.
            'totalTicketsCount' => $totalTickets,
            'openUnassignedCount' => $globalOpenUnassigned,
            'criticalOpenCount' => $globalCriticalOpen,
            'createdLast7DaysCount' => $createdLast7Days,
            'resolvedLast7DaysCount' => $resolvedLast7Days,
            'resolutionRate7DaysValue' => $resolutionRate7Days,
            'totalLocationsCount' => $totalLocations,
            'activeLocationsCount' => $activeLocations,
            'totalCategoriesCount' => $totalCategories,
            'totalUsersCount' => User::query()->count(),
        ];
    }

    /**
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

    /** @return array<string, int> */
    private function qrStatusSummary(): array
    {
        $rows = Location::query()
            ->selectRaw('qr_generation_status, COUNT(*) as total')
            ->groupBy('qr_generation_status')
            ->pluck('total', 'qr_generation_status')
            ->map(fn ($v): int => (int) $v)
            ->all();

        return [
            'pending' => $rows['pending'] ?? 0,
            'processing' => $rows['processing'] ?? 0,
            'failed' => $rows['failed'] ?? 0,
            'ready' => $rows['ready'] ?? 0,
        ];
    }

    /**
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

    private function formatPercentage(float $value): string
    {
        return sprintf('%.1f%%', $value);
    }

    private function hasRole(User $user, string $role): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($role);
    }
}
