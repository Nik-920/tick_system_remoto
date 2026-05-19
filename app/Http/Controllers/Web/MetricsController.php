<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class MetricsController extends Controller
{
    public function __invoke()
    {
        $registry = new CollectorRegistry(new InMemory());

        // Tickets por estado
        $ticketsByState = $registry->registerGauge(
            'tick_system',
            'tickets_by_state',
            'Tickets grouped by state',
            ['state']
        );

        $states = Ticket::query()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->get();

        foreach ($states as $row) {
            /** @var int $total */
            $total = $row->getAttribute('total');
            $ticketsByState->set((float) $total, [$row->state]);
        }

        // Total usuarios
        $totalUsers = $registry->registerGauge(
            'tick_system',
            'total_users',
            'Total registered users'
        );
        $totalUsers->set(User::count());

        // Tickets creados hoy
        $ticketsToday = $registry->registerGauge(
            'tick_system',
            'tickets_created_today',
            'Tickets created today'
        );
        $ticketsToday->set(Ticket::whereDate('created_at', today())->count());

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return response($result, 200)->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
