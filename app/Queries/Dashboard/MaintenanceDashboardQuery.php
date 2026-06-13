<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use App\Support\Tickets\TicketDifficultyScore;
use App\ViewModels\Dashboard\MaintenanceDashboardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the maintenance dashboard data for a single technician and date range.
 *
 * Security contract (mirrors TicketIndexQuery): the role scope is applied
 * FIRST and unconditionally. Every personal metric is bounded by
 * assigned_to = technician; only the "available to claim" figures are global
 * (open, unassigned, claimable) and are surfaced separately.
 *
 * Portability: aggregates use SUM(CASE ...) (not Postgres-only FILTER),
 * durations are computed in PHP from range-bounded rows, and the daily trend
 * is bucketed in PHP, so the same code runs on SQLite (tests) and PostgreSQL.
 */
final class MaintenanceDashboardQuery
{
    /** @var list<string> */
    private const ACTIVE_STATES = [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS];

    /** @var list<string> */
    private const URGENT_PRIORITIES = ['critical', 'high'];

    private const PRIORITY_ORDER = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    private const STALE_DAYS = 3;

    private const TOP_LIMIT = 5;

    private const QUEUE_LIMIT = 10;

    private const ALERTS_LIMIT = 5;

    private const AVAILABLE_LIMIT = 6;

    private const RESOLVED_LIMIT = 6;

    public function __construct(
        private readonly User $maintenance,
        private readonly DateRange $range,
    ) {}

    public static function for(User $maintenance, DateRange $range): MaintenanceDashboardViewModel
    {
        return (new self($maintenance, $range))->build();
    }

