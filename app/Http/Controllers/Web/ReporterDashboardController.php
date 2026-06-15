<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Tickets\ReporterTicketsBoardQuery;
use App\Services\Cache\DashboardCache;
use App\Support\Cache\CacheTtl;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reporter home dashboard (reporter-only role) — the redesigned personal panel:
 * welcome hero, KPI strip, recent reports, quick actions and an insights rail.
 *
 * Parallel redesign at /reporter/dashboard — it does NOT replace /dashboard
 * (which keeps rendering the live `dashboard.reporter` view).
 *
 * LIVE DATA via ReporterTicketsBoardQuery (reporter_id ownership scope applied
 * first): the greeting, the KPI strip, the recent-reports list, the status donut
 * and the average response time are all REAL and own-scoped. The only curated
 * pieces left are the static reporting tip and the "Actividad reciente" panel,
 * which is shown as an honest "Próximamente" placeholder (no invented metrics or
 * ids). NO maintenance action is exposed. Middleware (auth + role:reporter) gates.
 *
 * Cache strategy: versioned per-user cache via DashboardCache. Only plain arrays
 * (chips, tickets, summary) are serialised — Eloquent Collections are excluded.
 * The version is bumped by InvalidateDashboardCacheOnTicketChanged on every
 * TicketCreated / TicketStateChanged / TicketAssigned / TicketResolved event
 * that belongs to this reporter. TTL: CacheTtl::DASHBOARD_REPORTER (45 s).
 */
class ReporterDashboardController extends Controller
{
    public function __construct(private readonly DashboardCache $dashboardCache) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // Cache only plain-array data to avoid serialising Eloquent Collections.
        // userName, quickActions and tip are cheap / static — excluded from cache.
        $boardData = $this->dashboardCache->rememberReporter(
            $user->id,
            ['surface' => 'reporter_dashboard'],
            CacheTtl::DASHBOARD_REPORTER,
            function () use ($user): array {
                $board = ReporterTicketsBoardQuery::for($user, [], 'all', 'recent');

                return [
                    'chips' => $board->chips,
                    'tickets' => $board->tickets,
                    'summary' => $board->summary,
                ];
            }
        );

        return view('reporter.dashboard', [
            'userName' => $user->name,
            'kpis' => $this->kpis($boardData['chips']),
            'recent' => $boardData['tickets'],
            'quickActions' => $this->quickActions(),
            'summary' => $boardData['summary'],
            'tip' => 'Agrega fotos, detalles del problema y el lugar exacto para acelerar la atención.',
        ]);
    }

    /**
     * KPI strip built from the real, ownership-scoped chip counts. Adds the icon
     * and headline label per status; relabels the "all" chip as "Tickets totales".
     *
     * @param  list<array{key: string, label: string, count: int, tone: string, active: bool}>  $chips
     * @return list<array{label: string, value: int, icon: string, tone: string}>
     */
    private function kpis(array $chips): array
    {
        $meta = [
            'all' => ['label' => 'Tickets totales', 'icon' => 'inbox', 'tone' => 'primary'],
            'open' => ['label' => 'Abiertos', 'icon' => 'circle-dot', 'tone' => 'purple'],
            'in_progress' => ['label' => 'En progreso', 'icon' => 'wrench', 'tone' => 'primary'],
            'resolved' => ['label' => 'Resueltos', 'icon' => 'circle-check', 'tone' => 'success'],
            'rejected' => ['label' => 'Rechazados', 'icon' => 'x-circle', 'tone' => 'high'],
            'cancelled' => ['label' => 'Cancelados', 'icon' => 'ban', 'tone' => 'neutral'],
            'duplicate' => ['label' => 'Posible duplicado', 'icon' => 'copy', 'tone' => 'info'],
        ];

        $kpis = [];
        foreach ($chips as $chip) {
            $m = $meta[$chip['key']] ?? null;
            if ($m === null) {
                continue;
            }

            $kpis[] = ['label' => $m['label'], 'value' => $chip['count'], 'icon' => $m['icon'], 'tone' => $m['tone']];
        }

        return $kpis;
    }

    /**
     * Quick-action tiles — all point to real reporter routes.
     *
     * @return list<array{label: string, href: ?string, icon: string, tone: string}>
     */
    private function quickActions(): array
    {
        return [
            ['label' => 'Crear ticket',   'href' => route('tickets.create'),           'icon' => 'plus-circle', 'tone' => 'primary'],
            ['label' => 'Mis tickets',    'href' => route('reporter.tickets.index'),   'icon' => 'file-text',   'tone' => 'info'],
            ['label' => 'Historial',      'href' => route('reporter.tickets.history'), 'icon' => 'history',     'tone' => 'success'],
            ['label' => 'Guía de reporte', 'href' => route('reporter.guide'),          'icon' => 'book-open',   'tone' => 'purple'],
        ];
    }
}
