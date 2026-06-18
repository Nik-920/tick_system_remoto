@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    /**
     * @var string $userName
     * @var list<array<string, mixed>> $kpis
     * @var list<array<string, mixed>> $recent
     * @var list<array<string, mixed>> $quickActions
     * @var array<string, mixed> $summary
     * @var list<array<string, mixed>> $activity
     * @var string $tip
     *
     * LIVE DATA — greeting, KPI strip, recent list, status donut and average are
     * all real and own-scoped (from ReporterTicketsBoardQuery). Only the tip and
     * the "Actividad reciente" panel are curated placeholders. No mutations, no
     * maintenance actions. Parallel to /dashboard; does not replace it.
     */
    $priorityTone = ['high' => 'high', 'medium' => 'medium', 'low' => 'low', 'critical' => 'high'];

    $donutStops = collect($summary['donut'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
    $donutAria = collect($summary['donut'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")
        ->implode(', ');
@endphp

<div class="rep-page rep-dash">

    {{-- ── 1. WELCOME HERO ───────────────────────────────────────── --}}
    <section class="rep-hero">
        <div class="rep-hero__content">
            <p class="rep-hero__eyebrow">Panel del reportero</p>
            <h1 class="rep-hero__title">¡Hola, {{ $userName }}! 👋</h1>
            <p class="rep-hero__subtitle">
                Aquí puedes crear y dar seguimiento a todas las incidencias que has reportado.
            </p>
        </div>
        <a href="{{ route('tickets.create') }}" class="rep-hero__cta">
            <x-lucide-plus width="17" height="17" stroke-width="2.5" />
            Crear nuevo ticket
        </a>
    </section>

    {{-- ── 2. KPI STRIP ──────────────────────────────────────────── --}}
    <section class="rep-kpis" aria-label="Resumen de mis tickets">
        @foreach ($kpis as $kpi)
            <article class="rep-kpi rep-tone-{{ $kpi['tone'] }}">
                <span class="rep-kpi__icon">
                    <x-dynamic-component :component="'lucide-' . $kpi['icon']" width="20" height="20" stroke-width="2" />
                </span>
                <div class="rep-kpi__body">
                    <span class="rep-kpi__value">{{ $kpi['value'] }}</span>
                    <span class="rep-kpi__label">{{ $kpi['label'] }}</span>
                </div>
            </article>
        @endforeach
    </section>

    {{-- ── 3. CONTENT LAYOUT ─────────────────────────────────────── --}}
    <div class="rep-layout">

        {{-- LEFT --}}
        <div class="rep-main rep-dash-main">

            {{-- Recent reports --}}
            <section class="rep-panel">
                <div class="rep-panel__head">
                    <h2 class="rep-panel__title">
                        <x-lucide-file-text width="16" height="16" stroke-width="2" />
                        Mis reportes recientes
                    </h2>
                    <a href="{{ route('reporter.tickets.index') }}" class="rep-link rep-link--inline">
                        Ver todos
                        <x-lucide-arrow-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>

                <div class="rep-list">
                    @forelse ($recent as $t)
                        <article class="rep-item rep-tone-{{ $t['status_tone'] }}" aria-labelledby="rep-dash-title-{{ $loop->index }}">
                            <span class="rep-item__rail" aria-hidden="true"></span>

                            <div class="rep-item__icon">
                                <x-dynamic-component :component="'lucide-' . $t['icon']" width="20" height="20" stroke-width="2" />
                            </div>

                            <div class="rep-item__body">
                                <div class="rep-item__top">
                                    <h3 class="rep-item__title" id="rep-dash-title-{{ $loop->index }}">{{ $t['title'] }}</h3>
                                    <span class="rep-item__id">{{ $t['ref'] }}</span>
                                </div>
                                <div class="rep-item__meta">
                                    <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                                    <span>{{ $t['location'] }}</span>
                                    <span class="rep-item__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $t['category'] }}</span>
                                </div>
                                <div class="rep-item__tags">
                                    <span class="rep-badge rep-status--{{ $t['status'] }}">
                                        <span class="rep-badge__dot" aria-hidden="true"></span>
                                        {{ $t['status_label'] }}
                                    </span>
                                    <span class="rep-prio-wrap">
                                        <span class="rep-prio-label">Prioridad</span>
                                        <span class="rep-badge rep-prio rep-tone-{{ $priorityTone[$t['priority']] ?? 'neutral' }}">
                                            <span class="rep-badge__dot" aria-hidden="true"></span>
                                            {{ $t['priority_label'] }}
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="rep-item__side">
                                <div class="rep-item__update">
                                    <x-lucide-clock width="13" height="13" stroke-width="2" />
                                    <span class="rep-item__update-at">{{ $t['updated'] }}</span>
                                </div>
                                <div class="rep-item__actions">
                                    <a href="{{ route('reporter.tickets.show', $t['id']) }}" class="rep-btn rep-btn--ghost">Ver</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rep-empty">
                            <x-lucide-inbox width="32" height="32" stroke-width="1.5" />
                            <p class="rep-empty__title">Aún no has reportado incidencias</p>
                            <p class="rep-empty__note">Cuando crees un ticket aparecerá aquí para que le des seguimiento.</p>
                            <a href="{{ route('tickets.create') }}" class="rep-btn-primary">
                                <x-lucide-plus width="16" height="16" stroke-width="2.5" />
                                Crear ticket
                            </a>
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Quick actions --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-layout-dashboard width="16" height="16" stroke-width="2" />
                    Acciones rápidas
                </h2>
                <div class="rep-quick">
                    @foreach ($quickActions as $action)
                        @if ($action['href'])
                            <a href="{{ $action['href'] }}" class="rep-quick__tile rep-tone-{{ $action['tone'] }}">
                                <span class="rep-quick__icon">
                                    <x-dynamic-component :component="'lucide-' . $action['icon']" width="20" height="20" stroke-width="2" />
                                </span>
                                <span class="rep-quick__label">{{ $action['label'] }}</span>
                                <x-lucide-arrow-right class="rep-quick__arrow" width="15" height="15" stroke-width="2.5" />
                            </a>
                        @else
                            <button type="button" class="rep-quick__tile rep-tone-{{ $action['tone'] }}" title="Disponible próximamente">
                                <span class="rep-quick__icon">
                                    <x-dynamic-component :component="'lucide-' . $action['icon']" width="20" height="20" stroke-width="2" />
                                </span>
                                <span class="rep-quick__label">{{ $action['label'] }}</span>
                                <x-lucide-arrow-right class="rep-quick__arrow" width="15" height="15" stroke-width="2.5" />
                            </button>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- Reporting tip --}}
            <section class="rep-panel rep-advice">
                <div class="rep-advice__icon rep-tone-purple">
                    <x-lucide-lightbulb width="20" height="20" stroke-width="2" />
                </div>
                <div class="rep-advice__body">
                    <h2 class="rep-panel__title rep-advice__title">Consejo para reportar mejor</h2>
                    <p class="rep-advice__text">{{ $tip }}</p>
                </div>
            </section>
        </div>

        {{-- RIGHT: insights rail --}}
        <aside class="rep-rail" aria-label="Insights de mis tickets">

            {{-- A. Status donut --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                    Estado de mis tickets
                </h2>
                <div class="rep-donut-wrap">
                    <div class="rep-donut {{ $summary['total'] > 0 ? '' : 'rep-donut--empty' }}" role="img"
                         @if ($summary['total'] > 0) style="background: conic-gradient({{ $donutStops }});" @endif
                         aria-label="Distribución por estado: {{ $summary['total'] > 0 ? $donutAria : 'sin tickets' }}">
                        <div class="rep-donut__center">
                            <span class="rep-donut__total">{{ $summary['total'] }}</span>
                            <span class="rep-donut__caption">tickets</span>
                        </div>
                    </div>
                    <div class="rep-legend">
                        @foreach ($summary['donut'] as $seg)
                            <div class="rep-legend__row rep-tone-{{ $seg['tone'] }}">
                                <span class="rep-legend__dot" aria-hidden="true"></span>
                                <span class="rep-legend__label">{{ $seg['label'] }}</span>
                                <span class="rep-legend__value">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="rep-panel__total">
                    <span>Total</span><strong>{{ $summary['total'] }} tickets</strong>
                </div>
            </section>

            {{-- B. Average response time --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempo promedio de respuesta
                </h2>
                <div class="rep-avg">
                    <div class="rep-avg__icon rep-tone-purple">
                        <x-lucide-clock width="20" height="20" stroke-width="2" />
                    </div>
                    <div>
                        <div class="rep-avg__value">{{ $summary['avg_value'] }}</div>
                        <span class="rep-avg__note">{{ $summary['avg_note'] }}</span>
                    </div>
                </div>
            </section>

            {{-- C. Recent activity — honest placeholder (no fabricated events). --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-activity width="16" height="16" stroke-width="2" />
                    Actividad reciente
                    <span class="rep-soon">Próximamente</span>
                </h2>
                <div class="rep-evidence-empty">
                    <x-lucide-activity width="26" height="26" stroke-width="1.5" />
                    <p class="rep-empty__note">El registro de actividad estará disponible pronto. Mientras tanto, revisa el seguimiento de cada ticket.</p>
                </div>
            </section>

        </aside>
    </div>
</div>
@endsection
