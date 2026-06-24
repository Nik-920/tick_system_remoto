@extends('layouts.app')

@section('title', 'Moderación de Comunidad')

@section('content')
<div class="comm-mod-page">

    {{-- ===== HERO ===== --}}
    <section class="comm-mod-hero">
        <div class="comm-mod-hero-inner">
            <div>
                <p class="comm-mod-overline">Gestión · Comunidad</p>
                <h1 class="comm-mod-title">Moderación de Comunidad</h1>
                <p class="comm-mod-subtitle">
                    Revisa qué tickets aparecen u ocultan en la pestaña Comunidad sin afectar su estado operativo.
                </p>
            </div>
        </div>
    </section>

    {{-- Alerts --}}
    @if(session('status'))
    <div class="alert-success" role="alert">{{ session('status') }}</div>
    @endif

    {{-- ===== SUMMARY CARDS ===== --}}
    <section class="comm-mod-summary" aria-label="Resumen de moderación">
        <div class="comm-mod-kpi-card">
            <span class="comm-mod-kpi-value">{{ number_format($vm->summary['total_visible']) }}</span>
            <span class="comm-mod-kpi-label">Visibles en Comunidad</span>
        </div>
        <div class="comm-mod-kpi-card comm-mod-kpi-card--warning">
            <span class="comm-mod-kpi-value">{{ number_format($vm->summary['total_hidden']) }}</span>
            <span class="comm-mod-kpi-label">Ocultos en Comunidad</span>
        </div>
        <div class="comm-mod-kpi-card">
            <span class="comm-mod-kpi-value">{{ number_format($vm->summary['hidden_last_7d']) }}</span>
            <span class="comm-mod-kpi-label">Ocultos últimos 7 días</span>
        </div>
        <div class="comm-mod-kpi-card comm-mod-kpi-card--alert">
            <span class="comm-mod-kpi-value">{{ number_format($vm->summary['visible_high_priority']) }}</span>
            <span class="comm-mod-kpi-label">Visibles alta prioridad</span>
        </div>
        <div class="comm-mod-kpi-card comm-mod-kpi-card--reports {{ $vm->summary['pending_reports'] > 0 ? 'comm-mod-kpi-card--reports-active' : '' }}">
            <span class="comm-mod-kpi-value">{{ number_format($vm->summary['pending_reports']) }}</span>
            <span class="comm-mod-kpi-label">Reportes pendientes</span>
        </div>
    </section>

    {{-- ===== FILTROS ===== --}}
    @include('community.admin.moderation.partials.filters')

    {{-- ===== LISTA DE CARDS ===== --}}
    <section class="adm-comm-queue" aria-label="Cola de moderación">
        <div class="comm-mod-dataset-head">
            <p class="comm-mod-dataset-count">
                {{ number_format($vm->paginator->total()) }} ticket(s) en esta vista
            </p>
            @if($vm->paginator->lastPage() > 1)
            <span class="comm-mod-dataset-chip">
                Página {{ $vm->paginator->currentPage() }} de {{ $vm->paginator->lastPage() }}
            </span>
            @endif
        </div>

        <div class="adm-comm-list">
            @forelse($vm->items as $item)
                @include('community.admin.moderation.partials.card', ['item' => $item])
            @empty
                @include('community.admin.moderation.partials.empty')
            @endforelse
        </div>
    </section>

    {{-- ===== PAGINACIÓN ===== --}}
    <div class="c-pagination">
        {{ $vm->paginator->links() }}
    </div>

</div>
@endsection
