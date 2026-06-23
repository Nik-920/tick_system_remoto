{{-- ── REAL POST CARD (Community Report Card v2) ─────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     No user data (reporter/assignee) is present in $post by design.

     Layout: feed-style card with a meta topbar, a 2-column body
     (content left / evidence right on desktop), a coloured action bar,
     a collapsible comments section and an informational footer.

     Social actions: Me interesa / También me pasa / Lo vi / Guardar.
     Progressive enhancement: every action is a POST/DELETE form that
     works without JS; community-social-actions.js upgrades them to JSON.
     Media is served exclusively through the thumbnail proxy route — the
     raw file_url is never rendered.
──────────────────────────────────────────────────────────── --}}
@php
    $carImgs  = array_values(array_filter($post['media_images'] ?? [], fn ($u) => $u !== ''));
    $carCount = count($carImgs);

    // Deterministic pastel palette per category (same color for the same category).
    $commCatName     = $post['category']['name'] ?? 'General';
    $commCatPalettes = ['blue', 'red', 'green', 'amber', 'purple', 'teal', 'indigo', 'rose'];
    $commCatColor    = $commCatPalettes[crc32($commCatName) % count($commCatPalettes)];

    // State + priority badge iconography.
    $commStateIcons = ['open' => 'circle-dot', 'progress' => 'loader', 'resolved' => 'circle-check', 'neutral' => 'circle'];
    $commPrioIcons  = ['high' => 'arrow-up', 'medium' => 'minus', 'low' => 'arrow-down'];
    $commStateIcon  = $commStateIcons[$post['state_tone']] ?? 'circle';
    $commPrioIcon   = $commPrioIcons[$post['priority_tone']] ?? 'minus';
@endphp

