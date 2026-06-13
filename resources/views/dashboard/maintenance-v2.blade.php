@extends('layouts.app')

@section('title', 'Dashboard · Mantenimiento')

@section('content')
@php
    /**
     * Dashboard Maintenance V2 — parallel redesign (real-data phase).
     *
     * The heavy figures are reused from MaintenanceDashboardQuery (same ViewModel
     * as /dashboard and the PDF report). The controller maps them to the arrays
     * below; activity, the assignments slice and the duplicate count are extra
     * scoped reads (assigned_to = me). The "Exportar informe PDF" button reuses
     * the existing dashboard.maintenance.report.pdf route, preserving the range.
     *
     * @var string $dateRange
     * @var string $rangePreset
     * @var list<array{value:string,label:string}> $rangePresets
     * @var bool $isCustomRange
     * @var string $customFrom
     * @var string $customTo
     * @var string|null $pdfUrl
     * @var list<array<string,mixed>> $kpis
     * @var array<string,mixed> $statusDonut
     * @var array<string,mixed> $priorityBars
     * @var array<string,mixed> $labBars
     * @var array{value:int,resolved:int,total:int} $closeRate
     * @var array{avg:string,median:string,first:string} $times
     * @var array{labels:list<string>,created:list<int>,resolved:list<int>} $trend
     * @var int $trendMax
     * @var list<array<string,mixed>> $alerts
     * @var list<array<string,mixed>> $activity
     * @var array<string,mixed> $activityPager
     * @var list<array<string,mixed>> $assignments
     * @var array<string,mixed> $assignmentsPager
     * @var list<array<string,mixed>> $bottomCards
     */
    // Trend chart geometry — a fixed, non-distorting viewBox so the dot markers
    // stay perfectly round and the grid/axes line up (no charting library needed).
    $chartW = 360;
    $chartH = 170;
    $padL = 30;
    $padR = 12;
    $padT = 14;
    $padB = 26;
    $plotW = $chartW - $padL - $padR;
    $plotH = $chartH - $padT - $padB;
    $points = count($trend['labels']);
    $cx = fn (int $i): float => $points <= 1
        ? $padL + $plotW / 2
        : round($padL + $i / ($points - 1) * $plotW, 1);
    $cy = fn (float $v): float => round($padT + (1 - $v / max(1, $trendMax)) * $plotH, 1);
    $polyOf = function (array $series) use ($cx, $cy): string {
        $out = [];
        foreach (array_values($series) as $i => $v) {
            $out[] = $cx($i).','.$cy((float) $v);
        }
        return implode(' ', $out);
    };
    // Y-axis ticks, top→bottom (e.g. 15/10/5/0 when the peak is 15).
    $gridTicks = [];
    for ($g = 3; $g >= 0; $g--) {
        $gridTicks[] = ['y' => $cy($trendMax * $g / 3), 'label' => (string) (int) round($trendMax * $g / 3)];
    }
    $trendHasData = (array_sum($trend['created']) + array_sum($trend['resolved'])) > 0;

    $donutStops = collect($statusDonut['segments'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');

    $donutAria = collect($statusDonut['segments'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")->implode(', ');
@endphp

<div class="mdv2-page">

    {{-- ── 1. HEADER (real range control: GET form) ──────────────── --}}
    <header class="mdv2-header">
        <div>
            <h1 class="mdv2-header__title">Dashboard</h1>
            <p class="mdv2-header__subtitle">Resumen general de la operación de mantenimiento.</p>
        </div>
        {{-- Real, GET-driven range control. No `action` → it always submits to
             the current URL, so it works identically on /dashboard and the
             /dashboard/maintenance-v2 alias. The preset auto-submits natively
             (onchange); choosing "Personalizado" reveals the from/to inputs.
             Fully functional without any module JS — "Actualizar" is the
             explicit fallback. --}}
        <form method="GET" class="mdv2-header__actions" data-mdv2-rangeform>
            <span class="mdv2-daterange" title="Rango activo del periodo">
                <x-lucide-calendar-range width="16" height="16" stroke-width="2" />
                {{ $dateRange }}
            </span>
            <label for="mdv2-preset" class="mdv2-sr-only">Rango de fechas</label>
            <select id="mdv2-preset" name="preset" class="mdv2-select" data-mdv2-preset onchange="this.form.submit()">
                @foreach ($rangePresets as $preset)
                    <option value="{{ $preset['value'] }}" @selected($rangePreset === $preset['value'])>{{ $preset['label'] }}</option>
                @endforeach
            </select>
            <div class="mdv2-custom {{ $isCustomRange ? '' : 'mdv2-custom--hidden' }}" data-mdv2-custom>
                <label for="mdv2-from" class="mdv2-sr-only">Desde</label>
                <input type="date" id="mdv2-from" name="from" value="{{ $customFrom }}" class="mdv2-date" aria-label="Fecha inicial" max="{{ now()->format('Y-m-d') }}">
                <span class="mdv2-custom__sep" aria-hidden="true">–</span>
                <label for="mdv2-to" class="mdv2-sr-only">Hasta</label>
                <input type="date" id="mdv2-to" name="to" value="{{ $customTo }}" class="mdv2-date" aria-label="Fecha final" max="{{ now()->format('Y-m-d') }}">
            </div>
            <button type="submit" class="mdv2-btn mdv2-btn--ghost">
                <x-lucide-refresh-cw width="15" height="15" stroke-width="2.5" />
                Actualizar
            </button>
            @if ($pdfUrl !== null)
                <a href="{{ $pdfUrl }}" class="mdv2-btn mdv2-btn--primary" target="_blank" rel="noopener">
                    <x-lucide-file-down width="16" height="16" stroke-width="2.5" />
                    Exportar informe PDF
                </a>
            @else
                <a href="#" class="mdv2-btn mdv2-btn--primary" aria-disabled="true" title="Exportación PDF pendiente de enlazar">
                    <x-lucide-file-down width="16" height="16" stroke-width="2.5" />
                    Exportar informe PDF
                </a>
            @endif
        </form>
    </header>

    {{-- ── 2. KPI ROW ────────────────────────────────────────────── --}}
    <div class="mdv2-kpis">
        @foreach ($kpis as $kpi)
            <div class="mdv2-kpi mdv2-tone-{{ $kpi['tone'] }}">
                <div class="mdv2-kpi__top">
                    <span class="mdv2-kpi__icon">
                        <x-dynamic-component :component="'lucide-' . $kpi['icon']" width="20" height="20" stroke-width="2" />
                    </span>
                    <div>
                        <div class="mdv2-kpi__value">{{ $kpi['value'] }}</div>
                        <div class="mdv2-kpi__label">{{ $kpi['label'] }}</div>
                    </div>
                </div>
                <div class="mdv2-kpi__note">{{ $kpi['note'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── 3. CHARTS ROW ─────────────────────────────────────────── --}}
    <div class="mdv2-charts">

        {{-- A. Tickets por estado (donut) --}}
        <section class="mdv2-card">
            <h2 class="mdv2-card__title">
                <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                Tickets por estado
            </h2>
            <div class="mdv2-donut-wrap">
                <div class="mdv2-donut {{ $statusDonut['total'] === 0 ? 'mdv2-donut--empty' : '' }}" role="img"
                     @if ($statusDonut['total'] > 0) style="background: conic-gradient({{ $donutStops }});" @endif
                     aria-label="Tickets por estado: {{ $donutAria }}">
                    <div class="mdv2-donut__center">
                        <span class="mdv2-donut__total">{{ $statusDonut['total'] }}</span>
                        <span class="mdv2-donut__caption">tickets</span>
                    </div>
                </div>
                <div class="mdv2-legend">
                    @foreach ($statusDonut['segments'] as $seg)
                        <div class="mdv2-legend__row mdv2-tone-{{ $seg['tone'] }}">
                            <span class="mdv2-legend__dot" aria-hidden="true"></span>
                            <span class="mdv2-legend__label">{{ $seg['label'] }}</span>
                            <span class="mdv2-legend__value">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mdv2-card__total"><span>Total</span><strong>{{ $statusDonut['total'] }} tickets</strong></div>
        </section>

        {{-- B. Tickets por prioridad (vertical bars) --}}
        <section class="mdv2-card">
            <h2 class="mdv2-card__title">
                <x-lucide-bar-chart-3 width="16" height="16" stroke-width="2" />
                Tickets por prioridad
            </h2>
            @php $vpeak = max(1, $priorityBars['peak']); @endphp
            <div class="mdv2-vbars" role="img"
                 aria-label="Tickets por prioridad: {{ collect($priorityBars['items'])->map(fn ($i) => "{$i['label']} {$i['count']}")->implode(', ') }}">
                @foreach ($priorityBars['items'] as $bar)
                    <div class="mdv2-vbar mdv2-tone-{{ $bar['tone'] }}">
                        <span class="mdv2-vbar__count">{{ $bar['count'] }}</span>
                        <div class="mdv2-vbar__track">
                            <div class="mdv2-vbar__fill" style="height: {{ round($bar['count'] / $vpeak * 100) }}%"></div>
                        </div>
                        <span class="mdv2-vbar__label">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- C. Tickets por laboratorio (horizontal bars) --}}
        <section class="mdv2-card">
            <h2 class="mdv2-card__title">
                <x-lucide-building-2 width="16" height="16" stroke-width="2" />
                Tickets por laboratorio
            </h2>
            @php $hpeak = max(1, $labBars['peak']); @endphp
            <div class="mdv2-hbars">
                @forelse ($labBars['items'] as $lab)
                    <div>
                        <div class="mdv2-hbar__head">
                            <span class="mdv2-hbar__label">{{ $lab['name'] }}</span>
                            <span class="mdv2-hbar__value">{{ $lab['count'] }}</span>
                        </div>
                        <div class="mdv2-hbar__track">
                            <div class="mdv2-hbar__fill" style="width: {{ round($lab['count'] / $hpeak * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="mdv2-empty">Sin tickets asignados en el periodo.</p>
                @endforelse
            </div>
            <div class="mdv2-card__total"><span>Total</span><strong>{{ $labBars['total'] }} tickets</strong></div>
        </section>
    </div>

    {{-- ── 4. MAIN LAYOUT (activity + assignments | rail) ────────── --}}
    <div class="mdv2-layout">

        <div class="mdv2-main">

            {{-- Actividad reciente --}}
            <section class="mdv2-card mdv2-card--fill">
                <div class="mdv2-card__head">
                    <h2 class="mdv2-card__title">
                        <x-lucide-activity width="16" height="16" stroke-width="2" />
                        Actividad reciente
                    </h2>
                    <a href="{{ route('tickets.history') }}" class="mdv2-link">Ver historial</a>
                </div>
                <div class="mdv2-feed">
                    @forelse ($activity as $event)
                        <article class="mdv2-event mdv2-tone-{{ $event['tone'] }}">
                            <span class="mdv2-event__icon">
                                <x-dynamic-component :component="'lucide-' . $event['icon']" width="16" height="16" stroke-width="2" />
                            </span>
                            <div class="mdv2-event__body">
                                <div class="mdv2-event__top">
                                    <h3 class="mdv2-event__title">{{ $event['title'] }}</h3>
                                    <span class="mdv2-badge mdv2-status--{{ $event['status'] }}">
                                        <span class="mdv2-badge__dot" aria-hidden="true"></span>
                                        {{ $event['status_label'] }}
                                    </span>
                                </div>
                                <div class="mdv2-event__meta">{{ $event['ref'] }} · {{ $event['lab'] }}</div>
                                <div class="mdv2-event__foot">
                                    <span class="mdv2-event__at">{{ $event['at'] }}</span>
                                    <span class="mdv2-event__text">{{ $event['text'] }}</span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="mdv2-empty">Sin actividad reciente en tus tickets.</p>
                    @endforelse
                </div>
                @include('dashboard.partials.mdv2-pager', ['pager' => $activityPager, 'label' => 'Paginación de actividad reciente'])
            </section>

            {{-- Mis asignaciones --}}
            <section class="mdv2-card mdv2-card--fill">
                <div class="mdv2-card__head">
                    <h2 class="mdv2-card__title">
                        <x-lucide-list-checks width="16" height="16" stroke-width="2" />
                        Mis asignaciones
                    </h2>
                    <a href="{{ route('tickets.assignments') }}" class="mdv2-link">Ver todas ({{ $assignmentsPager['total'] }})</a>
                </div>
                <div class="mdv2-assign">
                    @forelse ($assignments as $a)
                        <div class="mdv2-assign__row mdv2-tone-{{ $a['priority'] }}">
                            <span class="mdv2-assign__rail" aria-hidden="true"></span>
                            <div class="mdv2-assign__body">
                                <div class="mdv2-assign__title">{{ $a['title'] }}</div>
                                <div class="mdv2-assign__meta">{{ $a['lab'] }} · {{ $a['priority_label'] }}</div>
                            </div>
                            <span class="mdv2-badge mdv2-status--{{ $a['status'] }}">
                                <span class="mdv2-badge__dot" aria-hidden="true"></span>
                                {{ $a['status_label'] }}
                            </span>
                        </div>
                    @empty
                        <p class="mdv2-empty">No tienes asignaciones activas.</p>
                    @endforelse
                </div>
                @include('dashboard.partials.mdv2-pager', ['pager' => $assignmentsPager, 'label' => 'Paginación de mis asignaciones'])
            </section>
        </div>

        {{-- RIGHT: analytics rail --}}
        <aside class="mdv2-rail" aria-label="Indicadores de desempeño">

            {{-- A. Tasa de resolución (gauge; no SLA table → no SLA invented) --}}
            <section class="mdv2-card">
                <h2 class="mdv2-card__title">
                    <x-lucide-shield-check width="16" height="16" stroke-width="2" />
                    Tasa de resolución
                </h2>
                <div class="mdv2-gauge-wrap">
                    <div class="mdv2-gauge" role="img" aria-label="Tasa de resolución: {{ $closeRate['value'] }}%"
                         style="background: conic-gradient(var(--color-success) 0 {{ $closeRate['value'] }}%, var(--border-default) {{ $closeRate['value'] }}% 100%);">
                        <div class="mdv2-gauge__center">
                            <span class="mdv2-gauge__value">{{ $closeRate['value'] }}%</span>
                            <span class="mdv2-gauge__caption">cierre</span>
                        </div>
                    </div>
                    <div class="mdv2-gauge-side">
                        <div class="mdv2-gauge-side__label">Resueltos sobre el total del periodo</div>
                        <div class="mdv2-gauge-side__detail">{{ $closeRate['resolved'] }} de {{ $closeRate['total'] }} tickets</div>
                    </div>
                </div>
            </section>

            {{-- B. Tiempos de resolución (reused ViewModel metrics) --}}
            <section class="mdv2-card">
                <h2 class="mdv2-card__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempos de resolución
                </h2>
                <dl class="mdv2-times">
                    <div class="mdv2-time">
                        <dt class="mdv2-time__label">Promedio</dt>
                        <dd class="mdv2-time__value">{{ $times['avg'] }}</dd>
                    </div>
                    <div class="mdv2-time">
                        <dt class="mdv2-time__label">Mediana</dt>
                        <dd class="mdv2-time__value">{{ $times['median'] }}</dd>
                    </div>
                    <div class="mdv2-time">
                        <dt class="mdv2-time__label">Primera respuesta</dt>
                        <dd class="mdv2-time__value">{{ $times['first'] }}</dd>
                    </div>
                </dl>
            </section>

            {{-- C. Tickets creados vs resueltos (line chart: grid + ejes + markers) --}}
            <section class="mdv2-card">
                <h2 class="mdv2-card__title">
                    <x-lucide-trending-up width="16" height="16" stroke-width="2" />
                    Tickets creados vs resueltos
                </h2>
                @if ($trendHasData)
                    <div class="mdv2-chart" role="img"
                         aria-label="Creados vs resueltos en el periodo. Creados: {{ implode(', ', $trend['created']) }}. Resueltos: {{ implode(', ', $trend['resolved']) }}.">
                        <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" class="mdv2-chart__svg" preserveAspectRatio="xMidYMid meet" role="presentation">
                            {{-- grid + y-axis ticks --}}
                            @foreach ($gridTicks as $tick)
                                <line class="mdv2-chart__grid" x1="{{ $padL }}" y1="{{ $tick['y'] }}" x2="{{ $chartW - $padR }}" y2="{{ $tick['y'] }}" />
                                <text class="mdv2-chart__axis" x="{{ $padL - 7 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['label'] }}</text>
                            @endforeach
                            {{-- series lines --}}
                            <polyline class="mdv2-chart__line mdv2-chart__line--created" points="{{ $polyOf($trend['created']) }}" />
                            <polyline class="mdv2-chart__line mdv2-chart__line--resolved" points="{{ $polyOf($trend['resolved']) }}" />
                            {{-- markers --}}
                            @foreach ($trend['created'] as $i => $v)
                                <circle class="mdv2-chart__dot mdv2-chart__dot--created" cx="{{ $cx($i) }}" cy="{{ $cy((float) $v) }}" r="3.4" />
                            @endforeach
                            @foreach ($trend['resolved'] as $i => $v)
                                <circle class="mdv2-chart__dot mdv2-chart__dot--resolved" cx="{{ $cx($i) }}" cy="{{ $cy((float) $v) }}" r="3.4" />
                            @endforeach
                            {{-- x-axis labels --}}
                            @foreach ($trend['labels'] as $i => $label)
                                <text class="mdv2-chart__axis" x="{{ $cx($i) }}" y="{{ $chartH - 8 }}" text-anchor="middle">{{ $label }}</text>
                            @endforeach
                        </svg>
                    </div>
                    <div class="mdv2-line-legend">
                        <span class="mdv2-line-legend__item"><span class="mdv2-line-legend__swatch mdv2-line-legend__swatch--created"></span> Creados ({{ array_sum($trend['created']) }})</span>
                        <span class="mdv2-line-legend__item"><span class="mdv2-line-legend__swatch mdv2-line-legend__swatch--resolved"></span> Resueltos ({{ array_sum($trend['resolved']) }})</span>
                    </div>
                @else
                    <p class="mdv2-empty">Sin tickets creados ni resueltos en el periodo.</p>
                @endif
            </section>

            {{-- D. Important alerts --}}
            <section class="mdv2-card">
                <h2 class="mdv2-card__title">
                    <x-lucide-alert-triangle width="16" height="16" stroke-width="2" />
                    Alertas importantes
                </h2>
                <div class="mdv2-alerts">
                    @forelse ($alerts as $alert)
                        <div class="mdv2-alert mdv2-tone-{{ $alert['tone'] }}">
                            <span class="mdv2-alert__icon">
                                <x-dynamic-component :component="'lucide-' . $alert['icon']" width="20" height="20" stroke-width="2" />
                            </span>
                            <div>
                                <div class="mdv2-alert__title"><strong>{{ $alert['count'] }}</strong> {{ $alert['title'] }}</div>
                                <div class="mdv2-alert__note">{{ $alert['note'] }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="mdv2-empty">Sin alertas activas. Tu cola está bajo control.</p>
                    @endforelse
                </div>
                <div class="mdv2-card__foot">
                    <a href="{{ route('tickets.index') }}" class="mdv2-link">
                        Ver todos los tickets
                        <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>
            </section>
        </aside>
    </div>

    {{-- ── 5. BOTTOM CARDS (all real, scoped) ────────────────────── --}}
    <div class="mdv2-bottom">
        @foreach ($bottomCards as $card)
            <div class="mdv2-card mdv2-stat mdv2-tone-{{ $card['tone'] }}">
                <span class="mdv2-stat__icon">
                    <x-dynamic-component :component="'lucide-' . $card['icon']" width="24" height="24" stroke-width="2" />
                </span>
                <div>
                    <div class="mdv2-stat__value">{{ $card['value'] }}</div>
                    <div class="mdv2-stat__label">{{ $card['label'] }}</div>
                    <div class="mdv2-stat__note">{{ $card['note'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
