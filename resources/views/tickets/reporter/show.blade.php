@extends('layouts.app')

@section('title', 'Seguimiento · '.$tracking->ticket['ref'])

@section('content')
@php
    /** @var \App\ViewModels\Tickets\ReporterTicketTrackingViewModel $tracking */
    $ticket = $tracking->ticket;
    $steps = $tracking->steps;
    $timeline = $tracking->timeline;
    $details = $tracking->details;
    $evidence = $tracking->evidence;

    $stateCaption = ['done' => 'Completado', 'current' => 'En curso', 'todo' => 'Pendiente', 'rejected' => 'Rechazado', 'cancelled' => 'Cancelado'];
@endphp

<div class="rep-show">

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    {{-- ── Back link ─────────────────────────────────────────────── --}}
    <a href="{{ route('reporter.tickets.index') }}" class="rep-show__back">
        <x-lucide-arrow-left width="16" height="16" stroke-width="2.5" />
        Volver a mis tickets
    </a>

    {{-- ── 1. TICKET HEADER CARD ─────────────────────────────────── --}}
    <header class="rep-show-head rep-tone-{{ $ticket['status_tone'] }}">
        <span class="rep-show-head__rail" aria-hidden="true"></span>

        <div class="rep-show-head__icon">
            <x-dynamic-component :component="'lucide-' . $ticket['icon']" width="24" height="24" stroke-width="2" />
        </div>

        <div class="rep-show-head__main">
            <div class="rep-show-head__top">
                <h1 class="rep-show-head__title">{{ $ticket['title'] }}</h1>
                <span class="rep-show-head__id">{{ $ticket['ref'] }}</span>
                <span class="rep-badge rep-status--{{ $ticket['status'] }}">
                    <span class="rep-badge__dot" aria-hidden="true"></span>
                    {{ $ticket['status_label'] }}
                </span>
            </div>
            <div class="rep-show-head__meta">
                <x-lucide-map-pin width="14" height="14" stroke-width="2" />
                <span>{{ $ticket['location'] }}</span>
                <span class="rep-show-head__sep" aria-hidden="true"></span>
                <span>{{ $ticket['category'] }}</span>
                @if ($ticket['type'] !== '')
                    <span class="rep-show-head__sep" aria-hidden="true"></span>
                    <span>{{ $ticket['type'] }}</span>
                @endif
            </div>
        </div>

        <div class="rep-show-head__aside">
            <dl class="rep-show-head__stats">
                <div class="rep-show-stat">
                    <dt>Prioridad</dt>
                    <dd>
                        <span class="rep-badge rep-prio rep-tone-{{ $ticket['priority'] }}">
                            <span class="rep-badge__dot" aria-hidden="true"></span>
                            {{ $ticket['priority_label'] }}
                        </span>
                    </dd>
                </div>
                <div class="rep-show-stat">
                    <dt>Última actualización</dt>
                    <dd>{{ $ticket['updated'] }}</dd>
                </div>
            </dl>
            <a href="{{ route('reporter.tickets.show', $ticket['id']) }}" class="rep-btn-outline">
                <x-lucide-refresh-cw width="16" height="16" stroke-width="2.5" />
                Actualizar
            </a>
        </div>
    </header>

    {{-- ── 2. PROGRESS STEPPER (real milestones) ─────────────────── --}}
    <section class="rep-progress" aria-label="Progreso del ticket">
        <ol class="rep-stepper">
            @foreach ($steps as $step)
                <li class="rep-step is-{{ $step['state'] }} rep-tone-{{ $step['tone'] }}"
                    @if ($step['state'] === 'current') aria-current="step" @endif>
                    <span class="rep-step__node" aria-hidden="true">
                        @if ($step['state'] === 'done')
                            <x-lucide-check width="16" height="16" stroke-width="3" />
                        @elseif ($step['state'] === 'rejected')
                            <x-lucide-x width="16" height="16" stroke-width="3" />
                        @else
                            <x-dynamic-component :component="'lucide-' . $step['icon']" width="15" height="15" stroke-width="2" />
                        @endif
                    </span>
                    <span class="rep-step__text">
                        <span class="rep-step__label">{{ $step['label'] }}</span>
                        <span class="rep-step__state">{{ $stateCaption[$step['state']] ?? '' }}</span>
                        <span class="rep-step__at">{{ $step['at'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ── 3. DUPLICATE NOTICE (reporter-safe, shown only when flagged) ── --}}
    @include('tickets.reporter.partials.duplicate-notice', ['duplicate' => $tracking->duplicate])

    {{-- ── 3b. PRECHECK NOTICE (reporter confirmed "caso distinto") ── --}}
    @include('tickets.reporter.partials.precheck-notice', ['precheckNotice' => $tracking->precheckNotice])

    {{-- ── 4. CONTENT LAYOUT: description + timeline + detail rail ── --}}
    <div class="rep-show-layout">

        {{-- LEFT --}}
        <main class="rep-show-main">

            {{-- Description --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-file-text width="16" height="16" stroke-width="2" />
                    Descripción
                </h2>
                @if ($ticket['description'] !== '')
                    <p class="rep-desc">{{ $ticket['description'] }}</p>
                @else
                    <p class="rep-desc rep-desc--muted">No se agregó una descripción a este reporte.</p>
                @endif
            </section>

            {{-- Timeline (state_history) --}}
            <section class="rep-panel" id="rep-timeline">
                <h2 class="rep-panel__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Cronología del ticket
                </h2>

                <div class="rep-timeline">
                    @foreach ($timeline as $event)
                        <div class="rep-event rep-tone-{{ $event['tone'] }} {{ $event['highlight'] ? 'is-highlight' : '' }}">
                            <div class="rep-event__rail">
                                <span class="rep-event__dot">
                                    <x-dynamic-component :component="'lucide-' . $event['icon']" width="15" height="15" stroke-width="2" />
                                </span>
                                <span class="rep-event__line" aria-hidden="true"></span>
                            </div>
                            <div class="rep-event__body">
                                <div class="rep-event__head">
                                    <span class="rep-event__title">{{ $event['title'] }}</span>
                                    <span class="rep-event__at">{{ $event['at'] }}</span>
                                </div>
                                @if (! empty($event['note']))
                                    <p class="rep-event__note">{{ $event['note'] }}</p>
                                @endif
                                @if (! empty($event['actor']))
                                    <span class="rep-event__actor">
                                        <x-lucide-user width="12" height="12" stroke-width="2" />
                                        {{ $event['actor'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Comment box — visual placeholder (no reporter comment flow yet). --}}
            <section class="rep-panel rep-comment">
                <h2 class="rep-panel__title">
                    <x-lucide-message-square-plus width="16" height="16" stroke-width="2" />
                    Agregar un comentario
                </h2>
                <label for="rep-comment" class="sr-only">Escribe un comentario para el técnico</label>
                <textarea id="rep-comment" class="rep-comment__input" rows="3"
                          placeholder="Añade más contexto, detalles o aclaraciones para el técnico..."
                          aria-describedby="rep-comment-hint" disabled></textarea>
                <div class="rep-comment__foot">
                    <span id="rep-comment-hint" class="rep-comment__hint">
                        <x-lucide-info width="13" height="13" stroke-width="2" />
                        Disponible próximamente
                    </span>
                    <button type="button" class="rep-btn-primary" title="Disponible próximamente" disabled>
                        <x-lucide-send width="15" height="15" stroke-width="2.5" />
                        Enviar comentario
                    </button>
                </div>
            </section>
        </main>

        {{-- RIGHT: detail rail --}}
        <aside class="rep-show-rail" aria-label="Detalles y acciones del ticket">

            {{-- A. Ticket details --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-layers width="16" height="16" stroke-width="2" />
                    Detalles del ticket
                </h2>

                <dl class="rep-detail">
                    <div class="rep-detail__row">
                        <dt><x-lucide-circle-dot width="14" height="14" stroke-width="2" /> Estado</dt>
                        <dd>
                            <span class="rep-badge rep-status--{{ $ticket['status'] }}">
                                <span class="rep-badge__dot" aria-hidden="true"></span>
                                {{ $ticket['status_label'] }}
                            </span>
                        </dd>
                    </div>
                    @foreach ($details['rows'] as $row)
                        <div class="rep-detail__row">
                            <dt><x-dynamic-component :component="'lucide-' . $row['icon']" width="14" height="14" stroke-width="2" /> {{ $row['label'] }}</dt>
                            <dd>{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="rep-tech">
                    <span class="rep-tech__label">Técnico asignado</span>
                    @if ($details['technician'])
                        <div class="rep-tech__card">
                            <x-avatar :initials="$details['technician']['initials']" base-class="rep-tech__avatar" tone-class="" aria-hidden="true" />
                            <div>
                                <span class="rep-tech__name">{{ $details['technician']['name'] }}</span>
                                <span class="rep-tech__role">{{ $details['technician']['role'] }}</span>
                            </div>
                        </div>
                    @else
                        <div class="rep-tech__card rep-tech__card--empty">
                            <span class="rep-tech__avatar rep-tech__avatar--empty" aria-hidden="true">
                                <x-lucide-user width="16" height="16" stroke-width="2" />
                            </span>
                            <span class="rep-tech__name rep-tech__name--muted">Sin asignar todavía</span>
                        </div>
                    @endif
                </div>
            </section>

            {{-- B. Evidence (real media) --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-image width="16" height="16" stroke-width="2" />
                    Evidencias
                    <span class="rep-panel__count">{{ $evidence['count'] }}</span>
                </h2>

                @if (! $tracking->hasEvidence())
                    <div class="rep-evidence-empty">
                        <x-lucide-image width="26" height="26" stroke-width="1.5" />
                        <p class="rep-empty__note">Aún no hay evidencias adjuntas.</p>
                    </div>
                @else
                    <div class="rep-evidence">
                        @foreach ($evidence['items'] as $item)
                            @if ($item['is_image'] && $item['url'])
                                <a class="rep-evidence__tile rep-evidence__tile--img" href="{{ $item['url'] }}"
                                   target="_blank" rel="noopener" title="{{ $item['label'] }}"
                                   style="background-image: url('{{ $item['url'] }}');">
                                    <span class="sr-only">{{ $item['label'] }}</span>
                                </a>
                            @elseif ($item['url'])
                                <a class="rep-evidence__tile" href="{{ $item['url'] }}" target="_blank" rel="noopener" title="{{ $item['label'] }}">
                                    <x-lucide-paperclip width="20" height="20" stroke-width="1.75" />
                                    <figcaption class="rep-evidence__caption">{{ $item['label'] }}</figcaption>
                                </a>
                            @else
                                <figure class="rep-evidence__tile" title="{{ $item['label'] }}">
                                    <x-lucide-image width="20" height="20" stroke-width="1.75" />
                                    <figcaption class="rep-evidence__caption">{{ $item['label'] }}</figcaption>
                                </figure>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- C. Actions --}}
            <section class="rep-panel rep-actions">
                <div class="rep-actions__icon rep-tone-purple">
                    <x-lucide-message-circle width="20" height="20" stroke-width="2" />
                </div>
                <div class="rep-actions__body">
                    <h2 class="rep-panel__title rep-actions__title">¿Necesitas agregar algo?</h2>
                    <p class="rep-actions__text">
                        Abre la ficha completa del ticket para ver todo el detalle.
                    </p>
                    <a href="{{ route('tickets.show', $ticket['id']) }}" class="rep-btn-primary rep-actions__cta">
                        <x-lucide-arrow-right width="16" height="16" stroke-width="2.5" />
                        Ver ficha completa
                    </a>
                </div>
            </section>

        </aside>
    </div>

    {{-- ── 4. COMENTARIOS INTERNOS ────────────────────────────────── --}}
    @can('viewComments', $ticketForComments)
        @include('tickets.partials.comments.core-comments', [
            'ticket'        => $ticketForComments,
            'coreComments'  => $coreComments,
            'currentUserId' => (string) auth()->id(),
        ])
    @endcan

    {{-- ── 5. NOTICE BANNER ──────────────────────────────────────── --}}
    <div class="rep-notice" role="note">
        <x-lucide-bell width="17" height="17" stroke-width="2" />
        <span>{{ $tracking->notice }}</span>
    </div>
</div>
@endsection
