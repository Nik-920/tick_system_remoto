{{-- ⑨ Visibilidad en Comunidad — solo visible para admin/super_admin --}}
@can('moderateCommunityVisibility', $ticket)
<section class="mt-6 ticket-show__card ticket-show__card--sectioned comm-visibility-card" aria-labelledby="community-visibility-heading" id="community-visibility">
    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">9</span>
        <h2 id="community-visibility-heading" class="ticket-show__section-head-title">Visibilidad en Comunidad</h2>

        <div class="ml-auto flex items-center">
            @if ($vm->isCommunityVisible())
                <span class="comm-visibility-card__status comm-visibility-card__status--visible">
                    <span class="comm-visibility-card__status-dot" aria-hidden="true"></span>
                    Visible en Comunidad
                </span>
            @else
                <span class="comm-visibility-card__status comm-visibility-card__status--hidden">
                    <span class="comm-visibility-card__status-dot" aria-hidden="true"></span>
                    Oculto en Comunidad
                </span>
            @endif
        </div>
    </div>

    <div class="ticket-show__card-body">
    {{-- Estado actual --}}
    @if ($vm->isCommunityVisible())
        <p class="comm-visibility-card__hint">
            Este ticket aparece en el feed de Comunidad si su estado lo permite.
        </p>
    @else
        @if ($vm->communityVisibilityReason())
            <div class="comm-visibility-card__meta">
                <div class="comm-visibility-card__meta-row">
                    <span class="comm-visibility-card__meta-label">Motivo</span>
                    <span class="comm-visibility-card__meta-value">{{ $vm->communityVisibilityReason() }}</span>
                </div>
                @if ($vm->communityHiddenAtLabel())
                    <div class="comm-visibility-card__meta-row">
                        <span class="comm-visibility-card__meta-label">Ocultado el</span>
                        <span class="comm-visibility-card__meta-value">{{ $vm->communityHiddenAtLabel() }}</span>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- Formulario: ocultar (solo si actualmente visible) --}}
    @if ($vm->isCommunityVisible())
        <form method="POST"
              action="{{ route('tickets.community.hide', $ticket) }}"
              class="comm-visibility-card__form tickets-once-form"
              data-confirm="¿Ocultar este ticket del feed de Comunidad?">
            @csrf
            @method('PATCH')

            <label for="community_hide_reason" class="comm-visibility-card__label">
                Motivo de ocultamiento <span aria-hidden="true" class="comm-visibility-card__required">*</span>
            </label>
            <textarea id="community_hide_reason"
                      name="reason"
                      rows="2"
                      maxlength="255"
                      class="comm-visibility-card__textarea{{ $errors->has('reason') ? ' comm-visibility-card__textarea--error' : '' }}"
                      placeholder="Ej.: Información sensible, reporte duplicado, evidencia no apta...">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="comm-visibility-card__error" role="alert">{{ $message }}</p>
            @enderror
            <p class="comm-visibility-card__hint-text">Máximo 255 caracteres. Este motivo queda registrado en el historial de moderación.</p>

            <div class="comm-visibility-card__actions">
                <button type="submit" class="comm-visibility-card__button comm-visibility-card__button--hide">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-4.477-10-10 0-1.274.24-2.494.675-3.615M6.343 6.343A8 8 0 0120 12c0 1.274-.24 2.494-.675 3.615M3 3l18 18"/>
                    </svg>
                    Ocultar de Comunidad
                </button>
            </div>
        </form>
    @else
        {{-- Formulario: restaurar (solo si actualmente oculto) --}}
        <form method="POST"
              action="{{ route('tickets.community.restore', $ticket) }}"
              class="comm-visibility-card__form tickets-once-form">
            @csrf
            @method('PATCH')

            <div class="comm-visibility-card__actions">
                <button type="submit" class="comm-visibility-card__button comm-visibility-card__button--restore">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Restaurar en Comunidad
                </button>
            </div>
        </form>
    @endif

    {{-- Historial de moderación (últimas 5 acciones) --}}
    <div class="comm-visibility-card__history">
        <h3 class="comm-visibility-card__history-title">Historial de moderación</h3>
        @if ($vm->hasCommunityModerationLogs())
            <ul class="comm-visibility-card__history-list">
                @foreach ($vm->communityModerationLogs() as $log)
                    <li class="comm-visibility-card__history-item comm-visibility-card__history-item--{{ $log->action }}">
                        <span class="comm-visibility-card__history-dot" aria-hidden="true"></span>
                        <div class="comm-visibility-card__history-content">
                            <p class="comm-visibility-card__history-action">
                                {{ $vm->communityLogActionLabel($log->action) }}
                                <span class="comm-visibility-card__history-meta">
                                    @if ($log->performedBy)
                                        por {{ $log->performedBy->name }}
                                    @endif
                                    &middot; {{ $vm->fmtDate($log->created_at) }}
                                </span>
                            </p>
                            @if ($log->reason)
                                <p class="comm-visibility-card__history-reason">{{ $log->reason }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="comm-visibility-card__history-empty">Sin historial de moderación comunitaria.</p>
        @endif
    </div>

    {{-- Historial de ediciones de comentarios (últimas 5, append-only audit) --}}
    <div class="comm-visibility-card__history">
        <h3 class="comm-visibility-card__history-title">Historial de ediciones de comentarios</h3>
        @if ($vm->hasCommunityCommentEditLogs())
            <ul class="comm-visibility-card__history-list">
                @foreach ($vm->communityCommentEditLogs() as $editLog)
                    <li class="comm-visibility-card__history-item">
                        <span class="comm-visibility-card__history-dot" aria-hidden="true"></span>
                        <div class="comm-visibility-card__history-content">
                            <p class="comm-visibility-card__history-action">
                                Comentario editado
                                <span class="comm-visibility-card__history-meta">
                                    @if ($editLog->editedBy)
                                        por {{ $editLog->editedBy->name }}
                                    @endif
                                    &middot; {{ $vm->fmtDate($editLog->created_at) }}
                                </span>
                            </p>
                            <p class="comm-visibility-card__history-reason">
                                <span class="comm-visibility-card__meta-label">Antes:</span>
                                {{ $editLog->previous_body }}
                            </p>
                            <p class="comm-visibility-card__history-reason">
                                <span class="comm-visibility-card__meta-label">Después:</span>
                                {{ $editLog->new_body }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="comm-visibility-card__history-empty">Sin ediciones de comentarios registradas.</p>
        @endif
    </div>
    </div>
</section>
@endcan
