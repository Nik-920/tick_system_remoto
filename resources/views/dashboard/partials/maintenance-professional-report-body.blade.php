@php
    /** @var \App\ViewModels\Dashboard\MaintenanceReportViewModel $vm */
    $priorityLabels = ['critical' => 'Crítica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
    $stateLabels = ['open' => 'Abierto', 'in_progress' => 'En progreso', 'resolved' => 'Resuelto', 'rejected' => 'Rechazado', 'cancelled' => 'Cancelado'];
    $period = $vm->period;
    $time = $vm->timeMetrics;
    $evidence = $vm->evidenceSummary;
    $queue = $vm->globalQueueContext;
    $appendix = $vm->appendixTickets;
@endphp

<div class="report">

    {{-- ===== 1. PORTADA ===== --}}
    <div class="cover">
        <div class="cover-kicker">INCIDEX · Sistema de Gestión de Incidencias</div>
        <h1 class="cover-title">Informe Profesional de Mantenimiento</h1>
        <p class="cover-subtitle">Reporte operativo del técnico para entrega a su superior</p>

        <table class="cover-meta">
            <tr><td class="k">Técnico</td><td>{{ $vm->technician['name'] }}</td></tr>
            <tr><td class="k">Correo</td><td>{{ $vm->technician['email'] }}</td></tr>
            <tr><td class="k">Periodo del informe</td><td>{{ $period->presetLabel() }} ({{ $period->fromDate() }} al {{ $period->toDate() }}, {{ $period->days() }} días)</td></tr>
            <tr><td class="k">Fecha de generación</td><td>{{ $vm->generatedAt->format('Y-m-d H:i') }}</td></tr>
            <tr><td class="k">Alcance</td><td>Métricas personales limitadas a tickets asignados a este técnico. La cola global del área se incluye únicamente como contexto.</td></tr>
        </table>

        <p class="cover-note">
            Este informe distingue dos relojes: <b>snapshot</b> (estado actual al momento de generar)
            y <b>periodo</b> (datos acotados al rango seleccionado). Cada métrica declara su reloj.
            Las señales de IA sobre posibles duplicados son alertas operativas y no miden la productividad del técnico.
        </p>
    </div>

    {{-- ===== 2. RESUMEN EJECUTIVO ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Resumen ejecutivo</h2>
        <table class="headline-grid">
            <tr>
                @foreach ($vm->executiveSummary['headlines'] as $headline)
                    <td class="headline-cell">
                        <p class="headline-val">{{ $headline['value'] }}</p>
                        <p class="headline-label">{{ $headline['label'] }}</p>
                    </td>
                @endforeach
            </tr>
        </table>
        <ul class="narrative">
            @foreach ($vm->executiveSummary['narrative'] as $sentence)
                <li>{{ $sentence }}</li>
            @endforeach
        </ul>
    </div>

    {{-- ===== 3. ALCANCE DEL INFORME ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Alcance del informe</h2>
        <p class="r-note">Universos de tickets considerados y el reloj de cada uno.</p>
        <table class="dt">
            <thead>
                <tr><th style="width:28px">Cód.</th><th>Universo</th><th style="width:70px">Reloj</th><th style="width:50px">Conteo</th><th>Descripción</th></tr>
            </thead>
            <tbody>
                @foreach ($vm->scope as $universe)
                    <tr>
                        <td><strong>{{ $universe->code }}</strong></td>
                        <td>{{ $universe->label }}</td>
                        <td><span class="clock-tag clock-{{ $universe->clock === 'snapshot' ? 'snapshot' : 'periodo' }}">{{ $universe->clock }}</span></td>
                        <td class="num">{{ $universe->count }}</td>
                        <td class="muted">{{ $universe->description }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ===== 4. INDICADORES CLAVE ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Indicadores clave</h2>
        <p class="r-note">Snapshot: estado actual al generar el informe. Periodo: {{ $period->fromDate() }} al {{ $period->toDate() }}.</p>
        <table class="dt">
            <thead>
                <tr><th>Indicador</th><th style="width:70px">Valor</th><th style="width:65px">Reloj</th><th>Descripción</th></tr>
            </thead>
            <tbody>
                @foreach ($vm->kpis as $kpi)
                    <tr>
                        <td><strong>{{ $kpi->label }}</strong></td>
                        <td class="num">
                            {{ $kpi->value }}
                            @if ($kpi->lowSample)<br><span class="low-sample">muestra baja</span>@endif
                        </td>
                        <td><span class="clock-tag clock-{{ $kpi->clock }}">{{ $kpi->clockLabel() }}</span></td>
                        <td class="muted">{{ $kpi->hint }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ===== 5. ASIGNACIONES ACTUALES ===== --}}
    <div class="r-section page-break">
        <h2 class="r-h2">Asignaciones actuales del técnico</h2>
        <p class="r-note">
            Snapshot: tickets abiertos o en progreso asignados al técnico, sin filtro de fechas.
            Orden operativo determinista: crítica &gt; alta &gt; abiertos antiguos (primero sin primera respuesta) &gt; en progreso antiguos &gt; media &gt; baja; desempate por fecha de creación.
        </p>
        @if (count($vm->currentAssignments) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th style="width:60px">Ticket</th>
                        <th>Título</th>
                        <th>Laboratorio</th>
                        <th>Categoría</th>
                        <th style="width:50px">Prioridad</th>
                        <th style="width:58px">Estado</th>
                        <th style="width:48px">Antig.</th>
                        <th style="width:70px">Última transición</th>
                        <th style="width:42px">Evid.</th>
                        <th style="width:46px">Dup. IA</th>
                        <th style="width:95px">Acción recomendada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vm->currentAssignments as $row)
                        <tr>
                            <td>{{ $row->displayId() }}</td>
                            <td><strong>{{ $row->title }}</strong></td>
                            <td>
                                {{ $row->locationName }}
                                @if ($row->building !== '' || $row->roomCode !== '')
                                    <br><span class="muted">{{ trim(implode(' · ', array_filter([$row->building, $row->floor !== '' ? 'Piso '.$row->floor : '', $row->roomCode]))) }}</span>
                                @endif
                            </td>
                            <td>{{ $row->categoryName }}</td>
                            <td><span class="pill pill-{{ $row->priority }}">{{ $priorityLabels[$row->priority] ?? $row->priority }}</span></td>
                            <td><span class="pill pill-{{ $row->state }}">{{ $stateLabels[$row->state] ?? $row->state }}</span></td>
                            <td class="num">{{ $row->ageDays }} d</td>
                            <td class="muted">
                                @if ($row->lastTransition !== null)
                                    {{ $stateLabels[$row->lastTransition['toState']] ?? $row->lastTransition['toState'] }}<br>{{ $row->lastTransition['at']->format('Y-m-d') }}
                                @else
                                    Sin transiciones
                                @endif
                            </td>
                            <td>{{ $row->hasEvidence ? 'Sí ('.$row->evidenceCount.')' : 'No' }}</td>
                            <td>
                                @if ($row->hasDuplicateWarning)
                                    <span class="pill pill-warning">Sí</span>
                                @else
                                    <span class="muted">No</span>
                                @endif
                            </td>
                            <td>{{ $row->recommendedAction }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">El técnico no tiene asignaciones activas al momento de generar el informe.</p>
        @endif
    </div>

    {{-- ===== 6. CRÍTICOS Y ATRASADOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Tickets críticos y atrasados</h2>
        @php
            $critical = $vm->criticalAndDelayed['critical'];
            $staleOpen = $vm->criticalAndDelayed['staleOpen'];
            $staleInProgress = $vm->criticalAndDelayed['staleInProgress'];
        @endphp
        @if (count($critical) === 0 && count($staleOpen) === 0 && count($staleInProgress) === 0)
            <p class="empty">No se registran tickets críticos ni atrasados en las asignaciones activas.</p>
        @else
            @if (count($critical) > 0)
                <p class="r-note"><b>Prioridad crítica / alta sin cerrar ({{ count($critical) }})</b> — atención inmediata.</p>
                <table class="dt">
                    <thead><tr><th style="width:60px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:55px">Prioridad</th><th style="width:58px">Estado</th><th style="width:48px">Antig.</th></tr></thead>
                    <tbody>
                        @foreach ($critical as $row)
                            <tr>
                                <td>{{ $row->displayId() }}</td>
                                <td>{{ $row->title }}</td>
                                <td>{{ $row->locationName }}</td>
                                <td><span class="pill pill-{{ $row->priority }}">{{ $priorityLabels[$row->priority] ?? $row->priority }}</span></td>
                                <td><span class="pill pill-{{ $row->state }}">{{ $stateLabels[$row->state] ?? $row->state }}</span></td>
                                <td class="num">{{ $row->ageDays }} d</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if (count($staleOpen) > 0)
                <p class="r-note" style="margin-top:7px"><b>Abiertos con más de 3 días sin iniciar ({{ count($staleOpen) }})</b></p>
                <table class="dt">
                    <thead><tr><th style="width:60px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:48px">Antig.</th><th style="width:95px">Acción</th></tr></thead>
                    <tbody>
                        @foreach ($staleOpen as $row)
                            <tr>
                                <td>{{ $row->displayId() }}</td>
                                <td>{{ $row->title }}</td>
                                <td>{{ $row->locationName }}</td>
                                <td class="num">{{ $row->ageDays }} d</td>
                                <td>{{ $row->recommendedAction }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if (count($staleInProgress) > 0)
                <p class="r-note" style="margin-top:7px"><b>En progreso sin transiciones recientes ({{ count($staleInProgress) }})</b> — más de 5 días sin actividad registrada.</p>
                <table class="dt">
                    <thead><tr><th style="width:60px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:78px">Última transición</th></tr></thead>
                    <tbody>
                        @foreach ($staleInProgress as $row)
                            <tr>
                                <td>{{ $row->displayId() }}</td>
                                <td>{{ $row->title }}</td>
                                <td>{{ $row->locationName }}</td>
                                <td class="muted">{{ $row->lastTransition !== null ? $row->lastTransition['at']->format('Y-m-d') : 'Sin transiciones' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>

    {{-- ===== 7. TICKETS POR LABORATORIO ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Tickets por laboratorio</h2>
        <p class="r-note">Columnas de carga activa: snapshot. Columnas "Creados", "Resueltos", "Rechazados" y "Cancelados": periodo seleccionado. "Total" cuenta tickets distintos del informe.</p>
        @if (count($vm->locationBreakdown) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th>Laboratorio</th>
                        <th style="width:42px">Activos</th>
                        <th style="width:46px">Abiertos</th>
                        <th style="width:48px">En curso</th>
                        <th style="width:48px">Crít./alta</th>
                        <th style="width:46px">Creados</th>
                        <th style="width:52px">Resueltos</th>
                        <th style="width:54px">Rechazados</th>
                        <th style="width:56px">Cancelados</th>
                        <th style="width:40px">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vm->locationBreakdown as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->locationName }}</strong>
                                @if ($row->building !== '' || $row->roomCode !== '')
                                    <br><span class="muted">{{ trim(implode(' · ', array_filter([$row->building, $row->floor !== '' ? 'Piso '.$row->floor : '', $row->roomCode]))) }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $row->activeCount }}</td>
                            <td class="num">{{ $row->openCount }}</td>
                            <td class="num">{{ $row->inProgressCount }}</td>
                            <td class="num">{{ $row->highCriticalActive }}</td>
                            <td class="num">{{ $row->createdInPeriod }}</td>
                            <td class="num">{{ $row->resolvedInPeriod }}</td>
                            <td class="num">{{ $row->rejectedInPeriod }}</td>
                            <td class="num">{{ $row->cancelledInPeriod }}</td>
                            <td class="num">{{ $row->totalRelevant }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin tickets relevantes por laboratorio para este informe.</p>
        @endif
    </div>

    {{-- ===== 8. TICKETS POR CATEGORÍA ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Tickets por categoría</h2>
        <p class="r-note">Activos: snapshot. Creados/Resueltos/Rechazados/Cancelados: periodo. "Total relevante" cuenta tickets distintos y el porcentaje es sobre ese total.</p>
        @if (count($vm->categoryBreakdown) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th style="width:42px">Activos</th>
                        <th style="width:46px">Creados</th>
                        <th style="width:52px">Resueltos</th>
                        <th style="width:56px">Rechazados</th>
                        <th style="width:56px">Cancelados</th>
                        <th style="width:40px">Total</th>
                        <th style="width:40px">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vm->categoryBreakdown as $row)
                        <tr>
                            <td><strong>{{ $row->categoryName }}</strong></td>
                            <td class="num">{{ $row->activeCount }}</td>
                            <td class="num">{{ $row->createdInPeriod }}</td>
                            <td class="num">{{ $row->resolvedInPeriod }}</td>
                            <td class="num">{{ $row->rejectedInPeriod }}</td>
                            <td class="num">{{ $row->cancelledInPeriod }}</td>
                            <td class="num">{{ $row->totalRelevant }}</td>
                            <td class="num">{{ $row->percentage }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin categorías relevantes para este informe.</p>
        @endif
    </div>

    {{-- ===== 9. TRABAJO RESUELTO EN EL PERIODO ===== --}}
    <div class="r-section page-break">
        <h2 class="r-h2">Trabajo resuelto en el periodo</h2>
        <p class="r-note">Reloj: periodo. Fuente: tickets.resolved_at entre {{ $period->fromDate() }} y {{ $period->toDate() }}.</p>
        @if (count($vm->resolvedTickets) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th style="width:60px">Ticket</th>
                        <th>Título</th>
                        <th>Laboratorio</th>
                        <th>Categoría</th>
                        <th style="width:50px">Prioridad</th>
                        <th style="width:56px">Resuelto</th>
                        <th style="width:52px">Duración</th>
                        <th style="width:42px">Evid.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vm->resolvedTickets as $row)
                        <tr>
                            <td>{{ $row->displayId() }}</td>
                            <td>{{ $row->title }}</td>
                            <td>{{ $row->locationName }}</td>
                            <td>{{ $row->categoryName }}</td>
                            <td><span class="pill pill-{{ $row->priority }}">{{ $priorityLabels[$row->priority] ?? $row->priority }}</span></td>
                            <td class="muted">{{ $row->resolvedAt?->format('Y-m-d') ?? '—' }}</td>
                            <td class="num">{{ $row->durationHours !== null ? $row->durationHours.' h' : '—' }}</td>
                            <td>{{ $row->evidenceCount > 0 ? 'Sí ('.$row->evidenceCount.')' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No se registran tickets resueltos dentro del periodo seleccionado.</p>
        @endif

        @if (count($vm->rejectedTickets) > 0)
            <p class="r-note" style="margin-top:8px"><b>Cierres administrativos (rechazados) en el periodo ({{ count($vm->rejectedTickets) }})</b> — no cuentan como resueltos.</p>
            <table class="dt">
                <thead><tr><th style="width:60px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:60px">Fecha</th><th>Tratamiento</th></tr></thead>
                <tbody>
                    @foreach ($vm->rejectedTickets as $row)
                        <tr>
                            <td>{{ $row->displayId() }}</td>
                            <td>{{ $row->title }}</td>
                            <td>{{ $row->locationName }}</td>
                            <td class="muted">{{ $row->closedAt?->format('Y-m-d') ?? '—' }}</td>
                            <td class="muted">{{ $row->note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($vm->cancelledTickets) > 0)
            <p class="r-note" style="margin-top:8px"><b>Cancelados por el reporter en el periodo ({{ count($vm->cancelledTickets) }})</b> — informativo; no penaliza al técnico ni entra en la tasa de cierre.</p>
            <table class="dt">
                <thead><tr><th style="width:60px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:60px">Fecha</th><th>Tratamiento</th></tr></thead>
                <tbody>
                    @foreach ($vm->cancelledTickets as $row)
                        <tr>
                            <td>{{ $row->displayId() }}</td>
                            <td>{{ $row->title }}</td>
                            <td>{{ $row->locationName }}</td>
                            <td class="muted">{{ $row->closedAt?->format('Y-m-d') ?? '—' }}</td>
                            <td class="muted">{{ $row->note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ===== 10. TIEMPOS DE ATENCIÓN Y RESOLUCIÓN ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Tiempos de atención y resolución</h2>
        <p class="r-note">Reloj: periodo. La primera respuesta se mide con la primera transición real a "en progreso" en state_history.</p>
        <table class="dt">
            <thead><tr><th>Métrica</th><th style="width:80px">Valor</th><th style="width:70px">Muestra (n)</th><th>Nota</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>Promedio de resolución</strong></td>
                    <td class="num">{{ $time['avgResolutionHours'] !== null ? $time['avgResolutionHours'].' h' : 'Sin datos' }}</td>
                    <td class="num">{{ $time['resolutionSample'] }}</td>
                    <td class="muted">Desde creación hasta resolución de los resueltos del periodo. @if ($time['resolutionLowSample'])<span class="low-sample">muestra baja</span>@endif</td>
                </tr>
                <tr>
                    <td><strong>Mediana de resolución</strong></td>
                    <td class="num">{{ $time['medianResolutionHours'] !== null ? $time['medianResolutionHours'].' h' : 'Sin datos' }}</td>
                    <td class="num">{{ $time['resolutionSample'] }}</td>
                    <td class="muted">Valor central, robusto ante casos extremos. @if ($time['resolutionLowSample'])<span class="low-sample">muestra baja</span>@endif</td>
                </tr>
                <tr>
                    <td><strong>Primera respuesta promedio</strong></td>
                    <td class="num">{{ $time['avgFirstResponseHours'] !== null ? $time['avgFirstResponseHours'].' h' : 'Sin datos' }}</td>
                    <td class="num">{{ $time['firstResponseSample'] }}</td>
                    <td class="muted">Hasta la primera transición a en progreso dentro del periodo. @if ($time['firstResponseLowSample'])<span class="low-sample">muestra baja</span>@endif</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ===== 11. EVIDENCIAS REGISTRADAS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Evidencias registradas</h2>
        <p class="r-note">Snapshot sobre asignaciones activas. Fuente: ticket_media.</p>
        <table class="dt">
            <thead><tr><th>Con evidencia</th><th>Sin evidencia</th><th>Cobertura</th></tr></thead>
            <tbody>
                <tr>
                    <td class="num">{{ $evidence['withEvidence'] }}</td>
                    <td class="num">{{ $evidence['withoutEvidence'] }}</td>
                    <td class="num">{{ $evidence['coveragePct'] !== null ? $evidence['coveragePct'].' %' : 'Sin datos' }}</td>
                </tr>
            </tbody>
        </table>
        @if (count($evidence['missing']) > 0)
            <p class="r-note" style="margin-top:6px"><b>Tickets activos sin evidencia ({{ count($evidence['missing']) }})</b></p>
            <table class="dt">
                <thead><tr><th style="width:70px">Ticket</th><th>Título</th></tr></thead>
                <tbody>
                    @foreach ($evidence['missing'] as $missing)
                        <tr><td>#TIC-{{ $missing['idShort'] }}</td><td>{{ $missing['title'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($evidence['withEvidence'] > 0)
            <p class="empty" style="margin-top:6px">Todas las asignaciones activas cuentan con evidencia adjunta.</p>
        @endif
    </div>

    {{-- ===== 12. INCIDENCIAS RECURRENTES POR LABORATORIO ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Incidencias recurrentes por laboratorio</h2>
        <p class="r-note">Fuente: historial de incidencias (location_incident_history) para los laboratorios y categorías de todos los tickets relevantes del informe (activos, creados, resueltos y cierres del periodo). El promedio histórico es texto de referencia.</p>
        @if (count($vm->recurrenceInsights) > 0)
            <table class="dt">
                <thead><tr><th>Laboratorio</th><th>Categoría</th><th style="width:60px">Recurr.</th><th style="width:70px">Última resol.</th><th style="width:90px">Promedio hist.</th><th>Recomendación</th></tr></thead>
                <tbody>
                    @foreach ($vm->recurrenceInsights as $row)
                        <tr>
                            <td><strong>{{ $row->locationName }}</strong></td>
                            <td>{{ $row->categoryName }}</td>
                            <td class="num">{{ $row->recurrenceCount }}</td>
                            <td class="muted">{{ $row->lastResolvedAt?->format('Y-m-d') ?? '—' }}</td>
                            <td class="muted">{{ $row->avgResolutionLabel }}</td>
                            <td class="muted">{{ $row->recommendation }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No se registran incidencias recurrentes relevantes para los laboratorios asignados al técnico.</p>
        @endif
    </div>

    {{-- ===== 13. RIESGOS OPERATIVOS ===== --}}
    <div class="r-section page-break">
        <h2 class="r-h2">Riesgos operativos</h2>
        <p class="r-note">Riesgos deterministas derivados de los datos del informe; reproducibles con los mismos insumos.</p>
        @if (count($vm->risks) > 0)
            <table class="dt">
                <thead><tr><th style="width:72px">Severidad</th><th>Detalle</th><th style="width:50px">Afectados</th><th>Tickets relacionados</th></tr></thead>
                <tbody>
                    @foreach ($vm->risks as $risk)
                        <tr>
                            <td><span class="pill pill-{{ $risk->severity === 'critical' ? 'critical' : ($risk->severity === 'warning' ? 'warning' : 'info') }}">{{ $risk->severityLabel() }}</span></td>
                            <td>{{ $risk->detail }}</td>
                            <td class="num">{{ $risk->affectedCount }}</td>
                            <td class="muted">{{ count($risk->relatedTickets) > 0 ? implode(', ', $risk->relatedTickets) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No se detectan riesgos operativos con los datos actuales.</p>
        @endif
    </div>

    {{-- ===== 14. RECOMENDACIONES TÉCNICAS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Recomendaciones técnicas</h2>
        <ul class="reco">
            @foreach ($vm->recommendations as $recommendation)
                <li>{{ $recommendation }}</li>
            @endforeach
        </ul>
    </div>

    {{-- ===== 15. ALERTAS IA Y POSIBLES DUPLICADOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Alertas IA y posibles duplicados</h2>
        <p class="r-note">
            Señal operativa de calidad de atención: no mide productividad, no penaliza al técnico y no afecta la tasa de cierre.
            Las explicaciones provienen de la metadata Strategy persistida; no se recalcula ningún motor al generar este informe.
        </p>
        @if ($vm->aiSummary['allZero'])
            <p class="empty">No se detectan alertas IA activas para los tickets del técnico en este informe.</p>
        @else
            <table class="dt" style="margin-bottom:7px">
                <thead>
                    <tr>
                        <th style="width:25%">Posibles duplicados IA activos</th>
                        <th style="width:25%">Pendientes de revisión</th>
                        <th style="width:25%">Posibles recurrencias (Strategy)</th>
                        <th style="width:25%">Sin desglose Strategy</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="num">{{ $vm->aiSummary['active'] }}</td>
                        <td class="num">{{ $vm->aiSummary['pendingReview'] }}</td>
                        <td class="num">{{ $vm->aiSummary['suggestsRecurrence'] }}</td>
                        <td class="num">{{ $vm->aiSummary['legacyWithoutStrategy'] }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
        @if (count($vm->duplicateAlerts) > 0)
            @foreach ($vm->duplicateAlerts as $alert)
                <div class="dup-block">
                    <p class="dup-title">{{ $alert->displayId() }} — {{ $alert->title }}</p>
                    <p class="dup-meta">
                        Ticket similar: {{ $alert->matchedDisplayId() }}{{ $alert->matchedTicketTitle !== null ? ' · '.$alert->matchedTicketTitle : ' · No disponible' }}
                        · Similitud: {{ $alert->similarity !== null ? number_format($alert->similarity, 2) : 'Sin dato' }}
                        · Score Strategy: {{ $alert->strategyScore ?? 'Sin desglose' }}
                        · Revisión: {{ $alert->reviewStatusLabel }}
                        @if ($alert->suggestsRecurrence) · <b>Posible recurrencia</b>@endif
                    </p>
                    <p class="dup-summary">{{ $alert->summary }}</p>
                    @if (count($alert->topReasons) > 0)
                        <ul class="dup-reasons">
                            @foreach ($alert->topReasons as $reason)
                                <li><b>{{ $reason['label'] }}</b>@if ($reason['detail'] !== '') — {{ $reason['detail'] }}@endif</li>
                            @endforeach
                        </ul>
                    @elseif ($alert->isFallback)
                        <p class="dup-summary muted">Registro sin desglose Strategy disponible; explicación basada en la similitud legada.</p>
                    @endif
                    <p class="dup-action">Acción recomendada: {{ $alert->recommendedAction }}</p>
                </div>
            @endforeach
        @elseif (! $vm->aiSummary['allZero'])
            <p class="empty">No se detectan tickets activos del técnico marcados como posibles duplicados por IA.</p>
        @endif
    </div>

    {{-- ===== 16. COLA GLOBAL DISPONIBLE (CONTEXTO) ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Cola global disponible — contexto del área</h2>
        <p class="r-note">
            Snapshot. Tickets abiertos, sin asignar y sin bloqueo. <b>No forman parte de la responsabilidad personal del técnico</b>
            y no afectan ninguno de sus indicadores.
        </p>
        @if ($queue['count'] > 0)
            <p class="r-note">Total disponible: <b>{{ $queue['count'] }}</b> ticket(s). Se muestran los más antiguos.</p>
            <table class="dt">
                <thead><tr><th style="width:70px">Ticket</th><th>Título</th><th>Laboratorio</th><th style="width:55px">Prioridad</th><th style="width:48px">Antig.</th></tr></thead>
                <tbody>
                    @foreach ($queue['preview'] as $item)
                        <tr>
                            <td>#TIC-{{ $item['idShort'] }}</td>
                            <td>{{ $item['title'] }}</td>
                            <td>{{ $item['locationName'] }}</td>
                            <td><span class="pill pill-{{ $item['priority'] }}">{{ $priorityLabels[$item['priority']] ?? $item['priority'] }}</span></td>
                            <td class="num">{{ $item['ageDays'] }} d</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">La cola global del área no tiene tickets disponibles en este momento.</p>
        @endif
    </div>

    {{-- ===== 17. CALIDAD DE DATOS ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Calidad de datos del informe</h2>
        @if (count($vm->dataQuality) > 0)
            <p class="r-note">Inconsistencias detectadas. No bloquean el informe; se reportan para corrección.</p>
            <table class="dt">
                <thead><tr><th>Detalle</th><th style="width:50px">Casos</th></tr></thead>
                <tbody>
                    @foreach ($vm->dataQuality as $issue)
                        <tr>
                            <td>{{ $issue['detail'] }}</td>
                            <td class="num">{{ $issue['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No se detectaron inconsistencias de datos en los universos del informe.</p>
        @endif
    </div>

    {{-- ===== 18. ANEXO: DETALLE COMPLETO ===== --}}
    <div class="r-section page-break">
        <h2 class="r-h2">Anexo — Detalle completo de tickets</h2>
        <p class="r-note">Todos los tickets relevantes del informe, sin duplicados: asignaciones activas (orden operativo), creados en el periodo, resueltos, cierres administrativos y asignados históricos.</p>
        @if (count($appendix['rows']) > 0)
            <table class="dt">
                <thead>
                    <tr>
                        <th style="width:58px">Ticket</th>
                        <th>Título</th>
                        <th>Laboratorio</th>
                        <th>Categoría</th>
                        <th style="width:46px">Prioridad</th>
                        <th style="width:56px">Estado</th>
                        <th style="width:52px">Creado</th>
                        <th style="width:56px">Cerrado</th>
                        <th style="width:54px">Antig./Dur.</th>
                        <th style="width:64px">Última transición</th>
                        <th style="width:40px">Evid.</th>
                        <th style="width:52px">Dup. IA</th>
                        <th style="width:78px">Motivo de inclusión</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($appendix['rows'] as $row)
                        <tr>
                            <td>{{ $row['displayId'] }}</td>
                            <td>{{ $row['title'] }}</td>
                            <td>{{ $row['locationName'] }}</td>
                            <td>{{ $row['categoryName'] }}</td>
                            <td>{{ $row['priority'] }}</td>
                            <td>{{ $row['stateLabel'] }}</td>
                            <td class="muted">{{ $row['createdAt'] }}</td>
                            <td class="muted">{{ $row['closedAt'] }}</td>
                            <td class="muted">{{ $row['ageOrDuration'] }}</td>
                            <td class="muted">{{ $row['lastTransition'] }}</td>
                            <td>{{ $row['evidence'] }}</td>
                            <td class="muted">
                                {{ $row['duplicate'] }}
                                @if ($row['duplicateReasons'] !== '')<br>{{ $row['duplicateReasons'] }}@endif
                            </td>
                            <td class="muted">{{ $row['inclusionReason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($appendix['truncated'])
                <p class="r-note" style="margin-top:5px">Anexo truncado a {{ count($appendix['rows']) }} filas de {{ $appendix['totalRows'] }} en total.</p>
            @endif
        @else
            <p class="empty">No hay tickets relevantes para el anexo en este informe.</p>
        @endif
    </div>

    {{-- ===== 19. DEFINICIONES ===== --}}
    <div class="r-section">
        <h2 class="r-h2">Definiciones de métricas</h2>
        <ul class="defs">
            @foreach ($vm->definitions as $definition)
                <li><b>{{ $definition['term'] }}:</b> {{ $definition['definition'] }}</li>
            @endforeach
        </ul>
    </div>

    <div class="foot">
        Informe generado por INCIDEX el {{ $vm->generatedAt->format('Y-m-d H:i') }} para {{ $vm->technician['name'] }}.
        Métricas personales limitadas a tickets asignados a este técnico. La cola global se muestra solo como contexto del área.
        Las señales de IA sobre duplicados son alertas operativas y no miden productividad.
    </div>

</div>