<article class="comm-post-v2 comm-post-v2--{{ $post['state_tone'] }}" id="ticket-{{ $post['id'] }}" aria-label="Reporte público: {{ $post['title'] }}">

    {{-- ── TOPBAR — category · público · time · ref · state · priority ── --}}
    <div class="comm-post-v2__topbar">
        <div class="comm-post-v2__topbar-start">
            <span class="comm-post-v2__chip comm-post-v2__chip--cat comm-post-v2__chip--{{ $commCatColor }}">
                @if ($post['category'] !== null)
                    <x-dynamic-component
                        :component="'lucide-'.($post['category']['icon'] ?: 'tag')"
                        class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
                    <span>{{ $post['category']['name'] }}</span>
                @else
                    <x-lucide-wrench class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
                    <span>General</span>
                @endif
            </span>
            <span class="comm-post-v2__chip comm-post-v2__chip--public">
                <x-lucide-eye class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
                <span>Reporte público</span>
            </span>
        </div>

        <div class="comm-post-v2__topbar-end">
            <span class="comm-post-v2__meta">
                <x-lucide-clock width="12" height="12" stroke-width="2" aria-hidden="true" />
                {{ $post['updated_ago'] }}
            </span>
            <span class="comm-post-v2__meta-dot" aria-hidden="true">·</span>
            <span class="comm-post-v2__meta-ref">{{ $post['ref'] }}</span>
            <span class="comm-badge comm-badge--state comm-badge--{{ $post['state_tone'] }}">
                <x-dynamic-component :component="'lucide-'.$commStateIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
                {{ $post['state_label'] }}
            </span>
            <span class="comm-badge comm-badge--priority comm-badge--priority-{{ $post['priority_tone'] }}">
                <x-dynamic-component :component="'lucide-'.$commPrioIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
                {{ $post['priority_label'] }}
            </span>
        </div>
    </div>

    {{-- ── BODY — content (left) + evidence (right) ── --}}
    <div class="comm-post-v2__body">

        <div class="comm-post-v2__main">

            <h3 class="comm-post-v2__title">{{ $post['title'] }}</h3>

            {{-- Meta chips: location + contextual tags --}}
            <div class="comm-post-v2__chips">
                @if ($post['location'] !== null)
                    <span class="comm-post-v2__pill">
                        <x-lucide-map-pin class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
                        <span class="comm-post-v2__pill-label">{{ $post['location']['room_code'] }}</span>
                    </span>
                    @if ($post['location']['building'] !== '')
                        <span class="comm-post-v2__pill">
                            <x-lucide-building-2 class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
                            <span class="comm-post-v2__pill-label">{{ $post['location']['building'] }}</span>
                        </span>
                    @endif
                    @if ($post['location']['floor'] !== '')
                        <span class="comm-post-v2__pill">
                            <x-lucide-layers class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
                            <span class="comm-post-v2__pill-label">Piso {{ $post['location']['floor'] }}</span>
                        </span>
                    @endif
                @endif

                @if ($post['is_recent'])
                    <span class="comm-tag comm-tag--recent">
                        <x-lucide-clock width="11" height="11" stroke-width="2.5" />
                        Reciente
                    </span>
                @endif
                @if ($post['has_media'])
                    <span class="comm-tag comm-tag--media">
                        <x-lucide-paperclip width="11" height="11" stroke-width="2.5" />
                        Con evidencia
                    </span>
                @endif
                @if ($post['is_resolved'])
                    <span class="comm-tag comm-tag--resolved">
                        <x-lucide-circle-check width="11" height="11" stroke-width="2.5" />
                        Resuelto
                    </span>
                @endif
            </div>

            {{-- Description panel (collapsible) --}}
            @if ($post['summary'] !== '')
                <details class="comm-post-v2__desc" open>
                    <summary class="comm-post-v2__desc-summary">
                        <span class="comm-post-v2__desc-title">Descripción</span>
                        <x-lucide-chevron-down class="comm-post-v2__desc-chevron" width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                    </summary>
                    <div class="comm-post-v2__desc-body">
                        <p class="comm-post-v2__desc-text">{{ $post['summary'] }}</p>
                    </div>
                </details>
            @endif

        </div>

        {{-- Evidence column --}}
        <div class="comm-post-v2__media">
            @if ($carCount > 0)
                <div class="comm-post-v2__media-frame comm-thumb-car" data-comm-car>

                    {{-- Slides --}}
                    @foreach ($carImgs as $carIdx => $carUrl)
                        <div class="comm-thumb-car__slide {{ $carIdx === 0 ? 'comm-thumb-car__slide--visible' : '' }}"
                             data-car-slide="{{ $carIdx }}"
                             data-community-media-frame>
                            <img
                                src="{{ $carUrl }}"
                                alt="Evidencia {{ $carIdx + 1 }} del reporte"
                                class="comm-post-v2__media-img"
                                loading="{{ $carIdx === 0 ? 'eager' : 'lazy' }}"
                                width="320"
                                height="240"
                                data-community-media-img
                            >
                            <div class="comm-post-v2__media-unavailable" data-community-media-fallback hidden aria-hidden="true">
                                <x-lucide-image-off width="26" height="26" stroke-width="1.5" />
                                <span>Vista previa no disponible</span>
                            </div>
                        </div>
                    @endforeach

                    {{-- Bottom gradient + counter overlay --}}
                    <div class="comm-post-v2__media-overlay" aria-hidden="true">
                        <span class="comm-post-v2__media-counter">1 / {{ $carCount }}</span>
                    </div>

                    {{-- Prev / Next arrows (only when >1 image) --}}
                    @if ($carCount > 1)
                        <button type="button"
                                class="comm-thumb-car__arrow comm-thumb-car__arrow--prev"
                                data-car-prev
                                aria-label="Imagen anterior">
                            <x-lucide-chevron-left width="14" height="14" stroke-width="2.5" />
                        </button>
                        <button type="button"
                                class="comm-thumb-car__arrow comm-thumb-car__arrow--next"
                                data-car-next
                                aria-label="Imagen siguiente">
                            <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                        </button>

                        {{-- Navigation dots --}}
                        <div class="comm-thumb-car__dots" aria-hidden="true">
                            @foreach ($carImgs as $carIdx => $carUrl)
                                <span class="comm-thumb-car__dot {{ $carIdx === 0 ? 'comm-thumb-car__dot--on' : '' }}"
                                      data-car-dot="{{ $carIdx }}"></span>
                            @endforeach
                        </div>
                    @endif

                </div>
            @else
                <div class="comm-post-v2__media-frame comm-post-v2__media-placeholder" aria-hidden="true">
                    <x-lucide-image width="30" height="30" stroke-width="1.5" />
                    <span>Sin evidencia</span>
                </div>
            @endif

            @if ($post['has_media'])
                <span class="comm-post-v2__attachments">
                    <x-lucide-paperclip width="12" height="12" stroke-width="2" aria-hidden="true" />
                    {{ $post['media_count'] }} {{ $post['media_count'] === 1 ? 'archivo adjunto' : 'archivos adjuntos' }}
                </span>
            @endif
        </div>

    </div>

    {{-- ── ACTION BAR — reactions + save + report (Community v2 + optimistic JS) ── --}}
    {{-- Screen-reader live region: JS uses this to announce action outcomes.   --}}
    <span class="sr-only"
          data-community-social-status
          aria-live="polite"
          aria-atomic="true"></span>

    <div class="comm-post-v2__actions">

        <div class="comm-post-v2__actions-group">

        {{-- Me interesa --}}
        @if (in_array('interested', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'interested']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="interested"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="true"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'interested']) }}"
                  data-reaction-modifier="interested">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-interested comm-action-btn--active comm-action-btn--interested"
                        data-community-action-button
                        aria-pressed="true"
                        aria-label="Quitar reacción Me interesa">
                    <x-lucide-star width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Me interesa</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['interested'] ?: '' }}</span>
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="interested"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="false"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'interested']) }}"
                  data-reaction-modifier="interested">
                @csrf
                <input type="hidden" name="type" value="interested">
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-interested"
                        data-community-action-button
                        aria-pressed="false"
                        aria-label="Marcar como Me interesa">
                    <x-lucide-star width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Me interesa</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['interested'] ?: '' }}</span>
                </button>
            </form>
        @endif

        {{-- También me pasa --}}
        @if (in_array('also_happens', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'also_happens']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="also_happens"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="true"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'also_happens']) }}"
                  data-reaction-modifier="also">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-also comm-action-btn--active comm-action-btn--also"
                        data-community-action-button
                        aria-pressed="true"
                        aria-label="Quitar reacción También me pasa">
                    <x-lucide-users width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>También me pasa</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['also_happens'] ?: '' }}</span>
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="also_happens"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="false"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'also_happens']) }}"
                  data-reaction-modifier="also">
                @csrf
                <input type="hidden" name="type" value="also_happens">
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-also"
                        data-community-action-button
                        aria-pressed="false"
                        aria-label="Marcar como También me pasa">
                    <x-lucide-users width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>También me pasa</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['also_happens'] ?: '' }}</span>
                </button>
            </form>
        @endif

        {{-- Lo vi --}}
        @if (in_array('seen', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'seen']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="seen"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="true"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'seen']) }}"
                  data-reaction-modifier="seen">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-seen comm-action-btn--active comm-action-btn--seen"
                        data-community-action-button
                        aria-pressed="true"
                        aria-label="Quitar reacción Lo vi">
                    <x-lucide-eye width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Lo vi</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['seen'] ?: '' }}</span>
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-community-social-form
                  data-community-action="reaction"
                  data-reaction-type="seen"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="false"
                  data-store-url="{{ route('reporter.community.reactions.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.reactions.destroy', [$post['id'], 'seen']) }}"
                  data-reaction-modifier="seen">
                @csrf
                <input type="hidden" name="type" value="seen">
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-seen"
                        data-community-action-button
                        aria-pressed="false"
                        aria-label="Marcar como Lo vi">
                    <x-lucide-eye width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Lo vi</span>
                    <span class="comm-action-btn__count" data-community-action-count>{{ $post['reactions']['counts']['seen'] ?: '' }}</span>
                </button>
            </form>
        @endif

        {{-- Guardar --}}
        @if ($post['saved'])
            <form method="POST"
                  action="{{ route('reporter.community.saves.destroy', $post['id']) }}"
                  data-community-social-form
                  data-community-action="save"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="true"
                  data-store-url="{{ route('reporter.community.saves.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.saves.destroy', $post['id']) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-saved comm-action-btn--active comm-action-btn--saved"
                        data-community-action-button
                        aria-pressed="true"
                        aria-label="Quitar de guardados">
                    <x-lucide-bookmark width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Guardado</span>
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.saves.store', $post['id']) }}"
                  data-community-social-form
                  data-community-action="save"
                  data-ticket-id="{{ $post['id'] }}"
                  data-active="false"
                  data-store-url="{{ route('reporter.community.saves.store', $post['id']) }}"
                  data-destroy-url="{{ route('reporter.community.saves.destroy', $post['id']) }}">
                @csrf
                <button type="submit"
                        class="comm-action-btn comm-action-btn--tint-saved"
                        data-community-action-button
                        aria-pressed="false"
                        aria-label="Guardar reporte">
                    <x-lucide-bookmark width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span class="comm-action-btn__label" data-community-action-label>Guardar</span>
                </button>
            </form>
        @endif

        </div>

        {{-- Reportar publicación --}}
        <div class="comm-post-v2__actions-end">
            @if ($post['viewer_report_pending'])
                <span class="comm-action-btn comm-action-btn--reported" aria-label="Reporte enviado">
                    <x-lucide-flag width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Reporte enviado</span>
                </span>
            @else
                <details class="comm-report-details">
                    <summary class="comm-report-summary comm-report-summary--danger" aria-label="Reportar publicación">
                        <x-lucide-flag width="15" height="15" stroke-width="2" />
                        <span class="comm-action-btn__label">Reportar</span>
                    </summary>
                    <div class="comm-report-form-wrap">
                        <form method="POST"
                              action="{{ route('reporter.community.reports.store', $post['id']) }}"
                              class="comm-report-form">
                            @csrf
                            <select name="reason" required class="comm-report-select" aria-label="Motivo del reporte">
                                <option value="" disabled selected>Selecciona un motivo...</option>
                                <option value="sensitive_info">Información sensible</option>
                                <option value="inappropriate_evidence">Evidencia no apta</option>
                                <option value="incorrect_info">Contenido incorrecto</option>
                                <option value="duplicate_or_confusing">Duplicado o confuso</option>
                                <option value="other">Otro motivo</option>
                            </select>
                            <textarea name="note"
                                      maxlength="500"
                                      rows="2"
                                      placeholder="Detalle adicional (opcional)"
                                      class="comm-report-textarea"
                                      aria-label="Detalle adicional"></textarea>
                            <button type="submit" class="comm-report-btn">Enviar reporte</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    </div>

    {{-- ── COMMENTS (Community v3) — count badge + latest + form ─── --}}
    <div class="comm-post-v2__comments">
        <details class="comm-comments-details">
            <summary class="comm-comments-summary" aria-label="Ver comentarios">
                <x-lucide-message-circle width="14" height="14" stroke-width="2" />
                <span class="comm-comments-summary__label">
                    Comentarios
                </span>
                @if ($post['comments']['count'] > 0)
                    <span class="comm-comments-summary__count">{{ $post['comments']['count'] }}</span>
                @endif
                <x-lucide-chevron-down class="comm-comments-summary__chevron" width="15" height="15" stroke-width="2.5" aria-hidden="true" />
            </summary>
            <div class="comm-comments-details__body">
                @include('reporter.community.partials.comments', ['post' => $post])
            </div>
        </details>
    </div>

    {{-- ── FOOTER — verification + anonymous authorship (no PII) ── --}}
    <div class="comm-post-v2__footer">
        <span class="comm-post-v2__footer-item comm-post-v2__footer-item--verified">
            <x-lucide-circle-check width="13" height="13" stroke-width="2.25" aria-hidden="true" />
            Verificación activa
        </span>
        <span class="comm-post-v2__meta-dot" aria-hidden="true">·</span>
        <span class="comm-post-v2__footer-item">Creado por usuario anónimo</span>
    </div>
</article>
