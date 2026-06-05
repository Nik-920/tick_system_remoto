@extends('layouts.app')

@section('title', 'Consola de Mantenimiento')

@php
    /** @var \App\ViewModels\Dashboard\MaintenanceDashboardViewModel $vm */
    $range = $vm->range;
    $exportParams = $range->toQueryParams();
    $hasReportRoute = \Illuminate\Support\Facades\Route::has('dashboard.maintenance.report.pdf');
@endphp

@section('content')
    <div class="role-dashboard role-dashboard-maintenance space-y-6">

        {{-- ===== HERO ===== --}}
        <section class="role-hero role-hero-maintenance panel panel-pad overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Panel maintenance</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900">Consola de mantenimiento</h1>
                    <p class="mt-2 text-slate-700 text-sm md:text-base">Prioriza tu cola, vigila tiempos de respuesta y cierra con calidad. Decide qué atender primero.</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.12em] text-slate-500">{{ $roleLabel }} · {{ $vm->technicianName }}</p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-right">
                    <p class="text-xs uppercase tracking-[0.1em] font-semibold text-amber-700">Periodo analizado</p>
                    <p class="text-lg font-black text-slate-900 mt-1">{{ $range->presetLabel() }}</p>
                    <p class="text-xs text-slate-600 mt-0.5">{{ $range->fromDate() }} → {{ $range->toDate() }} · {{ $range->days() }} d</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a href="{{ route('tickets.index', ['assignment' => 'mine']) }}" class="btn-primary">Mis tickets asignados</a>
                <a href="{{ route('tickets.available') }}" class="btn-secondary">Cola disponible</a>
                <a href="{{ route('tickets.create') }}" class="btn-secondary">Registrar incidencia</a>
            </div>
        </section>

        {{-- ===== TOOLBAR: RANGO + EXPORT ===== --}}
        <section class="panel panel-pad">
            <div class="maint-toolbar">
                <form method="GET" action="{{ route('dashboard.index') }}" role="search" aria-label="Filtrar metricas por rango de fecha">
                    <label class="maint-field">
                        <span>Rango</span>
                        <select name="preset" class="maint-input" aria-label="Rango predefinido">
                            <option value="today" @selected($range->preset === 'today')>Hoy</option>
                            <option value="last_7_days" @selected($range->preset === 'last_7_days')>Ultimos 7 dias</option>
                            <option value="last_30_days" @selected($range->preset === 'last_30_days')>Ultimos 30 dias</option>
                            <option value="this_month" @selected($range->preset === 'this_month')>Este mes</option>
                            <option value="previous_month" @selected($range->preset === 'previous_month')>Mes anterior</option>
                            <option value="custom" @selected($range->isCustom())>Personalizado</option>
                        </select>
                    </label>
                    <label class="maint-field">
                        <span>Desde</span>
                        <input type="date" name="from" class="maint-input" value="{{ $range->isCustom() ? $range->fromDate() : '' }}" max="{{ now()->format('Y-m-d') }}">
                    </label>
                    <label class="maint-field">
                        <span>Hasta</span>
                        <input type="date" name="to" class="maint-input" value="{{ $range->isCustom() ? $range->toDate() : '' }}" max="{{ now()->format('Y-m-d') }}">
                    </label>
                    <div class="maint-toolbar-actions">
                        <button type="submit" class="btn-primary">Aplicar</button>
                        <a href="{{ route('dashboard.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </form>

                <div class="maint-toolbar-actions" style="margin-left:auto">
                    @if ($hasReportRoute)
                        <a href="{{ route('dashboard.maintenance.report.pdf', $exportParams) }}" class="maint-export">
                            Exportar informe PDF
                        </a>
                    @else
                        <span class="maint-export" aria-disabled="true" title="Disponible proximamente">Exportar informe PDF</span>
                    @endif
                </div>
            </div>

            @error('to')
                <p class="mt-3 text-sm font-semibold" style="color: var(--color-danger)">{{ $message }}</p>
            @enderror
            @error('from')
                <p class="mt-2 text-sm font-semibold" style="color: var(--color-danger)">{{ $message }}</p>
            @enderror

            <p class="maint-range-note mt-3">
                Mostrando actividad del <strong>{{ $range->fromDate() }}</strong> al <strong>{{ $range->toDate() }}</strong>.
                Las metricas personales solo incluyen tickets asignados a ti; los tickets disponibles son una cola global reclamable.
            </p>
        </section>

        {{-- ===== MI CARGA: KPIs ===== --}}
        <section>
            <p class="maint-band-title">Mi carga</p>
            <div class="role-kpi-grid grid grid-cols-2 xl:grid-cols-5 gap-4">
                @foreach ($vm->kpis as $kpi)
                    <article class="role-kpi-card kpi--{{ $kpi['tone'] }}">
                        <p>{{ $kpi['label'] }}</p>
                        <p>{{ $kpi['value'] }}</p>
                        <p>{{ $kpi['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ===== ALERTAS DE ATENCIÓN INMEDIATA ===== --}}
        @if ($vm->alerts->isNotEmpty())
            <section class="role-section role-section-panel">
                <header>
                    <h2>Atencion inmediata</h2>
                    <p>Tickets criticos o de alta prioridad que no deberian esperar.</p>
                </header>
                <div class="p-4 maint-queue">
                    @foreach ($vm->alerts as $ticket)
                        <article class="maint-queue-item maint-queue-item--urgent">
                            <span class="maint-rank">!</span>
                            <div class="maint-queue-body">
                                <p class="maint-queue-title">{{ $ticket->title }}</p>
                                <div class="maint-queue-meta">
                                    <span>{{ $ticket->location?->name ?? 'Sin ubicacion' }}</span>
                                    <span>·</span>
                                    <span>{{ $ticket->category?->name ?? 'Sin categoria' }}</span>
                                    <span>·</span>
                                    <span>{{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="maint-queue-side">
                                <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                <a href="{{ route('tickets.show', $ticket) }}" class="maint-queue-action">Gestionar</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ===== COLA OPERATIVA PRIORIZADA + DISPONIBLES ===== --}}
        <div class="maint-grid-2">
            {{-- Cola priorizada (mía) --}}
            <section class="role-section">
                <header>
                    <h2>Cola operativa priorizada</h2>
                    <p>Tus tickets activos ordenados por urgencia calculada (prioridad, antiguedad, recurrencia).</p>
                </header>
                <div class="p-4">
                    @if (count($vm->priorityQueue) > 0)
                        <div class="maint-queue">
                            @foreach ($vm->priorityQueue as $index => $row)
                                @php($ticket = $row['ticket'])
                                <article class="maint-queue-item {{ $row['band'] === 'urgent' ? 'maint-queue-item--urgent' : '' }}">
                                    <span class="maint-rank">{{ $index + 1 }}</span>
                                    <div class="maint-queue-body">
                                        <p class="maint-queue-title">{{ $ticket->title }}</p>
                                        <div class="maint-queue-meta">
                                            <span class="dash-state-badge dash-state-badge--{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                            <span>{{ $ticket->location?->name ?? 'Sin ubicacion' }}</span>
                                            <span>·</span>
                                            <span>{{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                    <div class="maint-queue-side">
                                        <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                        <span class="maint-score" title="Puntaje de urgencia">★ {{ $row['score'] }}</span>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="maint-queue-action">Gestionar</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p class="dash-empty">No tienes tickets activos en cola. Buen momento para revisar la cola disponible.</p>
                    @endif
                </div>
            </section>

            {{-- Disponibles para tomar (global) --}}
            <section class="role-section">
                <header>
                    <h2>Tickets disponibles para tomar</h2>
                    <p>Cola global de incidencias abiertas sin asignar. Cualquier tecnico puede reclamarlas.</p>
                </header>
                <div class="p-4">
                    <div class="maint-available">
                        <div class="maint-available-head">
                            <span class="maint-available-count">{{ $vm->availableToClaimCount }}</span>
                            <span class="text-sm font-semibold text-amber-800">disponibles ahora mismo</span>
                        </div>
                        @if (count($vm->availableQueue) > 0)
                            <div class="maint-queue">
                                @foreach ($vm->availableQueue as $row)
                                    @php($ticket = $row['ticket'])
                                    <article class="maint-queue-item">
                                        <span class="maint-rank">+</span>
                                        <div class="maint-queue-body">
                                            <p class="maint-queue-title">{{ $ticket->title }}</p>
                                            <div class="maint-queue-meta">
                                                <span>{{ $ticket->location?->name ?? 'Sin ubicacion' }}</span>
                                                <span>·</span>
                                                <span>{{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                        <div class="maint-queue-side">
                                            <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                            <a href="{{ route('tickets.show', $ticket) }}" class="maint-queue-action">Revisar y tomar</a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="dash-empty">No hay tickets disponibles para tomar en este momento.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- ===== TOP LABORATORIOS + TOP CATEGORÍAS ===== --}}
        <div class="maint-grid-2">
            <section class="role-section">
                <header>
                    <h2>Laboratorios con mas incidencias</h2>
                    <p>Donde se concentra tu trabajo en el periodo. Solo tus tickets asignados.</p>
                </header>
                <div class="p-4">
                    @if (count($vm->topLocations) > 0)
                        @php($locationPeak = $vm->topLocationPeak())
                        <div class="maint-bars">
                            @foreach ($vm->topLocations as $location)
                                <div class="maint-bar-row">
                                    <div class="maint-bar-label">
                                        <span class="name">{{ $location['name'] }}</span>
                                        <span class="meta">{{ $location['total'] }} · {{ $location['resolved'] }} cerr.</span>
                                    </div>
                                    <div class="maint-bar-track">
                                        <div class="maint-bar-fill" style="width: {{ $locationPeak > 0 ? max(2, (int) round($location['total'] / $locationPeak * 100)) : 0 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="dash-empty">Sin incidencias asignadas en el periodo seleccionado.</p>
                    @endif
                </div>
            </section>

            <section class="role-section">
                <header>
                    <h2>Categorias mas frecuentes</h2>
                    <p>Tipos de incidencia que mas atiendes en el periodo.</p>
                </header>
                <div class="p-4">
                    @if (count($vm->topCategories) > 0)
                        @php($categoryPeak = $vm->topCategoryPeak())
                        <div class="maint-bars">
                            @foreach ($vm->topCategories as $category)
                                <div class="maint-bar-row">
                                    <div class="maint-bar-label">
                                        <span class="name">{{ $category['name'] }}</span>
                                        <span class="meta">{{ $category['total'] }}</span>
                                    </div>
                                    <div class="maint-bar-track">
                                        <div class="maint-bar-fill" style="width: {{ $categoryPeak > 0 ? max(2, (int) round($category['total'] / $categoryPeak * 100)) : 0 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="dash-empty">Sin categorias registradas en el periodo seleccionado.</p>
                    @endif
                </div>
            </section>
        </div>

        {{-- ===== DISTRIBUCIÓN PRIORIDAD + TENDENCIA ===== --}}
        <div class="maint-grid-2">
            <section class="role-section">
                <header>
                    <h2>Distribucion por prioridad</h2>
                    <p>Composicion de los tickets que te llegaron en el periodo.</p>
                </header>
                <div class="p-4">
                    @php($priorityPeak = count($vm->priorityDistribution) > 0 ? max($vm->priorityDistribution) : 0)
                    @if ($priorityPeak > 0)
                        <div class="maint-bars">
                            @foreach (['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'] as $key => $label)
                                @if (($vm->priorityDistribution[$key] ?? 0) > 0)
                                    <div class="maint-bar-row">
                                        <div class="maint-bar-label">
                                            <span class="name">{{ $label }}</span>
                                            <span class="meta">{{ $vm->priorityDistribution[$key] }}</span>
                                        </div>
                                        <div class="maint-bar-track">
                                            <div class="maint-bar-fill maint-bar-fill--{{ $key }}" style="width: {{ max(2, (int) round(($vm->priorityDistribution[$key] / $priorityPeak) * 100)) }}%"></div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="dash-empty">Sin datos de prioridad en el periodo.</p>
                    @endif
                </div>
            </section>

            <section class="role-section">
                <header>
                    <h2>Creados vs resueltos por dia</h2>
                    <p>Tu ritmo de ingreso y cierre dentro del periodo.</p>
                </header>
                <div class="p-4">
                    @php($trendPeak = $vm->trendPeak())
                    @if ($trendPeak > 0)
                        <div class="maint-trend" role="img" aria-label="Tendencia diaria de tickets creados y resueltos">
                            @foreach ($vm->dailyTrend as $point)
                                <div class="maint-trend-day" title="{{ $point['date'] }}: {{ $point['created'] }} creados, {{ $point['resolved'] }} resueltos">
                                    @if ($point['created'] > 0)
                                        <span class="maint-trend-bar maint-trend-bar--created" style="height: {{ max(6, (int) round(($point['created'] / $trendPeak) * 100)) }}%"></span>
                                    @endif
                                    @if ($point['resolved'] > 0)
                                        <span class="maint-trend-bar maint-trend-bar--resolved" style="height: {{ max(6, (int) round(($point['resolved'] / $trendPeak) * 100)) }}%"></span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="maint-legend">
                            <span><i class="maint-dot-created"></i> Creados</span>
                            <span><i class="maint-dot-resolved"></i> Resueltos</span>
                        </div>
                    @else
                        <p class="dash-empty">Sin actividad registrada en el periodo seleccionado.</p>
                    @endif
                </div>
            </section>
        </div>

        {{-- ===== CIERRES RECIENTES ===== --}}
        <section class="role-section">
            <header>
                <h2>Cierres recientes</h2>
                <p>Tickets que resolviste dentro del periodo seleccionado.</p>
            </header>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Titulo</th>
                            <th>Ubicacion</th>
                            <th>Categoria</th>
                            <th>Prioridad</th>
                            <th>Resuelto</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vm->recentResolved as $ticket)
                            <tr>
                                <td>{{ $ticket->title }}</td>
                                <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                                <td>{{ $ticket->category?->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">
                                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td>{{ $ticket->resolved_at?->diffForHumans() ?? 'N/A' }}</td>
                                <td><a href="{{ route('tickets.show', $ticket) }}">Ver</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="dash-empty">No registras cierres dentro del periodo seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ===== RECOMENDACIONES ===== --}}
        <section class="role-section role-section-panel">
            <header>
                <h2>Recomendaciones</h2>
                <p>Lecturas rapidas para decidir tu proximo paso.</p>
            </header>
            <div class="p-4">
                <ul class="maint-reco">
                    @foreach ($vm->recommendations as $recommendation)
                        <li>{{ $recommendation }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

    </div>
@endsection
