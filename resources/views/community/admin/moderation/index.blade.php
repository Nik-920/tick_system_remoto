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
        <div class="comm-mod-kpi-card comm-mod-tone-primary">
            <div class="comm-mod-kpi-icon">
                <x-lucide-eye width="22" height="22" stroke-width="2" />
            </div>
            <div class="comm-mod-kpi-body">
                <span class="comm-mod-kpi-value">{{ number_format($vm->summary['total_visible']) }}</span>
                <span class="comm-mod-kpi-label">Visibles en Comunidad</span>
            </div>
        </div>
        <div class="comm-mod-kpi-card comm-mod-tone-neutral">
            <div class="comm-mod-kpi-icon">
                <x-lucide-eye-off width="22" height="22" stroke-width="2" />
            </div>
            <div class="comm-mod-kpi-body">
                <span class="comm-mod-kpi-value">{{ number_format($vm->summary['total_hidden']) }}</span>
                <span class="comm-mod-kpi-label">Ocultos en Comunidad</span>
            </div>
        </div>
        <div class="comm-mod-kpi-card comm-mod-tone-info">
            <div class="comm-mod-kpi-icon">
                <x-lucide-history width="22" height="22" stroke-width="2" />
            </div>
            <div class="comm-mod-kpi-body">
                <span class="comm-mod-kpi-value">{{ number_format($vm->summary['hidden_last_7d']) }}</span>
                <span class="comm-mod-kpi-label">Ocultos últimos 7 días</span>
            </div>
        </div>
        <div class="comm-mod-kpi-card comm-mod-tone-high">
            <div class="comm-mod-kpi-icon">
                <x-lucide-alert-triangle width="22" height="22" stroke-width="2" />
            </div>
            <div class="comm-mod-kpi-body">
                <span class="comm-mod-kpi-value">{{ number_format($vm->summary['visible_high_priority']) }}</span>
                <span class="comm-mod-kpi-label">Visibles alta prioridad</span>
            </div>
        </div>
        <div class="comm-mod-kpi-card comm-mod-tone-medium{{ $vm->summary['pending_reports'] > 0 ? ' comm-mod-kpi-card--active' : '' }}">
            <div class="comm-mod-kpi-icon">
                <x-lucide-flag width="22" height="22" stroke-width="2" />
            </div>
            <div class="comm-mod-kpi-body">
                <span class="comm-mod-kpi-value">{{ number_format($vm->summary['pending_reports']) }}</span>
                <span class="comm-mod-kpi-label">Reportes pendientes</span>
            </div>
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

{{-- ============================================================
   Progressive JS — filters toggle + select auto-submit.
   Filtering works server-side without JS; this only smooths the UX.
   ============================================================ --}}
<script>
(function () {
    'use strict';

    var toggleBtn = document.querySelector('[data-cmq-filters-toggle]');
    var panel = document.getElementById('comm-mod-filters');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function () {
            var open = toggleBtn.getAttribute('aria-expanded') === 'true';
            toggleBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.hidden = open;
        });
    }

    document.querySelectorAll('[data-cmq-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) { sel.form.submit(); }
        });
    });
})();
</script>
@endsection
