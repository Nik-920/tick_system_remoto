<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Http\Requests\Dashboard\DashboardDateRangeRequest;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\Queries\Dashboard\MaintenanceDashboardQuery;
use App\Support\LocalTime;
use App\ViewModels\Dashboard\MaintenanceDashboardViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

/**
 * Builds the Dashboard Maintenance V2 view payload for a technician + range.
 *
 * Shared by both entry points so the logic lives in ONE place:
 *  - MaintenanceDashboardV2Controller (the /dashboard/maintenance-v2 alias), and
 *  - DashboardController@index (the promoted /dashboard for the maintenance role).
 *
 * The heavy figures are reused from MaintenanceDashboardQuery (the same
 * ViewModel that powers the PDF report), so the dashboard and the PDF never
 * diverge and no calculation is duplicated. Only three small V2-only widgets
 * need extra scoped reads (recent activity, the assignments slice and the
 * duplicate count); each applies the ownership scope (assigned_to = me) FIRST.
 *
 * Visibility contract: every personal figure is bounded by assigned_to = the
 * technician. The only intentionally global number is `availableToClaimCount`
 * (open + unassigned + claimable), surfaced as such.
 *
 * Semantics (documented so the numbers add up):
 *  - The "Tickets por estado" donut and the four state-count KPIs describe the
 *    technician's tickets CREATED within the selected period, by current state
 *    (= ViewModel::stateDistribution). "Tickets totales" is their sum.
 *  - "Tiempo prom. resolución" and the timing card reuse the ViewModel's
 *    resolution metrics (resolved_at within the period).
 *  - The gauge is a "Tasa de resolución" (resolved / total of the period). There
 *    is no SLA table, so no SLA metric is invented.
 */
final class MaintenanceDashboardV2Presenter
{
    /** Existing maintenance PDF export route (reused, never recreated). */
    private const PDF_ROUTE = 'dashboard.maintenance.report.pdf';

    /** Rows shown in the activity feed and the assignments slice. */
    private const FEED_LIMIT = 5;

    /** Active states for "Mis asignaciones" (own open + in progress). */
    private const ACTIVE_STATES = [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    /** UI label for the in_progress state (KPIs, donut and state labels). */
    private const LABEL_IN_PROGRESS = 'En progreso';

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, DashboardDateRangeRequest $request): array
    {
        $range = $request->toDateRange();
        $vm = MaintenanceDashboardQuery::for($user, $range);

        $userId = (string) $user->id;
        $kpiByKey = $this->kpisByKey($vm);
        $total = array_sum($vm->stateDistribution);

        $trend = $this->trend($vm);
        $trendMax = max(1, ...$trend['created'], ...$trend['resolved']);

        // What the user actively selected (may be "custom" even before dates are
        // valid), so the control can reveal the from/to inputs server-side.
        $rawPreset = (string) $request->input('preset', '');
        $selectedPreset = in_array($rawPreset, DateRange::SELECTABLE_PRESETS, true) ? $rawPreset : $range->preset;
        $isCustom = $selectedPreset === DateRange::PRESET_CUSTOM;

        return [
            // ── Header / range control (real, GET-driven) ──
            'dateRange' => (LocalTime::format($range->from, 'd/m/Y') ?? '—').' – '.(LocalTime::format($range->to, 'd/m/Y') ?? '—'),
            'rangePreset' => $selectedPreset,
            'rangePresets' => $this->rangePresets(),
            'isCustomRange' => $isCustom,
            'customFrom' => $isCustom ? $range->fromDate() : '',
            'customTo' => $isCustom ? $range->toDate() : '',
            'pdfUrl' => $this->pdfUrl($range),

            // ── KPIs + charts (reused ViewModel) ──
            'kpis' => $this->kpis($vm, $kpiByKey, $total),
            'statusDonut' => $this->statusDonut($vm->stateDistribution, $total),
            'priorityBars' => $this->priorityBars($vm->priorityDistribution),
            'labBars' => $this->labBars($vm),

            // ── Rail (reused ViewModel) ──
            'closeRate' => $this->closeRate($vm->stateDistribution, $total),
            'times' => [
                'avg' => $this->kpiValue($kpiByKey, 'avg_resolution'),
                'median' => $this->kpiValue($kpiByKey, 'median_resolution'),
                'first' => $this->kpiValue($kpiByKey, 'first_response'),
            ],
            'trend' => $trend,
            'trendMax' => $trendMax,
            'alerts' => $this->alerts($kpiByKey, $vm->availableToClaimCount),

            // ── Activity + assignments (V2-only scoped reads) ──
            'activity' => $this->activity($userId),
            'activityPager' => $this->activityPager($userId),
            'assignments' => $this->assignments($userId),
            'assignmentsPager' => $this->assignmentsPager($userId),

            // ── Bottom cards (all real) ──
            'bottomCards' => $this->bottomCards($vm, $userId, $range, $kpiByKey),
        ];
    }

