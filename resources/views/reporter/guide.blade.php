@extends('layouts.app')

@section('title', 'Guía del reporter')

@section('content')
@php
    /**
     * @var list<array<string, string>> $steps
     * @var list<string> $practices
     * @var array<string, array{title: string, text: string}> $examples
     * @var list<array<string, string>> $categories
     * @var list<array<string, string>> $info
     *
     * FIRST VISUAL PHASE — static informational page (see ReporterGuideController).
     * No live data, no mutations, no maintenance actions.
     */
@endphp

<div class="rep-page rep-guide">

    {{-- ── HEADER ────────────────────────────────────────────────── --}}
    <header class="rep-guide-head">
        <div>
            <h1 class="rep-header__title">Guía del reporter</h1>
            <p class="rep-header__subtitle">
                Sigue estas recomendaciones para crear reportes claros y ayudar a que se resuelvan más rápido.
            </p>
        </div>
        <div class="rep-guide-cta">
            <div class="rep-guide-cta__icon" aria-hidden="true">
                <x-lucide-circle-help width="22" height="22" stroke-width="2" />
            </div>
            <div class="rep-guide-cta__body">
                <p class="rep-guide-cta__title">¿Listo para reportar?</p>
                <p class="rep-guide-cta__text">Crea un ticket en menos de un minuto.</p>
            </div>
            <a href="{{ route('tickets.create') }}" class="rep-btn-primary rep-guide-cta__btn">
                <x-lucide-plus width="16" height="16" stroke-width="2.5" />
                Crear nuevo ticket
            </a>
        </div>
    </header>

    {{-- ── 1. STEPS ──────────────────────────────────────────────── --}}
    <section class="rep-panel">
        <h2 class="rep-panel__title">
            <x-lucide-list-checks width="16" height="16" stroke-width="2" />
            Pasos para reportar una incidencia
        </h2>
        <ol class="rep-guide-steps">
            @foreach ($steps as $step)
                <li class="rep-guide-step">
                    <span class="rep-guide-step__node">
                        <x-dynamic-component :component="'lucide-' . $step['icon']" width="20" height="20" stroke-width="2" />
                        <span class="rep-guide-step__num" aria-hidden="true">{{ $loop->iteration }}</span>
                    </span>
                    <span class="rep-guide-step__title">{{ $step['title'] }}</span>
                    <span class="rep-guide-step__desc">{{ $step['desc'] }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ── 2. PRACTICES + EXAMPLES ───────────────────────────────── --}}
    <div class="rep-guide-grid">
        <section class="rep-panel">
            <h2 class="rep-panel__title">
                <x-lucide-circle-check width="16" height="16" stroke-width="2" />
                Buenas prácticas para un mejor reporte
            </h2>
            <ul class="rep-practice">
                @foreach ($practices as $practice)
                    <li class="rep-practice__item">
                        <span class="rep-practice__check" aria-hidden="true">
                            <x-lucide-check width="13" height="13" stroke-width="3" />
                        </span>
                        <span>{{ $practice }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rep-panel">
            <h2 class="rep-panel__title">
                <x-lucide-sparkles width="16" height="16" stroke-width="2" />
                Ejemplos de buenos reportes
            </h2>
            <div class="rep-example rep-example--good">
                <div class="rep-example__head">
                    <x-lucide-thumbs-up width="15" height="15" stroke-width="2.5" />
                    {{ $examples['good']['title'] }}
                </div>
                <p class="rep-example__text">{{ $examples['good']['text'] }}</p>
            </div>
            <div class="rep-example rep-example--bad">
                <div class="rep-example__head">
                    <x-lucide-thumbs-down width="15" height="15" stroke-width="2.5" />
                    {{ $examples['bad']['title'] }}
                </div>
                <p class="rep-example__text">{{ $examples['bad']['text'] }}</p>
            </div>
        </section>
    </div>

    {{-- ── 3. CATEGORIES + INFO ──────────────────────────────────── --}}
    <div class="rep-guide-grid">
        <section class="rep-panel">
            <h2 class="rep-panel__title">
                <x-lucide-folder width="16" height="16" stroke-width="2" />
                Categorías disponibles
            </h2>
            <div class="rep-cats">
                @foreach ($categories as $cat)
                    <div class="rep-cat rep-tone-{{ $cat['tone'] }}">
                        <span class="rep-cat__icon">
                            <x-dynamic-component :component="'lucide-' . $cat['icon']" width="20" height="20" stroke-width="2" />
                        </span>
                        <span class="rep-cat__label">{{ $cat['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rep-panel">
            <h2 class="rep-panel__title">
                <x-lucide-info width="16" height="16" stroke-width="2" />
                Información importante
            </h2>
            <ul class="rep-info">
                @foreach ($info as $item)
                    <li class="rep-info__item">
                        <span class="rep-info__icon" aria-hidden="true">
                            <x-dynamic-component :component="'lucide-' . $item['icon']" width="16" height="16" stroke-width="2" />
                        </span>
                        <span>{{ $item['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection
