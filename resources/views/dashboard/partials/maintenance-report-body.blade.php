@php
    /** @var \App\ViewModels\Dashboard\MaintenanceDashboardViewModel $vm */
    $priorityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
    $stateLabels = ['open' => 'Abierto', 'in_progress' => 'En progreso', 'resolved' => 'Resuelto', 'rejected' => 'Rechazado'];
    $range = $vm->range;
    $locationPeak = $vm->topLocationPeak();
    $categoryPeak = $vm->topCategoryPeak();
    $priorityPeak = count($vm->priorityDistribution) > 0 ? max($vm->priorityDistribution) : 0;
@endphp

<div class="report">

    {{-- ===== HEADER ===== --}}
    <div class="r-head">
        <p class="r-kicker">Incidex · Informe de mantenimiento</p>
        <h1 class="r-title">Consola de mantenimiento</h1>
        <table class="r-meta">
            <tr>
                <td class="k">Tecnico</td>
                <td>{{ $vm->technicianName }}</td>
                <td class="k">Periodo</td>
                <td>{{ $range->presetLabel() }} ({{ $range->fromDate() }} al {{ $range->toDate() }})</td>
            </tr>
            <tr>
                <td class="k">Dias</td>
                <td>{{ $range->days() }}</td>
                <td class="k">Generado</td>
                <td>{{ $vm->generatedAt->format('Y-m-d H:i') }}</td>
            </tr>
        </table>
    </div>

    {{-- ===== RESUMEN EJECUTIVO ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Resumen ejecutivo</h2>
        <ul class="reco">
            @foreach ($vm->recommendations as $recommendation)
                <li>{{ $recommendation }}</li>
            @endforeach
        </ul>
    </div>

    {{-- ===== INDICADORES ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Indicadores del periodo</h2>
        <table class="kpi-grid">
            @foreach (array_chunk($vm->kpis, 3) as $row)
                <tr>
                    @foreach ($row as $kpi)
                        <td class="kpi-cell">
                            <p class="kpi-label">{{ $kpi['label'] }}</p>
                            <p class="kpi-val">{{ $kpi['value'] }}</p>
                            <p class="kpi-hint">{{ $kpi['hint'] }}</p>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>

    {{-- ===== LABORATORIOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Laboratorios con mas incidencias</h2>
        <p class="r-note">Solo tickets asignados a ti dentro del periodo.</p>
        @if (count($vm->topLocations) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th>Ubicacion</th>
                        <th style="width:120px">Carga</th>
                        <th style="width:55px">Total</th>
                        <th style="width:60px">Abiertos</th>
                        <th style="width:70px">En curso</th>
                        <th style="width:65px">Cerrados</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vm->topLocations as $location)
                        <tr>
                            <td>
                                <strong>{{ $location['name'] }}</strong>
                                @if ($location['meta'] !== '')<br><span style="color:#94a3b8;font-size:8.5px">{{ $location['meta'] }}</span>@endif
                            </td>
                            <td>
                                <div class="bar"><div style="width: {{ $locationPeak > 0 ? max(3, (int) round($location['total'] / $locationPeak * 100)) : 0 }}%"></div></div>
                            </td>
                            <td class="num">{{ $location['total'] }}</td>
                            <td class="num">{{ $location['open'] }}</td>
                            <td class="num">{{ $location['in_progress'] }}</td>
                            <td class="num">{{ $location['resolved'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin incidencias asignadas en el periodo.</p>
        @endif
    </div>

    {{-- ===== CATEGORIAS + PRIORIDAD ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Categorias mas frecuentes</h2>
        @if (count($vm->topCategories) > 0)
            <table class="dt">
                <thead>
                    <tr><th>Categoria</th><th style="width:160px">Frecuencia</th><th style="width:55px">Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($vm->topCategories as $category)
                        <tr>
                            <td><strong>{{ $category['name'] }}</strong></td>
                            <td><div class="bar"><div style="width: {{ $categoryPeak > 0 ? max(3, (int) round($category['total'] / $categoryPeak * 100)) : 0 }}%"></div></div></td>
                            <td class="num">{{ $category['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin categorias registradas en el periodo.</p>
        @endif
    </div>

    <div class="r-section">
        <h2 class="r-h2">Distribucion por prioridad</h2>
        @if ($priorityPeak > 0)
            <table class="dt">
                <tbody>
                    @foreach (['critical', 'high', 'medium', 'low'] as $key)
                        @if (($vm->priorityDistribution[$key] ?? 0) > 0)
                            <tr>
                                <td style="width:90px"><span class="pill pill-{{ $key }}">{{ $priorityLabels[$key] }}</span></td>
                                <td><div class="bar"><div style="width: {{ max(3, (int) round(($vm->priorityDistribution[$key] / $priorityPeak) * 100)) }}%"></div></div></td>
                                <td class="num" style="width:55px">{{ $vm->priorityDistribution[$key] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin datos de prioridad en el periodo.</p>
        @endif
    </div>

    {{-- ===== PENDIENTES CRITICOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Pendientes criticos y de alta prioridad</h2>
        @if ($vm->alerts->isNotEmpty())
            <table class="dt">
                <thead>
                    <tr><th>Ticket</th><th>Ubicacion</th><th style="width:70px">Estado</th><th style="width:70px">Prioridad</th></tr>
                </thead>
                <tbody>
                    @foreach ($vm->alerts as $ticket)
                        <tr>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $ticket->location?->name ?? 'Sin ubicacion' }}</td>
                            <td><span class="pill pill-{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span></td>
                            <td><span class="pill pill-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No tienes pendientes criticos o de alta prioridad. Buen trabajo.</p>
        @endif
    </div>

    {{-- ===== TICKETS RESUELTOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Tickets resueltos en el periodo</h2>
        @if ($vm->recentResolved->isNotEmpty())
            <table class="dt">
                <thead>
                    <tr><th>Ticket</th><th>Ubicacion</th><th>Categoria</th><th style="width:70px">Prioridad</th></tr>
                </thead>
                <tbody>
                    @foreach ($vm->recentResolved as $ticket)
                        <tr>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                            <td>{{ $ticket->category?->name ?? 'N/A' }}</td>
                            <td><span class="pill pill-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No registras cierres dentro del periodo seleccionado.</p>
        @endif
    </div>

    {{-- ===== DEFINICIONES ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Definiciones</h2>
        <ul class="defs">
            <li><b>Resueltos en rango:</b> tickets asignados a ti con fecha de resolucion dentro del periodo.</li>
            <li><b>Promedio / mediana de resolucion:</b> tiempo desde creacion hasta cierre, en horas.</li>
            <li><b>Primera respuesta:</b> tiempo promedio hasta que el ticket pasa a en progreso.</li>
            <li><b>Tasa de cierre:</b> resueltos en rango / (resueltos en rango + pendientes activos).</li>
            <li><b>Distribuciones y laboratorios:</b> tickets asignados a ti creados dentro del periodo.</li>
        </ul>
    </div>

    <div class="foot">
        Informe generado por Incidex el {{ $vm->generatedAt->format('Y-m-d H:i') }} para {{ $vm->technicianName }}.
        Metricas personales limitadas a tickets asignados a este tecnico.
    </div>

</div>
