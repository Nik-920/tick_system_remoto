@extends('layouts.app')

@section('content')
<main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- ── Botón volver superior ── --}}
    <div class="mb-5">
        <a href="{{ route('tickets.assignments') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a mis tickets
        </a>
    </div>

    {{-- ── Header del ticket ── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                {{-- Icono --}}
                <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4"/>
                    </svg>
                </div>
                {{-- Título y código --}}
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 leading-tight">
                        {{ $ticket->title ?? 'Sin título' }}
                    </h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm font-mono text-slate-500">
                            #{{ $ticket->code ?? 'INC-0000-0000' }}
                        </span>
                        <button
                            data-copy-ticket="{{ $ticket->code ?? '' }}"
                            aria-label="Copiar código del ticket"
                            class="copy-btn inline-flex items-center gap-1 text-xs text-blue-500 hover:text-blue-700 transition-colors px-1.5 py-0.5 rounded-md hover:bg-blue-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="9" y="9" width="13" height="13" rx="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                            <span class="copy-label">Copiar</span>
                        </button>
                    </div>
                    {{-- Badges de estado y prioridad --}}
                    <div class="mt-2 flex flex-wrap gap-2">
                        @php
                            $status = $ticket->status ?? null;
                            $statusColors = [
                                'En progreso'  => 'bg-blue-100 text-blue-700 border-blue-200',
                                'Abierto'      => 'bg-green-100 text-green-700 border-green-200',
                                'En revisión'  => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                'Resuelto'     => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                'Cancelado'    => 'bg-red-100 text-red-700 border-red-200',
                            ];
                            $statusClass = $statusColors[$status] ?? 'bg-slate-100 text-slate-600 border-slate-200';

                            $priority = $ticket->priority ?? null;
                            $priorityColors = [
                                'Alta'   => 'bg-red-50 text-red-600 border-red-200',
                                'Media'  => 'bg-amber-50 text-amber-600 border-amber-200',
                                'Baja'   => 'bg-sky-50 text-sky-600 border-sky-200',
                            ];
                            $priorityClass = $priorityColors[$priority] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                        @endphp
                        @if($status)
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full border {{ $statusClass }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80"></span>
                            {{ $status }}
                        </span>
                        @endif
                        @if($priority)
                        <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full border {{ $priorityClass }}">
                            {{ $priority }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Botón de acción principal --}}
            <div class="flex-shrink-0 flex flex-wrap gap-2">
                @if($canClaim ?? false)
                    {{-- TODO: conectar con route('tickets.claim', $ticket) --}}
                    <form method="POST" action="#">
                        @csrf
                        <button type="submit"
                            onclick="return confirm('¿Deseas tomar este ticket?')"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Tomar ticket
                        </button>
                    </form>
                @elseif($canStart ?? false)
                    {{-- TODO: conectar con route('tickets.update-state', $ticket) --}}
                    <form method="POST" action="#">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Iniciar atención
                        </button>
                    </form>
                @elseif($canContinue ?? false)
                    {{-- TODO: conectar con route('tickets.update-state', $ticket) --}}
                    <form method="POST" action="#">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Continuar atención
                        </button>
                    </form>
                @endif

                {{-- Botón editar (ejemplo si el rol lo permite) --}}
                {{-- TODO: mostrar solo si el sistema permite editar para Maintenance --}}
                {{--
                <a href="{{ route('tickets.edit', $ticket) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-medium shadow-sm hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/></svg>
                    Editar ticket
                </a>
                --}}
            </div>
        </div>

        {{-- ── Métricas rápidas ── --}}
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 border-t border-slate-100 pt-4">
            <div class="flex items-start gap-2.5">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Fecha de creación</p>
                    <p class="text-sm text-slate-700 font-semibold mt-0.5">
                        {{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i A') : '—' }}
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-2.5">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Última actualización</p>
                    <p class="text-sm text-slate-700 font-semibold mt-0.5">
                        {{ $ticket->updated_at ? $ticket->updated_at->format('d/m/Y H:i A') : '—' }}
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-2.5">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Tiempo transcurrido</p>
                    <p class="text-sm text-slate-700 font-semibold mt-0.5">
                        {{ $ticket->created_at ? $ticket->created_at->diffForHumans(null, true, true, 3) : '—' }}
                    </p>
                </div>
            </div>

            @if($assignment && $assignment->assigned_at ?? false)
            <div class="flex items-start gap-2.5">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Desde asignación</p>
                    <p class="text-sm text-slate-700 font-semibold mt-0.5">
                        {{ \Carbon\Carbon::parse($assignment->assigned_at)->diffForHumans(null, true, true, 3) }}
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Grid principal ── --}}
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">

        {{-- ── Columna izquierda ── --}}
        <div class="flex flex-col gap-6">

            {{-- ① Información principal del ticket --}}
            <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="info-heading">
                <h2 id="info-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">1</span>
                    Información principal del ticket
                </h2>

                {{-- Descripción --}}
                <div class="mb-5">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Descripción del problema</p>
                    <div class="bg-slate-50 rounded-xl p-3.5 text-sm text-slate-700 leading-relaxed border border-slate-100 min-h-[60px]">
                        {{ $ticket->description ?? 'Sin descripción registrada.' }}
                    </div>
                </div>

                {{-- Datos de ubicación --}}
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5 text-sm mb-5">
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Ubicación</p>
                        <div class="flex items-center gap-1 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {{ $ticket->location ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Edificio</p>
                        <p class="text-slate-700">{{ $ticket->building ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Piso</p>
                        <p class="text-slate-700">{{ $ticket->floor ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Aula / Lab / Sala</p>
                        <p class="text-slate-700">{{ $ticket->room ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Código de ubicación</p>
                        <p class="text-slate-700 font-mono text-xs">{{ $ticket->location_code ?? '—' }}</p>
                    </div>
                </div>

                {{-- Datos del ticket --}}
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5 text-sm mb-5">
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Categoría</p>
                        <div class="flex items-center gap-1 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            {{ $ticket->category ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Prioridad</p>
                        @if($ticket->priority ?? null)
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full border {{ $priorityClass ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80"></span>
                                {{ $ticket->priority }}
                            </span>
                        @else
                            <p class="text-slate-700">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Estado</p>
                        @if($ticket->status ?? null)
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full border {{ $statusClass ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80"></span>
                                {{ $ticket->status }}
                            </span>
                        @else
                            <p class="text-slate-700">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Reportado por</p>
                        <div class="flex items-center gap-1.5">
                            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-rose-100 text-rose-600 text-[10px] font-bold flex items-center justify-center">
                                {{ strtoupper(substr($ticket->reporter->name ?? 'R', 0, 1)) }}
                            </span>
                            <span class="text-slate-700 truncate text-xs">{{ $ticket->reporter->name ?? '—' }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Correo</p>
                        <p class="text-slate-700 truncate text-xs">{{ $ticket->reporter->email ?? '—' }}</p>
                    </div>
                </div>

                <div class="text-sm mb-5">
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Fecha de creación</p>
                    <p class="text-slate-700">{{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i A') : '—' }}</p>
                </div>

                @if($ticket->location ?? null)
                <div>
                    {{-- TODO: conectar con route adecuado de ubicación --}}
                    <button type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 border border-blue-200 rounded-lg px-3 py-1.5 hover:bg-blue-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Ver detalles de ubicación
                    </button>
                </div>
                @endif
            </section>

            {{-- ② Estado operativo del ticket --}}
            <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="operative-heading">
                <h2 id="operative-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">2</span>
                    Estado operativo del ticket
                </h2>

                {{-- Métricas operativas --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-5 text-sm">
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1">Estado actual de atención</p>
                        @if($ticket->status ?? null)
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full border {{ $statusClass ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80"></span>
                                {{ $ticket->status }}
                            </span>
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1">Último cambio de estado</p>
                        <p class="text-slate-700">
                            {{ ($stateHistory && $stateHistory->first()) ? \Carbon\Carbon::parse($stateHistory->first()->created_at)->format('d/m/Y H:i A') : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1">Tiempo desde asignación</p>
                        <div class="flex items-center gap-1 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            {{ ($assignment && $assignment->assigned_at) ? \Carbon\Carbon::parse($assignment->assigned_at)->diffForHumans(null, true, true, 3) : '—' }}
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1">Tiempo en progreso</p>
                        <div class="flex items-center gap-1 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            {{ $ticket->time_in_progress ?? '—' }}
                        </div>
                    </div>
                </div>

                {{-- Último comentario técnico --}}
                <div class="mb-5">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Último comentario técnico</p>
                    <div class="bg-slate-50 rounded-xl p-3.5 text-sm text-slate-700 leading-relaxed border border-slate-100 min-h-[50px]">
                        {{ $ticket->last_technical_comment ?? 'Sin comentarios técnicos registrados.' }}
                    </div>
                </div>

                {{-- Acción recomendada --}}
                <div class="mb-5">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Acción recomendada</p>
                    <p class="text-sm text-slate-600">{{ $ticket->recommended_action ?? 'Revisa el estado actual y actúa según corresponda.' }}</p>
                </div>

                {{-- Botones operativos --}}
                <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
                    @if($canContinue ?? false)
                        {{-- TODO: conectar con route('tickets.update-state', $ticket) --}}
                        <form method="POST" action="#">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Continuar atención
                            </button>
                        </form>
                    @endif

                    @if($canAddEvidence ?? false)
                        {{-- TODO: conectar con route('tickets.evidence.create', $ticket) --}}
                        <a href="#"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-blue-200 text-blue-600 text-sm font-medium hover:bg-blue-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            Agregar evidencia
                        </a>
                    @endif

                    @if($canAddComment ?? false)
                        {{-- TODO: conectar con route('tickets.comment.create', $ticket) --}}
                        <a href="#"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Agregar comentario
                        </a>
                    @endif

                    @if($canRelease ?? false)
                        {{-- TODO: conectar con route('tickets.release', $ticket) --}}
                        <form method="POST" action="#" data-confirm="¿Estás seguro de liberar este ticket?">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                Liberar ticket
                            </button>
                        </form>
                    @endif

                    @if($canResolve ?? false)
                        {{-- TODO: conectar con route('tickets.update-state', $ticket) para resolver --}}
                        <form method="POST" action="#" data-confirm="¿Confirmas la resolución de este ticket?">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Resolver ticket
                            </button>
                        </form>
                    @endif

                    @if($canViewHistory ?? false)
                        <a href="#history"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                            Ver historial
                        </a>
                    @endif
                </div>
            </section>

        </div>{{-- fin columna izquierda --}}

        {{-- ── Columna derecha ── --}}
        <div class="flex flex-col gap-6">

            {{-- ③ Información de asignación --}}
            <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="assignment-heading">
                <h2 id="assignment-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">3</span>
                    Información de asignación
                </h2>

                @if($assignment ?? null)
                <div class="space-y-4 text-sm">
                    {{-- Asignado a --}}
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1.5">Asignado a</p>
                        <div class="flex items-center gap-2.5">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                {{ strtoupper(substr($assignment->technician->name ?? 'T', 0, 2)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ $assignment->technician->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $assignment->technician->email ?? '—' }}</p>
                            </div>
                            @auth
                                @if(auth()->id() === ($assignment->technician->id ?? null))
                                <span class="flex-shrink-0 inline-flex items-center text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">
                                    Técnico actual
                                </span>
                                @endif
                            @endauth
                        </div>
                    </div>

                    {{-- Asignado por --}}
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1.5">Asignado por</p>
                        <div class="flex items-center gap-2.5">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center">
                                {{ strtoupper(substr($assignment->assignedBy->name ?? 'A', 0, 2)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ $assignment->assignedBy->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $assignment->assignedBy->email ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Fecha de asignación --}}
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Fecha de asignación</p>
                        <div class="flex items-center gap-1.5 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            {{ $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at)->format('d/m/Y H:i A') : '—' }}
                        </div>
                    </div>

                    {{-- Tipo de asignación --}}
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Tipo de asignación</p>
                        <div class="flex items-center gap-1.5 text-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                            {{ $assignment->type ?? 'Manual' }}
                        </div>
                    </div>

                    {{-- Estado de bloqueo --}}
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-1">Estado de bloqueo</p>
                        @php $locked = $assignment->is_locked ?? false; @endphp
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 {{ $locked ? 'text-amber-500' : 'text-emerald-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                @if($locked)
                                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                @else
                                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                                @endif
                            </svg>
                            <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full border
                                {{ $locked ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                {{ $locked ? 'Bloqueado' : 'Desbloqueado' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $locked ? 'No puede ser reasignado' : 'Puede ser reasignado' }}
                        </p>
                    </div>
                </div>
                @else
                    <p class="text-sm text-slate-500 py-2">No hay información de asignación disponible.</p>
                @endif
            </section>

            {{-- ④ Acciones rápidas --}}
            <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="quick-actions-heading">
                <h2 id="quick-actions-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">4</span>
                    Acciones rápidas
                </h2>

                <nav class="space-y-1" aria-label="Acciones disponibles">
                    {{-- TODO: mostrar solo si el sistema permite editar para Maintenance --}}
                    {{--
                    <a href="{{ route('tickets.edit', $ticket) }}"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors group">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Editar ticket
                    </a>
                    --}}

                    @if($canAddEvidence ?? false)
                    {{-- TODO: conectar con route('tickets.evidence.create', $ticket) --}}
                    <a href="#"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors group">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        Agregar evidencia
                    </a>
                    @endif

                    @if($canAddComment ?? false)
                    {{-- TODO: conectar con route('tickets.comment.create', $ticket) --}}
                    <a href="#"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors group">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Agregar comentario técnico
                    </a>
                    @endif

                    @if($canRelease ?? false)
                    {{-- TODO: conectar con route('tickets.release', $ticket) --}}
                    <a href="#"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors group">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Liberar ticket
                    </a>
                    @endif

                    @if($canViewHistory ?? false)
                    <a href="#history"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors group">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                        Ver historial completo
                    </a>
                    @endif
                </nav>

                {{-- Caja de información importante --}}
                <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-3.5">
                    <div class="flex items-start gap-2 mb-2">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                        <p class="text-xs font-semibold text-amber-700">Información importante</p>
                    </div>
                    <ul class="space-y-1 pl-6" role="list">
                        <li class="text-xs text-amber-700 list-disc">Revisa toda la información antes de realizar acciones.</li>
                        <li class="text-xs text-amber-700 list-disc">Agrega comentarios claros y detallados.</li>
                        <li class="text-xs text-amber-700 list-disc">Sube evidencias relevantes para mantener buen seguimiento.</li>
                    </ul>
                </div>
            </section>

        </div>{{-- fin columna derecha --}}

    </div>{{-- fin grid principal --}}

    {{-- ── Evidencias / adjuntos ── --}}
    <section class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="evidence-heading">
        <h2 id="evidence-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">5</span>
            Evidencias / adjuntos
        </h2>

        <div class="grid gap-6 md:grid-cols-2">

            {{-- Evidencias del reporter --}}
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <p class="text-sm font-medium text-slate-700">Evidencias del reporter</p>
                    @if($reporterEvidence ?? null)
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold">
                        {{ $reporterEvidence->count() }}
                    </span>
                    @endif
                </div>

                @if($reporterEvidence && $reporterEvidence->count() > 0)
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-left">
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Archivo</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Tipo</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Fecha</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Subido por</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Ver</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($reporterEvidence as $ev)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-3 py-2.5 text-slate-700 font-medium max-w-[120px] truncate">
                                        {{ $ev->filename ?? 'archivo' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ $ev->type ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap">
                                        {{ $ev->created_at ? \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex w-6 h-6 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold items-center justify-center"
                                              title="{{ $ev->uploadedBy->name ?? '' }}">
                                            {{ strtoupper(substr($ev->uploadedBy->name ?? 'R', 0, 2)) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{-- TODO: conectar con ruta de descarga/visualización --}}
                                        <a href="#" aria-label="Ver archivo {{ $ev->filename ?? '' }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($ticket->reporter_comment ?? null)
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Comentario del reporter:</p>
                        <p class="text-xs text-slate-600 leading-relaxed">{{ $ticket->reporter_comment }}</p>
                    </div>
                    @endif
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center rounded-xl border border-dashed border-slate-200">
                        <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        <p class="text-xs text-slate-400">No hay evidencias registradas.</p>
                    </div>
                @endif
            </div>

            {{-- Evidencias de maintenance --}}
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <p class="text-sm font-medium text-slate-700">Evidencias de maintenance</p>
                    @if($maintenanceEvidence ?? null)
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold">
                        {{ $maintenanceEvidence->count() }}
                    </span>
                    @endif
                </div>

                @if($maintenanceEvidence && $maintenanceEvidence->count() > 0)
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-left">
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Archivo</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Tipo</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Fecha</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Subido por</th>
                                    <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Ver</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($maintenanceEvidence as $ev)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-3 py-2.5 text-slate-700 font-medium max-w-[120px] truncate">
                                        {{ $ev->filename ?? 'archivo' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ $ev->type ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap">
                                        {{ $ev->created_at ? \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold items-center justify-center"
                                              title="{{ $ev->uploadedBy->name ?? '' }}">
                                            {{ strtoupper(substr($ev->uploadedBy->name ?? 'M', 0, 2)) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{-- TODO: conectar con ruta de descarga/visualización --}}
                                        <a href="#" aria-label="Ver archivo {{ $ev->filename ?? '' }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($ticket->technical_comment ?? null)
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Comentario técnico:</p>
                        <p class="text-xs text-slate-600 leading-relaxed">{{ $ticket->technical_comment }}</p>
                    </div>
                    @endif
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center rounded-xl border border-dashed border-slate-200">
                        <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        <p class="text-xs text-slate-400">No hay evidencias registradas.</p>
                    </div>
                @endif
            </div>

        </div>
    </section>

    {{-- ── Historial de estados ── --}}
    @if($canViewHistory ?? false)
    <section id="history" class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="history-heading">
        <h2 id="history-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">6</span>
            Historial de estados
        </h2>

        @if($stateHistory && $stateHistory->count() > 0)
        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 text-left">
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Estado anterior</th>
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Estado nuevo</th>
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Cambiado por</th>
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Tipo de acción</th>
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide">Comentario</th>
                        <th class="px-3 py-2.5 font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stateHistory as $history)
                    @php
                        $prevStatus = $history->previous_status ?? null;
                        $newStatus  = $history->new_status ?? null;
                        $prevClass  = $statusColors[$prevStatus] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                        $newClass   = $statusColors[$newStatus]  ?? 'bg-slate-100 text-slate-600 border-slate-200';
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-3 py-2.5">
                            @if($prevStatus)
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $prevClass }}">
                                {{ $prevStatus }}
                            </span>
                            @else
                            <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            @if($newStatus)
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $newClass }}">
                                {{ $newStatus }}
                            </span>
                            @else
                            <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold flex items-center justify-center">
                                    {{ strtoupper(substr($history->changedBy->name ?? 'U', 0, 2)) }}
                                </span>
                                <span class="text-slate-700 whitespace-nowrap">{{ $history->changedBy->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $history->action_type ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-slate-600 max-w-[200px]">
                            <p class="truncate" title="{{ $history->comment ?? '' }}">{{ $history->comment ?? '—' }}</p>
                        </td>
                        <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap">
                            {{ $history->created_at ? \Carbon\Carbon::parse($history->created_at)->format('d/m/Y H:i A') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div class="flex flex-col items-center justify-center py-10 text-center rounded-xl border border-dashed border-slate-200">
                <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                <p class="text-sm text-slate-400">No hay historial de estados registrado.</p>
            </div>
        @endif
    </section>
    @endif

    {{-- ── Información final / cierre ── --}}
    <section class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-100 p-5" aria-labelledby="closure-heading">
        <h2 id="closure-heading" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-600 text-[11px] font-bold flex items-center justify-center">7</span>
            Información final / cierre
        </h2>

        @php
            $closedStatuses = ['Resuelto', 'Rechazado', 'Cancelado'];
            $isClosed = in_array($ticket->status ?? '', $closedStatuses);
        @endphp

        @if(!$isClosed)
            <div class="flex items-center gap-4 rounded-xl bg-slate-50 border border-slate-200 p-5">
                <div class="flex-shrink-0">
                    {{-- Ilustración simple --}}
                    <svg class="w-16 h-16 text-slate-200" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 64 64" aria-hidden="true">
                        <rect x="8" y="8" width="48" height="48" rx="8" stroke-width="2"/>
                        <path d="M20 32h24M20 22h24M20 42h16" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="48" cy="48" r="10" fill="white" stroke-width="2"/>
                        <path d="M44 48l3 3 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-700 mb-1">Este ticket aún no ha sido resuelto.</p>
                    <p class="text-sm text-slate-500">La información de cierre se mostrará una vez que el ticket sea resuelto.</p>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Fecha de resolución</p>
                    <p class="text-slate-700">
                        {{ $ticket->resolved_at ? \Carbon\Carbon::parse($ticket->resolved_at)->format('d/m/Y H:i A') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Resuelto por</p>
                    <div class="flex items-center gap-1.5">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center">
                            {{ strtoupper(substr($ticket->resolvedBy->name ?? 'R', 0, 2)) }}
                        </span>
                        <span class="text-slate-700">{{ $ticket->resolvedBy->name ?? '—' }}</span>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Tiempo total de atención</p>
                    <p class="text-slate-700">{{ $ticket->total_attention_time ?? '—' }}</p>
                </div>
                @if($ticket->resolution_comment ?? null)
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-xs text-slate-400 font-medium mb-1">Resultado final / comentario</p>
                    <div class="bg-emerald-50 rounded-xl p-3.5 text-sm text-slate-700 border border-emerald-100">
                        {{ $ticket->resolution_comment }}
                    </div>
                </div>
                @endif
                @if($ticket->rejection_reason ?? null)
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-xs text-slate-400 font-medium mb-1">Motivo de rechazo</p>
                    <div class="bg-red-50 rounded-xl p-3.5 text-sm text-slate-700 border border-red-100">
                        {{ $ticket->rejection_reason }}
                    </div>
                </div>
                @endif
                @if($ticket->cancellation_reason ?? null)
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-xs text-slate-400 font-medium mb-1">Motivo de cancelación</p>
                    <div class="bg-amber-50 rounded-xl p-3.5 text-sm text-slate-700 border border-amber-100">
                        {{ $ticket->cancellation_reason }}
                    </div>
                </div>
                @endif
            </div>
        @endif
    </section>

    {{-- ── Botón volver inferior ── --}}
    <div class="mt-6 pb-2">
        <a href="{{ route('tickets.assignments') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver
        </a>
    </div>

</main>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Copiar código del ticket ──────────────────────────────────────
    document.querySelectorAll('[data-copy-ticket]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const value = btn.dataset.copyTicket;
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                const label = btn.querySelector('.copy-label');
                if (label) {
                    label.textContent = 'Copiado';
                    btn.classList.add('text-emerald-600');
                    setTimeout(function () {
                        label.textContent = 'Copiar';
                        btn.classList.remove('text-emerald-600');
                    }, 1500);
                }
            } catch (_) {
                // fallback silencioso
            }
        });
    });

    // ── Confirmar acciones delicadas ─────────────────────────────────
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const message = form.dataset.confirm || '¿Estás seguro de realizar esta acción?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

})();
</script>
@endpush
