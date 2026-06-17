@extends('layouts.app')

@section('content')
@php
    $stateLabels = [
        'open'        => 'Abierto',
        'in_progress' => 'En progreso',
        'in_review'   => 'En revisión',
        'resolved'    => 'Resuelto',
        'rejected'    => 'Rechazado',
        'cancelled'   => 'Cancelado',
    ];

    $priorityLabels = [
        'low'      => 'Baja',
        'medium'   => 'Media',
        'high'     => 'Alta',
        'critical' => 'Crítica',
    ];

    $stateColors = [
        'open'        => 'bg-blue-100 text-blue-700 border-blue-200',
        'in_progress' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
        'in_review'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'resolved'    => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected'    => 'bg-red-100 text-red-700 border-red-200',
        'cancelled'   => 'bg-slate-100 text-slate-600 border-slate-200',
    ];

    $priorityColors = [
        'low'      => 'bg-sky-50 text-sky-600 border-sky-200',
        'medium'   => 'bg-amber-50 text-amber-600 border-amber-200',
        'high'     => 'bg-orange-100 text-orange-700 border-orange-200',
        'critical' => 'bg-red-100 text-red-700 border-red-200',
    ];

    $currentState    = $ticket->state ?? 'open';
    $currentPriority = $ticket->priority ?? 'medium';
    $stateClass      = $stateColors[$currentState]    ?? 'bg-slate-100 text-slate-600 border-slate-200';
    $priorityClass   = $priorityColors[$currentPriority] ?? 'bg-slate-100 text-slate-600 border-slate-200';
    $stateLabel      = $stateLabels[$currentState]    ?? $currentState;
    $priorityLabel   = $priorityLabels[$currentPriority] ?? $currentPriority;
@endphp