    public function build(): MaintenanceDashboardViewModel
    {
        $assignedActive = $this->mine()->whereIn('state', self::ACTIVE_STATES)->count();
        $inProgress = $this->mine()->where('state', Ticket::STATE_IN_PROGRESS)->count();
        $criticalActive = $this->mine()
            ->whereIn('state', self::ACTIVE_STATES)
            ->whereIn('priority', self::URGENT_PRIORITIES)
            ->count();
        $staleOpen = $this->mine()
            ->where('state', Ticket::STATE_OPEN)
            ->where('created_at', '<', CarbonImmutable::now()->subDays(self::STALE_DAYS))
            ->count();
        $resolvedInRange = $this->mine()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$this->range->from, $this->range->to])
            ->count();

        $durations = $this->resolutionDurationsInMinutes();
        $avgResolutionHours = $durations->isEmpty()
            ? null
            : round(((float) $durations->sum()) / $durations->count() / 60, 1);
        $medianResolutionHours = $this->median($durations);
        $firstResponseHours = $this->averageFirstResponseHours();

        $pendingActive = $assignedActive;
        $closeDenominator = $resolvedInRange + $pendingActive;
        $closeRate = $closeDenominator > 0 ? round($resolvedInRange / $closeDenominator * 100, 1) : 0.0;

        $availableToClaimCount = $this->availableForClaim()->count();
        $topLocations = $this->topLocations();
        $topCategories = $this->topCategories();

        $kpis = [
            $this->kpi('assigned_active', 'Asignados activos', (string) $assignedActive,
                'Abiertos y en progreso bajo tu responsabilidad.', 'neutral'),
            $this->kpi('in_progress', 'En progreso', (string) $inProgress,
                'Trabajo tecnico que ya iniciaste.', 'info'),
            $this->kpi('critical_active', 'Criticos / alta', (string) $criticalActive,
                'Prioridad alta o critica todavia sin cerrar.', $criticalActive > 0 ? 'critical' : 'neutral'),
            $this->kpi('stale_open', 'Sin atender +'.self::STALE_DAYS.'d', (string) $staleOpen,
                'Abiertos hace mas de '.self::STALE_DAYS.' dias sin iniciar.', $staleOpen > 0 ? 'warning' : 'neutral'),
            $this->kpi('resolved_range', 'Resueltos en rango', (string) $resolvedInRange,
                'Cierres dentro del periodo elegido.', 'positive'),
            $this->kpi('avg_resolution', 'Promedio resolucion', $this->formatHours($avgResolutionHours),
                'Tiempo medio desde creacion hasta cierre.', 'neutral'),
            $this->kpi('median_resolution', 'Mediana resolucion', $this->formatHours($medianResolutionHours),
                'Valor central, robusto ante casos extremos.', 'neutral'),
            $this->kpi('first_response', 'Primera respuesta', $this->formatHours($firstResponseHours),
                'Promedio hasta pasar el ticket a en progreso.', 'neutral'),
            $this->kpi('close_rate', 'Tasa de cierre', $this->formatPercent($closeRate),
                'Resueltos sobre resueltos mas pendientes activos.', 'neutral'),
        ];

        return new MaintenanceDashboardViewModel(
            range: $this->range,
            technicianName: $this->technicianName(),
            generatedAt: CarbonImmutable::now(),
            kpis: $kpis,
            availableToClaimCount: $availableToClaimCount,
            alerts: $this->alerts(),
            priorityQueue: $this->priorityQueue(),
            availableQueue: $this->availableQueue(),
            topLocations: $topLocations,
            topCategories: $topCategories,
            priorityDistribution: $this->distributionByColumn('priority'),
            stateDistribution: $this->distributionByColumn('state'),
            dailyTrend: $this->dailyTrend(),
            recentResolved: $this->recentResolved(),
            recommendations: $this->recommendations($criticalActive, $staleOpen, $availableToClaimCount, $resolvedInRange, $topLocations),
        );
    }

    /** @return Builder<Ticket> */
    private function mine(): Builder
    {
        return Ticket::query()->where('assigned_to', $this->userId());
    }

    private function mineTable(): QueryBuilder
    {
        return DB::table('tickets')->where('assigned_to', $this->userId());
    }

    /** @return Builder<Ticket> */
    private function availableForClaim(): Builder
    {
        return Ticket::availableForClaim();
    }

    /** @return SupportCollection<int,float> */
    private function resolutionDurationsInMinutes(): SupportCollection
    {
        return $this->mine()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$this->range->from, $this->range->to])
            ->get(['created_at', 'resolved_at'])
            ->map(fn (Ticket $ticket): ?float => $ticket->created_at !== null && $ticket->resolved_at !== null
                ? (float) $ticket->created_at->diffInMinutes($ticket->resolved_at)
                : null)
            ->filter(fn (?float $minutes): bool => $minutes !== null)
            ->values();
    }

    /**
     * @param  SupportCollection<int,float>  $minutes
     */
    private function median(SupportCollection $minutes): ?float
    {
        if ($minutes->isEmpty()) {
            return null;
        }

        $sorted = $minutes->sort()->values();
        $count = $sorted->count();
        $mid = intdiv($count, 2);

        $median = $count % 2 === 0
            ? (((float) $sorted[$mid - 1]) + ((float) $sorted[$mid])) / 2
            : (float) $sorted[$mid];

        return round($median / 60, 1);
    }

    private function averageFirstResponseHours(): ?float
    {
        $rows = DB::table('state_history')
            ->join('tickets', 'tickets.id', '=', 'state_history.ticket_id')
            ->where('tickets.assigned_to', $this->userId())
            ->where('state_history.to_state', Ticket::STATE_IN_PROGRESS)
            ->whereBetween('state_history.created_at', [$this->range->from, $this->range->to])
            ->orderBy('state_history.created_at')
            ->get([
                'state_history.ticket_id as ticket_id',
                'tickets.created_at as ticket_created_at',
                'state_history.created_at as responded_at',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $minutesByTicket = [];
        foreach ($rows as $row) {
            $ticketId = (string) $row->ticket_id;
            if (array_key_exists($ticketId, $minutesByTicket)) {
                continue;
            }

            $created = CarbonImmutable::parse((string) $row->ticket_created_at);
            $responded = CarbonImmutable::parse((string) $row->responded_at);
            $minutesByTicket[$ticketId] = (float) $created->diffInMinutes($responded);
        }

        return round(array_sum($minutesByTicket) / count($minutesByTicket) / 60, 1);
    }

    /**
     * @return list<array{location_id:string,name:string,meta:string,total:int,open:int,in_progress:int,resolved:int}>
     */
    private function topLocations(): array
    {
        $rows = $this->mineTable()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->selectRaw(
                'location_id, COUNT(*) as total, '
                ."SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as open_count, "
                ."SUM(CASE WHEN state = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count, "
                ."SUM(CASE WHEN state = 'resolved' THEN 1 ELSE 0 END) as resolved_count"
            )
            ->groupBy('location_id')
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get();

        $locations = Location::query()
            ->whereIn('id', $rows->pluck('location_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($locations): array {
            $location = $locations->get($row->location_id);
            $meta = $location !== null
                ? trim(implode(' · ', array_filter([(string) $location->building, (string) $location->room_code])))
                : '';

            return [
                'location_id' => (string) $row->location_id,
                'name' => $location->name ?? 'Ubicacion eliminada',
                'meta' => $meta,
                'total' => (int) $row->total,
                'open' => (int) $row->open_count,
                'in_progress' => (int) $row->in_progress_count,
                'resolved' => (int) $row->resolved_count,
            ];
        })->all();
    }

    /**
     * @return list<array{category_id:string,name:string,total:int}>
     */
    private function topCategories(): array
    {
        $rows = $this->mineTable()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->get();

        $categories = Category::query()
            ->whereIn('id', $rows->pluck('category_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'category_id' => (string) $row->category_id,
            'name' => $categories->get($row->category_id)->name ?? 'Categoria eliminada',
            'total' => (int) $row->total,
        ])->all();
    }

    /**
     * @return array<string,int>
     */
    private function distributionByColumn(string $column): array
    {
        return $this->mineTable()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->selectRaw("{$column}, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($total): int => (int) $total)
            ->toArray();
    }

    /**
     * @return list<array{date:string,label:string,created:int,resolved:int}>
     */
    private function dailyTrend(): array
    {
        // Use SQL-level DATE() grouping instead of plucking every timestamp into PHP.
        // DATE() is portable across SQLite (tests) and PostgreSQL.
        $createdByDay = $this->mineTable()
            ->whereBetween('created_at', [$this->range->from, $this->range->to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $resolvedByDay = $this->mineTable()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$this->range->from, $this->range->to])
            ->selectRaw('DATE(resolved_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(resolved_at)')
            ->pluck('total', 'day')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $trend = [];
        $cursor = $this->range->from->startOfDay();
        $end = $this->range->to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $trend[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'created' => $createdByDay[$key] ?? 0,
                'resolved' => $resolvedByDay[$key] ?? 0,
            ];
            $cursor = $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @return EloquentCollection<int,Ticket>
     */
    private function alerts(): EloquentCollection
    {
        return $this->mine()
            ->whereIn('state', self::ACTIVE_STATES)
            ->whereIn('priority', self::URGENT_PRIORITIES)
            ->with(['location', 'category'])
            ->orderByRaw(self::PRIORITY_ORDER)
            ->oldest('created_at')
            ->limit(self::ALERTS_LIMIT)
            ->get();
    }

    /**
     * @return list<array{ticket:Ticket,score:int,band:string,bandLabel:string}>
     */
    private function priorityQueue(): array
    {
        $tickets = $this->mine()
            ->whereIn('state', self::ACTIVE_STATES)
            ->with(['location', 'category', 'embedding'])
            ->withCount('media')
            ->get();

        $recurrence = $this->recurrenceMap($tickets);

        return $tickets
            ->map(fn (Ticket $ticket): array => [
                'ticket' => $ticket,
                'score' => $this->scoreFor($ticket, $recurrence),
            ])
            ->sortByDesc('score')
            ->take(self::QUEUE_LIMIT)
            ->map(function (array $row): array {
                $ticket = $row['ticket'];
                $urgent = in_array($ticket->priority, self::URGENT_PRIORITIES, true);

                return [
                    'ticket' => $ticket,
                    'score' => $row['score'],
                    'band' => $urgent ? 'urgent' : 'standard',
                    'bandLabel' => $urgent ? 'Atencion inmediata' : 'Pendiente',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{ticket:Ticket,score:int}>
     */
    private function availableQueue(): array
    {
        $tickets = $this->availableForClaim()
            ->with(['location', 'category', 'embedding'])
            ->withCount('media')
            ->oldest('created_at')
            ->limit(self::AVAILABLE_LIMIT)
            ->get();

        $recurrence = $this->recurrenceMap($tickets);

        return $tickets
            ->map(fn (Ticket $ticket): array => [
                'ticket' => $ticket,
                'score' => $this->scoreFor($ticket, $recurrence),
            ])
            ->all();
    }

    /**
     * @return EloquentCollection<int,Ticket>
     */
    private function recentResolved(): EloquentCollection
    {
        return $this->mine()
            ->where('state', Ticket::STATE_RESOLVED)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$this->range->from, $this->range->to])
            ->with(['location', 'category'])
            ->latest('resolved_at')
            ->limit(self::RESOLVED_LIMIT)
            ->get();
    }

    /**
     * @param  EloquentCollection<int,Ticket>  $tickets
     * @return array<string,int>
     */
    private function recurrenceMap(EloquentCollection $tickets): array
    {
        if ($tickets->isEmpty()) {
            return [];
        }

        $locationIds = $tickets->pluck('location_id')->filter()->unique()->values()->all();
        $categoryIds = $tickets->pluck('category_id')->filter()->unique()->values()->all();

        if ($locationIds === [] || $categoryIds === []) {
            return [];
        }

        $map = [];

        LocationIncidentHistory::query()
            ->whereIn('location_id', $locationIds)
            ->whereIn('category_id', $categoryIds)
            ->get(['location_id', 'category_id', 'recurrence_count'])
            ->each(function (LocationIncidentHistory $history) use (&$map): void {
                $map[$history->location_id.'|'.$history->category_id] = (int) $history->recurrence_count;
            });

        return $map;
    }

    /**
     * @param  array<string,int>  $recurrence
     */
    private function scoreFor(Ticket $ticket, array $recurrence): int
    {
        $daysOpen = $ticket->created_at !== null
            ? (int) floor($ticket->created_at->diffInDays(CarbonImmutable::now()))
            : 0;

        $embedding = $ticket->embedding;

        return TicketDifficultyScore::calculate(
            (string) $ticket->priority,
            $daysOpen,
            $recurrence[$ticket->location_id.'|'.$ticket->category_id] ?? 0,
            $embedding !== null && $embedding->effective_duplicate,
            ((int) $ticket->getAttribute('media_count')) > 0,
        );
    }

    /**
     * @param  list<array{location_id:string,name:string,meta:string,total:int,open:int,in_progress:int,resolved:int}>  $topLocations
     * @return list<string>
     */
    private function recommendations(int $critical, int $stale, int $available, int $resolved, array $topLocations): array
    {
        $items = [];

        if ($critical > 0) {
            $items[] = "Tienes {$critical} ticket(s) de alta prioridad o criticos sin cerrar. Atiendelos primero.";
        }

        if ($stale > 0) {
            $items[] = "Hay {$stale} ticket(s) abiertos hace mas de ".self::STALE_DAYS.' dias sin iniciar.';
        }

        if (($topLocations[0]['total'] ?? 0) >= 2) {
            $leader = $topLocations[0];
            $items[] = "La ubicacion {$leader['name']} concentra {$leader['total']} incidencias en el periodo.";
        }

        if ($available > 0) {
            $items[] = "Quedan {$available} ticket(s) disponibles para tomar en la cola global.";
        }

        if ($resolved === 0) {
            $items[] = 'No registras cierres dentro del periodo seleccionado.';
        }

        if ($items === []) {
            $items[] = 'Tu cola esta bajo control. Manten el ritmo de cierres.';
        }

        return array_slice($items, 0, 4);
    }

    /**
     * @return array{key:string,label:string,value:string,hint:string,tone:string}
     */
    private function kpi(string $key, string $label, string $value, string $hint, string $tone): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
        ];
    }

    private function formatHours(?float $hours): string
    {
        if ($hours === null) {
            return 'Sin datos';
        }

        return $this->trimNumber($hours).' h';
    }

    private function formatPercent(float $value): string
    {
        return $this->trimNumber($value).' %';
    }

    private function trimNumber(float $value): string
    {
        $formatted = number_format(round($value, 1), 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function technicianName(): string
    {
        $name = trim((string) ($this->maintenance->name ?? '').' '.(string) ($this->maintenance->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $email = (string) ($this->maintenance->email ?? '');

        return $email !== '' ? $email : $this->userId();
    }

    private function userId(): string
    {
        return (string) $this->maintenance->id;
    }
}
