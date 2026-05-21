<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function __invoke()
    {
        $registry = new CollectorRegistry(new InMemory());

        // 1. Tickets por estado
        $ticketsByState = $registry->registerGauge(
            'tick_system', 'tickets_by_state',
            'Tickets grouped by state', ['state']
        );
        foreach (Ticket::query()->selectRaw('state, count(*) as total')->groupBy('state')->get() as $row) {
            $ticketsByState->set((float) $row->total, [$row->state]);
        }

        // 2. Total usuarios
        $totalUsers = $registry->registerGauge(
            'tick_system', 'total_users', 'Total registered users'
        );
        $totalUsers->set(User::count());

        // 3. Tickets creados hoy
        $ticketsToday = $registry->registerGauge(
            'tick_system', 'tickets_created_today', 'Tickets created today'
        );
        $ticketsToday->set(Ticket::whereDate('created_at', today())->count());

        // 4. Tickets por categoría
        $ticketsByCategory = $registry->registerGauge(
            'tick_system', 'tickets_by_category',
            'Tickets grouped by category', ['category']
        );
        $categories = DB::table('tickets')
            ->join('categories', 'tickets.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as category, count(*) as total')
            ->groupBy('categories.name')
            ->get();
        foreach ($categories as $row) {
            $ticketsByCategory->set((float) $row->total, [$row->category]);
        }

        // 5. Tickets por ubicación
        $ticketsByLocation = $registry->registerGauge(
            'tick_system', 'tickets_by_location',
            'Tickets grouped by location', ['location']
        );
        $locations = DB::table('tickets')
            ->join('locations', 'tickets.location_id', '=', 'locations.id')
            ->selectRaw('locations.name as location, count(*) as total')
            ->groupBy('locations.name')
            ->get();
        foreach ($locations as $row) {
            $ticketsByLocation->set((float) $row->total, [$row->location]);
        }

        // 6. Usuarios por rol
        $usersByRole = $registry->registerGauge(
            'tick_system', 'users_by_role',
            'Users grouped by role', ['role']
        );
        $roles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->selectRaw('roles.name as role, count(*) as total')
            ->groupBy('roles.name')
            ->get();
        foreach ($roles as $row) {
            $usersByRole->set((float) $row->total, [$row->role]);
        }

        // 7. Tickets resueltos esta semana
        $ticketsResolvedWeek = $registry->registerGauge(
            'tick_system', 'tickets_resolved_this_week',
            'Tickets resolved this week'
        );
        $ticketsResolvedWeek->set(
            Ticket::where('state', 'resolved')
                ->whereBetween('resolved_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count()
        );

        // 8. Tiempo promedio de resolución en horas
        $avgResolutionTime = $registry->registerGauge(
            'tick_system', 'avg_resolution_time_hours',
            'Average ticket resolution time in hours'
        );
        $avg = DB::table('tickets')
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');
        $avgResolutionTime->set((float) ($avg ?? 0));

        // 9. Tickets por prioridad
        $ticketsByPriority = $registry->registerGauge(
            'tick_system', 'tickets_by_priority',
            'Tickets grouped by priority', ['priority']
        );
        foreach (Ticket::query()->selectRaw('priority, count(*) as total')->groupBy('priority')->get() as $row) {
            $ticketsByPriority->set((float) $row->total, [$row->priority ?? 'none']);
        }

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return response($result, 200)->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