<main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- ── Volver ── --}}
    <div class="mb-5">
        <a href="{{ route('tickets.show', $ticket) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a los detalles del ticket
        </a>
    </div>

    {{-- ── Título de página ── --}}
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Editar ticket</h1>
            <p class="mt-1 text-sm text-slate-500">
                Actualiza la información técnica del ticket. Los datos originales no pueden ser modificados.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 mt-1">
            <span class="font-mono text-sm font-semibold text-slate-700 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl">
                #{{ $ticket->id ?? 'INC-0000-0000' }}
            </span>
            <button data-copy-ticket="{{ $ticket->id ?? '' }}"
                    aria-label="Copiar código del ticket"
                    class="copy-btn inline-flex items-center gap-1 text-xs text-blue-500 hover:text-blue-700 px-2 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span class="copy-label">Copiar</span>
            </button>
        </div>
    </div>

    {{-- ── Resumen del ticket ── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
        <h2 class="text-sm font-semibold text-slate-600 mb-4">Resumen del ticket</h2>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-800">{{ $ticket->title ?? 'Sin título' }}</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full border {{ $stateClass }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80"></span>
                    {{ $stateLabel }}
                </span>
                <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full border {{ $priorityClass }}">
                    {{ $priorityLabel }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
            <div class="flex items-start gap-2">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Ubicación</p>
                    <p class="font-medium text-slate-700">{{ optional($ticket->location)->name ?? '—' }}</p>
                    <p class="text-xs text-slate-500">
                        {{ optional($ticket->location)->building ?? '' }}
                        @if(optional($ticket->location)->floor), Piso {{ $ticket->location->floor }}@endif
                        @if(optional($ticket->location)->room_code), {{ $ticket->location->room_code }}@endif
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Reportado por</p>
                    <div class="flex items-center gap-1.5">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold flex items-center justify-center">
                            {{ strtoupper(substr(optional($ticket->reporter)->name ?? 'R', 0, 2)) }}
                        </span>
                        <p class="font-medium text-slate-700">{{ optional($ticket->reporter)->name ?? '—' }}</p>
                    </div>
                    <p class="text-xs text-slate-500 truncate">{{ optional($ticket->reporter)->email ?? '' }}</p>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Fecha de creación</p>
                    <p class="font-medium text-slate-700">
                        {{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i A') : '—' }}
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <div class="mt-0.5 flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wide">Última actualización</p>
                    <p class="font-medium text-slate-700">
                        {{ $ticket->updated_at ? $ticket->updated_at->format('d/m/Y H:i A') : '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Formulario principal ── --}}
    {{-- TODO: el backend debe procesar evidence[] y crear registros en ticket_media. --}}
    {{-- TODO: el backend debe guardar comment en state_history, no en tickets directamente. --}}
    <form id="edit-ticket-form"
          method="POST"
          action="{{ route('tickets.update-state', $ticket) }}"
          enctype="multipart/form-data"
          novalidate>
        @csrf
        @method('PATCH')

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">

            {{-- ── Columna izquierda ── --}}
            <div class="flex flex-col gap-6">

                {{-- 1. Información original — solo lectura --}}
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5"
                         aria-labelledby="original-info-heading">
                    <h2 id="original-info-heading" class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-1.5">
                        <span class="text-slate-800">1. Información original del ticket</span>
                        <span class="text-xs font-normal text-blue-500">(solo lectura)</span>
                    </h2>

                    <div class="space-y-4 text-sm">
                        {{-- Descripción --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 mb-1.5">Descripción original</p>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-700 leading-relaxed min-h-[64px]">
                                {{ $ticket->description ?? 'Sin descripción registrada.' }}
                            </div>
                        </div>

                        {{-- Categoría --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 mb-1">Categoría</p>
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                                </svg>
                                {{ optional($ticket->category)->name ?? '—' }}
                            </div>
                        </div>

                        {{-- Ubicación --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 mb-1">Ubicación</p>
                            <p class="text-slate-700">{{ optional($ticket->location)->name ?? '—' }}</p>
                        </div>

                        {{-- Grid ubicación detallada --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-slate-400 font-medium mb-0.5">Edificio</p>
                                <p class="text-slate-700">{{ optional($ticket->location)->building ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-medium mb-0.5">Piso</p>
                                <p class="text-slate-700">
                                    @if(optional($ticket->location)->floor)Piso {{ $ticket->location->floor }}@else —@endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-medium mb-0.5">Aula / Laboratorio</p>
                                <p class="text-slate-700">{{ optional($ticket->location)->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-medium mb-0.5">Código de ubicación</p>
                                <p class="text-slate-700 font-mono text-xs">{{ optional($ticket->location)->room_code ?? '—' }}</p>
                            </div>
                        </div>

                        {{-- Evidencias del reporter --}}
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-xs font-medium text-slate-500">Evidencias del reporter</p>
                                @if($reporterEvidence ?? null)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold">
                                    {{ $reporterEvidence->count() }}
                                </span>
                                @endif
                            </div>

                            @if(($reporterEvidence ?? null) && $reporterEvidence->count() > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($reporterEvidence as $ev)
                                <div class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs">
                                    @if(in_array(strtolower(pathinfo($ev->file_url ?? '', PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']))
                                        <svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                    @else
                                        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    @endif
                                    <span class="text-slate-700 font-medium truncate max-w-[120px]">
                                        {{ basename($ev->file_url ?? 'archivo') }}
                                    </span>
                                    <span class="text-slate-400">{{ strtoupper($ev->file_type ?? '') }}</span>
                                    {{-- TODO: conectar con ruta de visualización de evidencia --}}
                                    <a href="{{ $ev->file_url ?? '#' }}" target="_blank" rel="noopener"
                                       aria-label="Ver evidencia {{ basename($ev->file_url ?? '') }}"
                                       class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <p class="text-xs text-slate-400 italic">Sin evidencias del reporter.</p>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- 3. Actualización técnica — editable --}}
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5"
                         aria-labelledby="tech-update-heading">
                    <h2 id="tech-update-heading" class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-1.5">
                        <span class="text-slate-800">3. Actualización técnica</span>
                        <span class="text-xs font-normal text-blue-500">(editable)</span>
                    </h2>

                    <div class="space-y-5">

                        {{-- Nuevo estado --}}
                        @if($canChangeState ?? false)
                        <div>
                            <label for="state" class="block text-xs font-medium text-slate-600 mb-1.5">
                                Nuevo estado <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <div class="relative">
                                <select id="state" name="state"
                                        class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 py-2.5 pr-9 text-sm text-slate-700 shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 transition"
                                        aria-required="true">
                                    @foreach($stateLabels as $val => $label)
                                        @if($val !== 'cancelled')
                                        <option value="{{ $val }}"
                                            {{ old('state', $currentState) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endif
                                    @endforeach
                                </select>
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                </span>
                            </div>
                            @error('state')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

                        {{-- Comentario técnico --}}
                        <div>
                            <label for="comment" class="block text-xs font-medium text-slate-600 mb-1.5">
                                Comentario técnico <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            {{-- Este comentario se persistirá en state_history.comment --}}
                            <textarea id="comment" name="comment"
                                      rows="5"
                                      maxlength="1000"
                                      placeholder="Describe el trabajo realizado, hallazgos o próximos pasos..."
                                      aria-required="true"
                                      aria-describedby="comment-counter comment-hint"
                                      class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm resize-y focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 transition placeholder-slate-300">{{ old('comment', '') }}</textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <p id="comment-counter" class="text-xs text-slate-400 comment-counter" data-target="comment" data-max="1000">
                                    0/1000 caracteres
                                </p>
                            </div>
                            @error('comment')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Adjuntar evidencias técnicas --}}
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1.5">
                                Adjuntar evidencias técnicas
                            </label>
                            <div id="dropzone"
                                 class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-blue-200 bg-blue-50/40 px-6 py-8 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                                 role="button"
                                 tabindex="0"
                                 aria-label="Zona de carga de archivos">
                                <svg class="w-8 h-8 text-blue-400 mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <p class="text-sm text-blue-600 font-medium">Arrastra archivos aquí o haz clic para seleccionar</p>
                                <p class="text-xs text-slate-400">Formatos permitidos: JPG, PNG, PDF, DOC, TXT. Máx. 10MB por archivo.</p>
                                <input id="evidence-input" type="file" name="evidence[]" multiple
                                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       aria-label="Seleccionar archivos de evidencia">
                            </div>
                            @error('evidence.*')
                                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Lista de archivos adjuntos seleccionados --}}
                        <div id="selected-files-container" class="hidden">
                            <p class="text-xs font-medium text-slate-600 mb-2">Archivos adjuntos</p>
                            <ul id="selected-files-list" class="space-y-2" aria-live="polite" aria-label="Archivos seleccionados"></ul>
                        </div>

                        {{-- Evidencias de maintenance ya subidas --}}
                        @if(($maintenanceEvidence ?? null) && $maintenanceEvidence->count() > 0)
                        <div>
                            <p class="text-xs font-medium text-slate-600 mb-2">Evidencias técnicas subidas anteriormente</p>
                            <div class="space-y-1.5">
                                @foreach($maintenanceEvidence as $ev)
                                <div class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs">
                                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span class="text-slate-700 font-medium flex-1 truncate">{{ basename($ev->file_url ?? 'archivo') }}</span>
                                    <span class="text-slate-400">{{ strtoupper($ev->file_type ?? '') }}</span>
                                    <span class="text-slate-400">
                                        {{ $ev->created_at ? \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y') : '' }}
                                    </span>
                                    {{-- TODO: conectar con ruta de visualización --}}
                                    <a href="{{ $ev->file_url ?? '#' }}" target="_blank" rel="noopener"
                                       aria-label="Ver archivo"
                                       class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Comentario asociado opcional --}}
                        {{-- Este comentario debe persistirse en state_history.comment, no en ticket_media, porque ticket_media no tiene columna comment. --}}
                        <div>
                            <label for="associated_comment" class="block text-xs font-medium text-slate-600 mb-1.5">
                                Comentario asociado <span class="text-slate-400 font-normal">(opcional)</span>
                            </label>
                            <textarea id="associated_comment" name="associated_comment"
                                      rows="3"
                                      maxlength="500"
                                      placeholder="Comentario adicional sobre las evidencias adjuntas..."
                                      aria-describedby="assoc-counter"
                                      class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm resize-y focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 transition placeholder-slate-300">{{ old('associated_comment', '') }}</textarea>
                            <p id="assoc-counter" class="mt-1 text-xs text-slate-400 comment-counter" data-target="associated_comment" data-max="500">
                                0/500 caracteres
                            </p>
                        </div>

                    </div>
                </section>

                {{-- Información importante --}}
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
                    <div class="flex items-start gap-2 mb-2">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                        </svg>
                        <p class="text-xs font-semibold text-amber-700">Información importante</p>
                    </div>
                    <ul class="space-y-1 pl-6" role="list">
                        <li class="text-xs text-amber-700 list-disc">Los datos originales del ticket no pueden ser modificados.</li>
                        <li class="text-xs text-amber-700 list-disc">Solo puedes actualizar el estado, agregar comentarios y subir evidencias.</li>
                        <li class="text-xs text-amber-700 list-disc">Asegúrate de guardar tus cambios antes de salir.</li>
                    </ul>
                </div>

            </div>{{-- fin columna izquierda --}}

            {{-- ── Columna derecha ── --}}
            <div class="flex flex-col gap-6">

                {{-- 2. Información de asignación — solo lectura --}}
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5"
                         aria-labelledby="assignment-heading">
                    <h2 id="assignment-heading" class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-1.5">
                        <span class="text-slate-800">2. Información de asignación</span>
                        <span class="text-xs font-normal text-blue-500">(solo lectura)</span>
                    </h2>

                    @if($assignment ?? null)
                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-1.5">Asignado a</p>
                            <div class="flex items-center gap-2.5">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                    {{ strtoupper(substr(optional($assignment->technician)->name ?? 'T', 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800 truncate">{{ optional($assignment->technician)->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ optional($assignment->technician)->email ?? '' }}</p>
                                </div>
                                @auth
                                    @if(auth()->id() === ($ticket->assigned_to ?? null))
                                    <span class="flex-shrink-0 inline-flex items-center text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        Técnico actual
                                    </span>
                                    @endif
                                @endauth
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-1.5">Asignado por</p>
                            <div class="flex items-center gap-2.5">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center">
                                    {{ strtoupper(substr(optional($assignment->assignedBy)->name ?? 'A', 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800 truncate">{{ optional($assignment->assignedBy)->name ?? '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-0.5">Fecha de asignación</p>
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                {{ $ticket->assigned_at ? \Carbon\Carbon::parse($ticket->assigned_at)->format('d/m/Y H:i A') : '—' }}
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-0.5">Tipo de asignación</p>
                            <p class="text-slate-700">{{ $ticket->assignment_source ?? 'Manual' }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-1">Estado de bloqueo</p>
                            @php $locked = (bool)($ticket->assignment_locked ?? false); @endphp
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full border
                                {{ $locked ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                {{ $locked ? 'Bloqueado' : 'Desbloqueado' }}
                            </span>
                            <p class="text-xs text-slate-500 mt-1">{{ $locked ? 'No puede ser reasignado' : 'Puede ser reasignado' }}</p>
                        </div>
                    </div>
                    @else
                        <p class="text-sm text-slate-500">No hay información de asignación disponible.</p>
                    @endif
                </section>

                {{-- 4. Asignación (informativo) --}}
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5"
                         aria-labelledby="assignment-summary-heading">
                    <h2 id="assignment-summary-heading" class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-1.5">
                        <span>4. Asignación</span>
                        <span class="text-xs font-normal text-slate-400">(informativo)</span>
                    </h2>

                    <div class="space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-slate-500">Asignado a</span>
                            <span class="text-right">{{ optional($assignment->technician ?? null)->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-slate-500">Asignado por</span>
                            <span class="text-right">{{ optional($assignment->assignedBy ?? null)->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-slate-500">Fecha de asignación</span>
                            <span class="text-right">
                                {{ $ticket->assigned_at ? \Carbon\Carbon::parse($ticket->assigned_at)->format('d/m/Y H:i A') : '—' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-slate-500">Tipo de asignación</span>
                            <span class="text-right">{{ $ticket->assignment_source ?? 'Manual' }}</span>
                        </div>
                        <div class="flex justify-between gap-2 items-center">
                            <span class="font-medium text-slate-500">Estado de bloqueo</span>
                            @php $locked2 = (bool)($ticket->assignment_locked ?? false); @endphp
                            <span class="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full border
                                {{ $locked2 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                {{ $locked2 ? 'Bloqueado' : 'Desbloqueado' }}
                            </span>
                        </div>
                        <p class="text-slate-400 pt-1">{{ $locked2 ? 'No puede ser reasignado' : 'Puede ser reasignado' }}</p>
                    </div>
                </section>

                {{-- 5. Historial reciente --}}
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5"
                         aria-labelledby="recent-history-heading">
                    <h2 id="recent-history-heading" class="text-sm font-semibold text-slate-700 mb-3">
                        5. Historial reciente
                    </h2>

                    @php
                        $historyItems = ($recentHistory ?? $stateHistory ?? collect())->take(5);
                        $actionMap = [
                            'open'        => 'Creación',
                            'in_progress' => 'Inicio de atención',
                            'in_review'   => 'Liberación',
                            'resolved'    => 'Resolución',
                            'rejected'    => 'Rechazo',
                            'cancelled'   => 'Cancelación',
                        ];
                    @endphp

                    @if($historyItems->count() > 0)
                    <div class="space-y-3">
                        @foreach($historyItems as $h)
                        @php
                            $toState = $h->to_state ?? $h->new_status ?? '';
                            $hStateClass = $stateColors[$toState] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                            $hLabel     = $stateLabels[$toState] ?? $toState;
                            $actionText = $actionMap[$toState] ?? 'Cambio de estado';
                            $dotColor   = match($toState) {
                                'in_progress' => 'bg-blue-500',
                                'in_review'   => 'bg-amber-400',
                                'resolved'    => 'bg-emerald-500',
                                'rejected'    => 'bg-red-500',
                                default       => 'bg-slate-300',
                            };
                        @endphp
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 mt-1.5">
                                <span class="block w-2 h-2 rounded-full {{ $dotColor }}"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full border {{ $hStateClass }}">
                                            {{ $hLabel }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ $actionText }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 flex-shrink-0">
                                        {{ $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i A') : '' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    {{ optional($h->changedBy)->name ?? optional($h->user)->name ?? '—' }}
                                </p>
                                @if($h->comment ?? null)
                                <p class="text-xs text-slate-500 mt-0.5 truncate" title="{{ $h->comment }}">
                                    {{ $h->comment }}
                                </p>
                                @endif
                            </div>
                        </div>
                        @if(!$loop->last)<div class="ml-4 border-l border-slate-100 h-2"></div>@endif
                        @endforeach
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        {{-- TODO: conectar con route('tickets.show', $ticket).'#history' --}}
                        <a href="{{ route('tickets.show', $ticket) }}#history"
                           class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                            </svg>
                            Ver historial completo
                        </a>
                    </div>
                    @else
                        <p class="text-xs text-slate-400 py-4 text-center">Sin historial registrado.</p>
                    @endif
                </section>

            </div>{{-- fin columna derecha --}}

        </div>{{-- fin grid --}}

        {{-- ── Barra inferior de acciones ── --}}
        <div class="mt-8 sticky bottom-0 z-10 bg-white border-t border-slate-200 shadow-[0_-2px_12px_0_rgba(0,0,0,0.06)] -mx-4 px-4 py-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="mx-auto max-w-7xl flex flex-wrap items-center justify-between gap-3">

                {{-- Volver --}}
                <a href="{{ route('tickets.show', $ticket) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                </a>

                <div class="flex flex-wrap gap-2">
                    {{-- Guardar avance --}}
                    @if($canSaveProgress ?? true)
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Guardar avance
                    </button>
                    @endif

                    {{-- Liberar ticket --}}
                    @if($canRelease ?? false)
                    {{-- TODO: conectar con route('tickets.release', $ticket) --}}
                    <form method="POST" action="#" class="inline" data-confirm="¿Estás seguro de liberar este ticket? Se perderá la asignación actual.">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Liberar ticket
                        </button>
                    </form>
                    @endif

                    {{-- Resolver ticket --}}
                    @if($canResolve ?? false)
                    {{-- TODO: conectar con route('tickets.update-state', $ticket) para estado resolved --}}
                    <form method="POST" action="#" class="inline" data-confirm="¿Confirmas la resolución de este ticket? Esta acción marcará el ticket como resuelto.">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="state" value="resolved">
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Resolver ticket
                        </button>
                    </form>
                    @endif
                </div>

            </div>
        </div>

    </form>

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
                var label = btn.querySelector('.copy-label');
                if (label) {
                    label.textContent = 'Copiado';
                    btn.classList.add('text-emerald-600', 'border-emerald-300');
                    setTimeout(function () {
                        label.textContent = 'Copiar';
                        btn.classList.remove('text-emerald-600', 'border-emerald-300');
                    }, 1500);
                }
            } catch (e) { /* silencioso */ }
        });
    });

    // ── Contador de caracteres ───────────────────────────────────────
    document.querySelectorAll('.comment-counter').forEach(function (counter) {
        var targetId = counter.dataset.target;
        var max = parseInt(counter.dataset.max, 10);
        var textarea = document.getElementById(targetId);
        if (!textarea) return;

        function update() {
            var len = textarea.value.length;
            counter.textContent = len + '/' + max + ' caracteres';
            counter.classList.toggle('text-red-500', len >= max);
        }
        textarea.addEventListener('input', update);
        update();
    });

    // ── Dropzone / input file con lista visual ───────────────────────
    var input     = document.getElementById('evidence-input');
    var container = document.getElementById('selected-files-container');
    var list      = document.getElementById('selected-files-list');
    var dropzone  = document.getElementById('dropzone');
    var dt        = new DataTransfer();

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function getIcon(name) {
        var ext = name.split('.').pop().toLowerCase();
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            return '<svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
        }
        return '<svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    }

    function renderFiles() {
        list.innerHTML = '';
        if (dt.files.length === 0) {
            container.classList.add('hidden');
            return;
        }
        container.classList.remove('hidden');
        Array.from(dt.files).forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs';
            li.innerHTML =
                getIcon(file.name) +
                '<span class="flex-1 min-w-0"><span class="font-medium text-slate-700 truncate block">' + file.name + '</span>' +
                '<span class="text-slate-400">' + file.type + ' · ' + formatSize(file.size) + '</span></span>' +
                '<button type="button" data-idx="' + idx + '" class="remove-file flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors" aria-label="Quitar ' + file.name + '">' +
                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>' +
                '</button>';
            list.appendChild(li);
        });

        // Quitar archivo
        list.querySelectorAll('.remove-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.dataset.idx, 10);
                var newDt = new DataTransfer();
                Array.from(dt.files).forEach(function (f, i) {
                    if (i !== idx) newDt.items.add(f);
                });
                dt = newDt;
                input.files = dt.files;
                renderFiles();
            });
        });
    }

    if (input) {
        input.addEventListener('change', function () {
            Array.from(input.files).forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            renderFiles();
        });
    }

    // Drag & drop visual
    if (dropzone) {
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('border-blue-400', 'bg-blue-50');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('border-blue-400', 'bg-blue-50');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('border-blue-400', 'bg-blue-50');
            if (e.dataTransfer && e.dataTransfer.files.length) {
                Array.from(e.dataTransfer.files).forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
                renderFiles();
            }
        });
        dropzone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input && input.click();
            }
        });
    }

    // ── Confirmar acciones delicadas ─────────────────────────────────
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.dataset.confirm || '¿Estás seguro de realizar esta acción?';
            if (!window.confirm(msg)) { e.preventDefault(); }
        });
    });

})();
</script>
@endpush
