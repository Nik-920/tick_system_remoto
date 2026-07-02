@extends('layouts.app')

@section('title', 'Centro De Control Admin')

@section('content')
@php
    /**
     * Dashboard Admin V2 — card-based, no <table>. Same visual grammar as
     * the maintenance V2 dashboard (dashboard-maintenance-v2.css, mdv2-*)
     * and the reporter dashboard (reporter-dashboard.css, rep-*), built by
     * AdminDashboardV2Presenter from AdminDashboardQuery's global figures.
     *
     * @var string $roleProfile
     * @var string $roleLabel
     * @var array{badge:string,title:string,subtitle:string,resolutionRate7Days:string} $hero
     * @var list<array{label:string,href:string,variant:string}> $quickActions
     * @var list<array{value:string,label:string,note:string,icon:string,tone:string}> $kpis
     * @var array{total:int,segments:list<array<string,mixed>>} $statusDonut
     * @var array{peak:int,items:list<array{label:string,count:int,tone:string}>} $priorityBars
     * @var array{total:int,peak:int,items:list<array{label:string,count:int,tone:string}>} $qrBars
     * @var array{value:int,resolved:int,total:int} $closeRate
     * @var list<array<string,mixed>> $recentActivity
     * @var list<array<string,mixed>> $topLocations
     * @var list<array<string,mixed>> $qrIssues
     * @var list<array{count:int,title:string,note:string,tone:string,icon:string}> $alerts
     * @var list<array{value:string,label:string,note:string,icon:string,tone:string}> $bottomCards
     */
    $donutStops = collect($statusDonut['segments'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
    $donutAria = collect($statusDonut['segments'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")
        ->implode(', ');
    $vpeak = max(1, $priorityBars['peak']);
    $hpeak = max(1, $qrBars['peak']);
@endphp

<div class="adm-page">

    {{-- ── 1. HERO ───────────────────────────────────────────────── --}}
    <section class="adm-hero">
        <div class="adm-hero-inner">
            <div>
                <p class="adm-header__overline">{{ $hero['badge'] }}</p>
                <h1 class="adm-header__title">{{ $hero['title'] }}</h1>
                <p class="adm-header__subtitle">{{ $hero['subtitle'] }}</p>
                <p class="adm-header__meta">Perfil operativo: {{ $roleLabel }}</p>
            </div>
            <div class="adm-header__actions">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['href'] }}" class="adm-btn adm-btn--{{ $action['variant'] === 'primary' ? 'primary' : 'ghost' }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 2. KPI ROW ────────────────────────────────────────────── --}}
    <div class="adm-kpis">
        @foreach ($kpis as $kpi)
            <div class="adm-kpi adm-tone-{{ $kpi['tone'] }}">
                <div class="adm-kpi__top">
                    <span class="adm-kpi__icon">
                        <x-dynamic-component :component="'lucide-' . $kpi['icon']" width="20" height="20" stroke-width="2" />
                    </span>
                    <div>
                        <div class="adm-kpi__value">{{ $kpi['value'] }}</div>
                        <div class="adm-kpi__label">{{ $kpi['label'] }}</div>
                    </div>
                </div>
                <div class="adm-kpi__note">{{ $kpi['note'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── 3. CHARTS ROW ─────────────────────────────────────────── --}}
    <div class="adm-charts">

        {{-- A. Tickets por estado (donut) --}}
        <section class="adm-card">
            <h2 class="adm-card__title">
                <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                Tickets por estado
            </h2>
            <div class="adm-donut-wrap">
                <div class="adm-donut {{ $statusDonut['total'] === 0 ? 'adm-donut--empty' : '' }}" role="img"
                     @if ($statusDonut['total'] > 0) style="background: conic-gradient({{ $donutStops }});" @endif
                     aria-label="Tickets por estado: {{ $donutAria }}">
                    <div class="adm-donut__center">
                        <span class="adm-donut__total">{{ $statusDonut['total'] }}</span>
                        <span class="adm-donut__caption">tickets</span>
                    </div>
                </div>
                <div class="adm-legend">
                    @foreach ($statusDonut['segments'] as $seg)
                        <div class="adm-legend__row adm-tone-{{ $seg['tone'] }}">
                            <span class="adm-legend__dot" aria-hidden="true"></span>
                            <span class="adm-legend__label">{{ $seg['label'] }}</span>
                            <span class="adm-legend__value">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="adm-card__total"><span>Total</span><strong>{{ $statusDonut['total'] }} tickets</strong></div>
        </section>

        {{-- B. Tickets por prioridad (vertical bars) --}}
        <section class="adm-card">
            <h2 class="adm-card__title">
                <x-lucide-bar-chart-3 width="16" height="16" stroke-width="2" />
                Tickets por prioridad
            </h2>
            <div class="adm-vbars" role="img"
                 aria-label="Tickets por prioridad: {{ collect($priorityBars['items'])->map(fn ($i) => "{$i['label']} {$i['count']}")->implode(', ') }}">
                @foreach ($priorityBars['items'] as $bar)
                    <div class="adm-vbar adm-tone-{{ $bar['tone'] }}">
                        <span class="adm-vbar__count">{{ $bar['count'] }}</span>
                        <div class="adm-vbar__track">
                            <div class="adm-vbar__fill" style="height: {{ round($bar['count'] / $vpeak * 100) }}%"></div>
                        </div>
                        <span class="adm-vbar__label">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- C. Salud QR por estado (horizontal bars) --}}
        <section class="adm-card">
            <h2 class="adm-card__title">
                <x-lucide-qr-code width="16" height="16" stroke-width="2" />
                Salud QR por estado
            </h2>
            <div class="adm-hbars">
                @foreach ($qrBars['items'] as $bar)
                    <div class="adm-tone-{{ $bar['tone'] }}">
                        <div class="adm-hbar__head">
                            <span class="adm-hbar__label">{{ $bar['label'] }}</span>
                            <span class="adm-hbar__value">{{ $bar['count'] }}</span>
                        </div>
                        <div class="adm-hbar__track">
                            <div class="adm-hbar__fill" style="width: {{ round($bar['count'] / $hpeak * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="adm-card__total"><span>Total</span><strong>{{ $qrBars['total'] }} ubicaciones</strong></div>
        </section>
    </div>

    {{-- ── 4. MAIN LAYOUT (actividad + ubicaciones | rail) ────────── --}}
    <div class="adm-layout">

        <div class="adm-main">

            {{-- Actividad global reciente --}}
            <section class="adm-card adm-card--fill">
                <div class="adm-card__head">
                    <h2 class="adm-card__title">
                        <x-lucide-activity width="16" height="16" stroke-width="2" />
                        Actividad global reciente
                    </h2>
                    <a href="{{ route('tickets.index') }}" class="adm-link">Ver todos</a>
                </div>
                <div class="adm-feed">
                    @forelse ($recentActivity as $event)
                        <article class="adm-event adm-tone-{{ $event['tone'] }}">
                            <span class="adm-event__icon">
                                <x-dynamic-component :component="'lucide-' . $event['icon']" width="16" height="16" stroke-width="2" />
                            </span>
                            <div class="adm-event__body">
                                <div class="adm-event__top">
                                    <h3 class="adm-event__title">{{ $event['title'] }}</h3>
                                    <span class="adm-badge adm-status--{{ $event['state'] }}">
                                        <span class="adm-badge__dot" aria-hidden="true"></span>
                                        {{ $event['state_label'] }}
                                    </span>
                                </div>
                                <div class="adm-event__meta">{{ $event['ref'] }} · {{ $event['location'] }}</div>
                                <div class="adm-event__foot">
                                    <span class="adm-event__at">{{ $event['at'] }}</span>
                                    <a href="{{ route('tickets.show', $event['id']) }}" class="adm-event__link">
                                        Ver
                                        <x-lucide-chevron-right width="13" height="13" stroke-width="2.5" />
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="adm-empty">No hay actividad reciente para mostrar.</p>
                    @endforelse
                </div>
            </section>

            {{-- Top ubicaciones con carga operativa --}}
            <section class="adm-card adm-card--fill">
                <div class="adm-card__head">
                    <h2 class="adm-card__title">
                        <x-lucide-map-pin width="16" height="16" stroke-width="2" />
                        Top ubicaciones con carga operativa
                    </h2>
                    <a href="{{ route('locations.index') }}" class="adm-link">Ver todas</a>
                </div>
                <div class="adm-locations">
                    @forelse ($topLocations as $location)
                        <div class="adm-loc">
                            <div class="adm-loc__head">
                                <span class="adm-loc__name">{{ $location['name'] }}</span>
                                <span class="adm-loc__meta">{{ $location['meta'] }}</span>
                            </div>
                            <div class="adm-loc__counts">
                                <span>Abiertos: <strong>{{ $location['open'] }}</strong></span>
                                <span>En progreso: <strong>{{ $location['in_progress'] }}</strong></span>
                            </div>
                            <div class="adm-loc__track">
                                <div class="adm-loc__fill" style="width: {{ $location['percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="adm-empty">No hay ubicaciones con carga activa en este momento.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- RIGHT: rail --}}
        <aside class="adm-rail" aria-label="Indicadores operativos">

            {{-- A. Tasa de resolución (7 días) --}}
            <section class="adm-card">
                <h2 class="adm-card__title">
                    <x-lucide-shield-check width="16" height="16" stroke-width="2" />
                    Tasa de resolución (7 días)
                </h2>
                <div class="adm-gauge-wrap">
                    <div class="adm-gauge" role="img" aria-label="Tasa de resolución: {{ $closeRate['value'] }}%"
                         style="background: conic-gradient(var(--color-success) 0 {{ $closeRate['value'] }}%, var(--border-default) {{ $closeRate['value'] }}% 100%);">
                        <div class="adm-gauge__center">
                            <span class="adm-gauge__value">{{ $closeRate['value'] }}%</span>
                            <span class="adm-gauge__caption">cierre</span>
                        </div>
                    </div>
                    <div class="adm-gauge-side">
                        <div class="adm-gauge-side__label">Resueltos sobre creados en 7 días</div>
                        <div class="adm-gauge-side__detail">{{ $closeRate['resolved'] }} de {{ $closeRate['total'] }} tickets</div>
                    </div>
                </div>
            </section>

            {{-- B. Radar QR --}}
            <section class="adm-card">
                <h2 class="adm-card__title">
                    <x-lucide-scan width="16" height="16" stroke-width="2" />
                    Radar de incidencias QR
                </h2>
                <div class="adm-qr-list">
                    @forelse ($qrIssues as $issue)
                        <div class="adm-qr-row">
                            <div class="adm-qr-row__body">
                                <div class="adm-qr-row__name">{{ $issue['name'] }}</div>
                                <div class="adm-qr-row__meta">{{ $issue['room'] }} · {{ $issue['tickets_count'] }} {{ Str::plural('ticket', $issue['tickets_count']) }}</div>
                            </div>
                            <span class="adm-badge adm-qr--{{ $issue['status'] }}">
                                <span class="adm-badge__dot" aria-hidden="true"></span>
                                {{ $issue['status_label'] }}
                            </span>
                        </div>
                    @empty
                        <p class="adm-empty">No hay incidencias QR activas.</p>
                    @endforelse
                </div>
                <div class="adm-card__foot">
                    <a href="{{ route('locations.index') }}" class="adm-link">
                        Gestionar ubicaciones
                        <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>
            </section>

            {{-- C. Alertas importantes --}}
            <section class="adm-card">
                <h2 class="adm-card__title">
                    <x-lucide-alert-triangle width="16" height="16" stroke-width="2" />
                    Alertas importantes
                </h2>
                <div class="adm-alerts">
                    @forelse ($alerts as $alert)
                        <div class="adm-alert adm-tone-{{ $alert['tone'] }}">
                            <span class="adm-alert__icon">
                                <x-dynamic-component :component="'lucide-' . $alert['icon']" width="20" height="20" stroke-width="2" />
                            </span>
                            <div>
                                <div class="adm-alert__title"><strong>{{ $alert['count'] }}</strong> {{ $alert['title'] }}</div>
                                <div class="adm-alert__note">{{ $alert['note'] }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="adm-empty">Sin alertas activas. La operación está bajo control.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    {{-- ── 5. BOTTOM CARDS ───────────────────────────────────────── --}}
    <div class="adm-bottom">
        @foreach ($bottomCards as $card)
            <div class="adm-card adm-stat adm-tone-{{ $card['tone'] }}">
                <span class="adm-stat__icon">
                    <x-dynamic-component :component="'lucide-' . $card['icon']" width="24" height="24" stroke-width="2" />
                </span>
                <div>
                    <div class="adm-stat__value">{{ $card['value'] }}</div>
                    <div class="adm-stat__label">{{ $card['label'] }}</div>
                    <div class="adm-stat__note">{{ $card['note'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