    // ── Header helpers ───────────────────────────────────────────

    private function pdfUrl(DateRange $range): ?string
    {
        // Reuse the EXISTING report route and preserve the active range so the
        // exported PDF matches exactly what the technician is looking at.
        return Route::has(self::PDF_ROUTE)
            ? route(self::PDF_ROUTE, $range->toQueryParams())
            : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function rangePresets(): array
    {
        return [
            ['value' => DateRange::PRESET_TODAY, 'label' => 'Hoy'],
            ['value' => DateRange::PRESET_LAST_7_DAYS, 'label' => 'Últimos 7 días'],
            ['value' => DateRange::PRESET_LAST_30_DAYS, 'label' => 'Últimos 30 días'],
            ['value' => DateRange::PRESET_THIS_MONTH, 'label' => 'Este mes'],
            ['value' => DateRange::PRESET_PREVIOUS_MONTH, 'label' => 'Mes anterior'],
            ['value' => DateRange::PRESET_CUSTOM, 'label' => 'Personalizado'],
        ];
    }

    // ── KPIs ─────────────────────────────────────────────────────

    /**
     * @param  array<string, array{key:string,label:string,value:string,hint:string,tone:string}>  $kpiByKey
     * @return list<array{key: string, value: string, label: string, note: string, icon: string, tone: string}>
     */
    private function kpis(MaintenanceDashboardViewModel $vm, array $kpiByKey, int $total): array
    {
        $dist = $vm->stateDistribution;
        $inProgress = $dist[Ticket::STATE_IN_PROGRESS] ?? 0;
        $resolved = $dist[Ticket::STATE_RESOLVED] ?? 0;
        $rejected = $dist[Ticket::STATE_REJECTED] ?? 0;

        $pct = static fn (int $n): string => $total > 0
            ? rtrim(rtrim(number_format($n / $total * 100, 1), '0'), '.').'% del total'
            : 'Sin tickets en el periodo';

        return [
            ['key' => 'total', 'value' => (string) $total, 'label' => 'Tickets totales', 'note' => 'Creados en el periodo', 'icon' => 'clipboard-list', 'tone' => 'primary'],
            ['key' => 'in_progress', 'value' => (string) $inProgress, 'label' => self::LABEL_IN_PROGRESS, 'note' => $pct($inProgress), 'icon' => 'loader', 'tone' => 'warning'],
            ['key' => 'resolved', 'value' => (string) $resolved, 'label' => 'Resueltos', 'note' => $pct($resolved), 'icon' => 'circle-check', 'tone' => 'success'],
            ['key' => 'rejected', 'value' => (string) $rejected, 'label' => 'Rechazados', 'note' => $pct($rejected), 'icon' => 'x', 'tone' => 'high'],
            ['key' => 'avg', 'value' => $this->kpiValue($kpiByKey, 'avg_resolution'), 'label' => 'Tiempo prom. resolución', 'note' => 'Cierres dentro del rango', 'icon' => 'clock', 'tone' => 'purple'],
        ];
    }

    // ── Charts ───────────────────────────────────────────────────

    /**
     * @param  array<string, int>  $dist
     * @return array{total: int, segments: list<array{key: string, label: string, count: int, percent: float, start: float, end: float, color: string, tone: string}>}
     */
    private function statusDonut(array $dist, int $total): array
    {
        $bands = [
            ['key' => Ticket::STATE_RESOLVED, 'label' => 'Resueltos', 'color' => '#16a34a', 'tone' => 'success'],
            ['key' => Ticket::STATE_OPEN, 'label' => 'Abiertos', 'color' => '#2563eb', 'tone' => 'primary'],
            ['key' => Ticket::STATE_IN_PROGRESS, 'label' => self::LABEL_IN_PROGRESS, 'color' => '#f59e0b', 'tone' => 'warning'],
            ['key' => Ticket::STATE_REJECTED, 'label' => 'Rechazados', 'color' => '#ef4444', 'tone' => 'high'],
        ];

        $cursor = 0.0;
        $segments = [];
        foreach ($bands as $band) {
            $count = $dist[$band['key']] ?? 0;
            $percent = $total > 0 ? round($count / $total * 100, 1) : 0.0;
            $start = $cursor;
            $cursor += $percent;
            $segments[] = [
                ...$band,
                'count' => $count,
                'percent' => $percent,
                'start' => round($start, 1),
                'end' => round($cursor, 1),
            ];
        }

        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * @param  array<string, int>  $dist
     * @return array{peak: int, items: list<array{label: string, count: int, tone: string}>}
     */
    private function priorityBars(array $dist): array
    {
        $bands = [
            ['key' => 'high', 'label' => 'Alta', 'tone' => 'high'],
            ['key' => 'medium', 'label' => 'Media', 'tone' => 'warning'],
            ['key' => 'low', 'label' => 'Baja', 'tone' => 'success'],
            ['key' => 'critical', 'label' => 'Crítica', 'tone' => 'purple'],
        ];

        $items = array_map(static fn (array $b): array => [
            'label' => $b['label'],
            'count' => $dist[$b['key']] ?? 0,
            'tone' => $b['tone'],
        ], $bands);

        $peak = max(1, ...array_map(static fn (array $i): int => $i['count'], $items));

        return ['peak' => $peak, 'items' => $items];
    }

    /**
     * @return array{total: int, peak: int, items: list<array{name: string, count: int}>}
     */
    private function labBars(MaintenanceDashboardViewModel $vm): array
    {
        $items = array_map(static fn (array $loc): array => [
            'name' => $loc['name'],
            'count' => $loc['total'],
        ], $vm->topLocations);

        return [
            'total' => array_sum(array_column($items, 'count')),
            'peak' => max(1, $vm->topLocationPeak()),
            'items' => $items,
        ];
    }

    // ── Rail ─────────────────────────────────────────────────────

    /**
     * "Tasa de resolución" gauge — resolved / total of the period. There is no
     * SLA table in the system, so no SLA figure is invented.
     *
     * @param  array<string, int>  $dist
     * @return array{value: int, resolved: int, total: int}
     */
    private function closeRate(array $dist, int $total): array
    {
        $resolved = $dist[Ticket::STATE_RESOLVED] ?? 0;

        return [
            'value' => $total > 0 ? (int) round($resolved / $total * 100) : 0,
            'resolved' => $resolved,
            'total' => $total,
        ];
    }

    /**
     * "Tickets creados vs resueltos" series. The daily trend is aggregated into
     * at most MAX_TREND_POINTS contiguous buckets so the chart shows ONE labelled
     * marker per point (clean, like a real chart library) regardless of how long
     * the range is. Short ranges are kept day-by-day. Labels/created/resolved are
     * always the same length (one entry per plotted point).
     *
     * @return array{labels: list<string>, created: list<int>, resolved: list<int>}
     */
    private function trend(MaintenanceDashboardViewModel $vm): array
    {
        $trend = $vm->dailyTrend;
        $count = count($trend);
        if ($count === 0) {
            return ['labels' => [], 'created' => [], 'resolved' => []];
        }

        $maxPoints = 8;
        if ($count <= $maxPoints) {
            return [
                'labels' => array_map(static fn (array $p): string => $p['label'], $trend),
                'created' => array_map(static fn (array $p): int => $p['created'], $trend),
                'resolved' => array_map(static fn (array $p): int => $p['resolved'], $trend),
            ];
        }

        $labels = $created = $resolved = [];
        $size = (int) ceil($count / $maxPoints);
        for ($start = 0; $start < $count; $start += $size) {
            $slice = array_slice($trend, $start, $size);
            $labels[] = $slice[0]['label'];
            $created[] = array_sum(array_column($slice, 'created'));
            $resolved[] = array_sum(array_column($slice, 'resolved'));
        }

        return ['labels' => $labels, 'created' => $created, 'resolved' => $resolved];
    }

    /**
     * Important alerts derived from the reused ViewModel KPIs (urgent active and
     * stale-open counts) plus the global claimable backlog. No SLA heuristic.
     *
     * @param  array<string, array{key:string,label:string,value:string,hint:string,tone:string}>  $kpiByKey
     * @return list<array{count: int, title: string, note: string, tone: string, icon: string}>
     */
    private function alerts(array $kpiByKey, int $available): array
    {
        $critical = $this->kpiInt($kpiByKey, 'critical_active');
        $stale = $this->kpiInt($kpiByKey, 'stale_open');

        $alerts = [];

        if ($critical > 0) {
            $alerts[] = [
                'count' => $critical,
                'title' => $critical === 1 ? 'ticket de alta prioridad sin cerrar' : 'tickets de alta prioridad sin cerrar',
                'note' => 'Críticos o de alta prioridad: atiéndelos primero.',
                'tone' => 'high',
                'icon' => 'alert-triangle',
            ];
        }

        if ($stale > 0) {
            $alerts[] = [
                'count' => $stale,
                'title' => $stale === 1 ? 'ticket sin atender +3 días' : 'tickets sin atender +3 días',
                'note' => 'Abiertos hace más de 3 días sin iniciar.',
                'tone' => 'warning',
                'icon' => 'clock',
            ];
        }

        if ($available > 0) {
            $alerts[] = [
                'count' => $available,
                'title' => $available === 1 ? 'ticket disponible para tomar' : 'tickets disponibles para tomar',
                'note' => 'En la cola global de mantenimiento.',
                'tone' => 'info',
                'icon' => 'inbox',
            ];
        }

        return $alerts;
    }

    // ── Activity feed (scoped: assigned_to = me) ─────────────────

    /**
     * @return list<array{title: string, ref: string, lab: string, status: string, status_label: string, tone: string, icon: string, at: string, text: string}>
     */
    private function activity(string $userId): array
    {
        return $this->mineFeedBase($userId)
            ->with('location')
            ->latest('updated_at')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map(fn (Ticket $t): array => [
                'title' => (string) $t->title,
                'ref' => $this->reference($t),
                'lab' => $t->location?->name ?? 'Sin ubicación',
                'status' => (string) $t->state,
                'status_label' => $this->stateLabel((string) $t->state),
                'tone' => $this->stateTone((string) $t->state),
                'icon' => $this->stateIcon((string) $t->state),
                'at' => LocalTime::format($t->updated_at, 'd/m/Y h:i A') ?? '—',
                'text' => $this->stateNote((string) $t->state),
            ])
            ->all();
    }

    /**
     * @return array{from: int, to: int, total: int, current: int, last: int, noun: string, more_url: string}
     */
    private function activityPager(string $userId): array
    {
        $total = $this->mineFeedBase($userId)->count();

        return $this->pager($total, 'actividades', route('tickets.history'));
    }

    // ── Assignments slice (scoped: assigned_to = me, active) ─────

    /**
     * @return list<array{title: string, lab: string, priority: string, priority_label: string, status: string, status_label: string}>
     */
    private function assignments(string $userId): array
    {
        return $this->mineActiveBase($userId)
            ->with('location')
            ->orderByRaw(self::PRIORITY_ORDER)
            ->latest('created_at')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map(fn (Ticket $t): array => [
                'title' => (string) $t->title,
                'lab' => $t->location?->name ?? 'Sin ubicación',
                'priority' => $this->priorityTone((string) $t->priority),
                'priority_label' => $this->priorityLabel((string) $t->priority),
                'status' => (string) $t->state,
                'status_label' => $this->stateLabel((string) $t->state),
            ])
            ->all();
    }

    /**
     * @return array{from: int, to: int, total: int, current: int, last: int, noun: string, more_url: string}
     */
    private function assignmentsPager(string $userId): array
    {
        $total = $this->mineActiveBase($userId)->count();

        return $this->pager($total, 'asignaciones', route('tickets.assignments'));
    }

    // ── Bottom cards (all real, scoped) ──────────────────────────

    /**
     * @param  array<string, array{key:string,label:string,value:string,hint:string,tone:string}>  $kpiByKey
     * @return list<array{value: string, label: string, note: string, icon: string, tone: string}>
     */
    private function bottomCards(MaintenanceDashboardViewModel $vm, string $userId, DateRange $range, array $kpiByKey): array
    {
        return [
            ['value' => (string) $vm->availableToClaimCount, 'label' => 'Disponibles para tomar', 'note' => 'Abiertos sin asignar en la cola global.', 'icon' => 'inbox', 'tone' => 'primary'],
            ['value' => (string) $this->duplicateCount($userId, $range), 'label' => 'Duplicados en tus tickets', 'note' => 'Detectados por IA en tus tickets del periodo.', 'icon' => 'copy', 'tone' => 'warning'],
            ['value' => (string) $this->kpiInt($kpiByKey, 'assigned_active'), 'label' => 'Pendientes activos', 'note' => 'Abiertos y en progreso a tu cargo.', 'icon' => 'list-checks', 'tone' => 'info'],
        ];
    }

    /**
     * Duplicates flagged on the technician's OWN tickets created in the period.
     * Reuses the embedding's effectiveDuplicates scope (AI flags + human review
     * overrides) and constrains the parent ticket to the ownership scope
     * (assigned_to = me) and the active range — so it never leaks.
     */
    private function duplicateCount(string $userId, DateRange $range): int
    {
        return TicketEmbedding::query()
            ->effectiveDuplicates()
            ->whereHas('ticket', fn (Builder $q): Builder => $q
                ->where('assigned_to', $userId)
                ->whereBetween('created_at', [$range->from, $range->to]))
            ->count();
    }

    // ── Scoped base queries (ownership applied FIRST) ────────────

    /** @return Builder<Ticket> */
    private function mineFeedBase(string $userId): Builder
    {
        return Ticket::query()->where('assigned_to', $userId);
    }

    /** @return Builder<Ticket> */
    private function mineActiveBase(string $userId): Builder
    {
        return Ticket::query()
            ->where('assigned_to', $userId)
            ->whereIn('state', self::ACTIVE_STATES);
    }

    // ── Small shared helpers ─────────────────────────────────────

    /**
     * A page-1 preview pager carrying REAL totals; deeper pages live on the
     * full board (`more_url`), which already has real pagination.
     *
     * @return array{from: int, to: int, total: int, current: int, last: int, noun: string, more_url: string}
     */
    private function pager(int $total, string $noun, string $moreUrl): array
    {
        $to = min(self::FEED_LIMIT, $total);

        return [
            'from' => $total > 0 ? 1 : 0,
            'to' => $to,
            'total' => $total,
            'current' => 1,
            'last' => max(1, (int) ceil($total / self::FEED_LIMIT)),
            'noun' => $noun,
            'more_url' => $moreUrl,
        ];
    }

    /**
     * @return array<string, array{key:string,label:string,value:string,hint:string,tone:string}>
     */
    private function kpisByKey(MaintenanceDashboardViewModel $vm): array
    {
        $byKey = [];
        foreach ($vm->kpis as $kpi) {
            $byKey[$kpi['key']] = $kpi;
        }

        return $byKey;
    }

    /**
     * @param  array<string, array{key:string,label:string,value:string,hint:string,tone:string}>  $kpiByKey
     */
    private function kpiValue(array $kpiByKey, string $key): string
    {
        return $kpiByKey[$key]['value'] ?? 'Sin datos';
    }

    /**
     * Reads a plain-integer KPI value (counts like critical_active / stale_open
     * are stored as integer strings by the reused query).
     *
     * @param  array<string, array{key:string,label:string,value:string,hint:string,tone:string}>  $kpiByKey
     */
    private function kpiInt(array $kpiByKey, string $key): int
    {
        return (int) ($kpiByKey[$key]['value'] ?? 0);
    }

    private function reference(Ticket $ticket): string
    {
        return '#'.strtoupper(substr((string) $ticket->id, 0, 8));
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Abierto',
            Ticket::STATE_IN_PROGRESS => self::LABEL_IN_PROGRESS,
            Ticket::STATE_RESOLVED => 'Resuelto',
            Ticket::STATE_REJECTED => 'Rechazado',
            default => ucfirst($state),
        };
    }

    private function stateNote(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'Pendiente de iniciar',
            Ticket::STATE_IN_PROGRESS => 'En atención',
            Ticket::STATE_RESOLVED => 'Completado',
            Ticket::STATE_REJECTED => 'Rechazado',
            default => '',
        };
    }

    private function stateTone(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'info',
            Ticket::STATE_IN_PROGRESS => 'amber',
            Ticket::STATE_RESOLVED => 'success',
            Ticket::STATE_REJECTED => 'neutral',
            default => 'neutral',
        };
    }

    private function stateIcon(string $state): string
    {
        return match ($state) {
            Ticket::STATE_OPEN => 'inbox',
            Ticket::STATE_IN_PROGRESS => 'loader',
            Ticket::STATE_RESOLVED => 'circle-check',
            Ticket::STATE_REJECTED => 'x',
            default => 'activity',
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
