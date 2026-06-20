{{-- ── REAL POST CARD ──────────────────────────────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     No user data (reporter/assignee) is present in $post by design.
     Social actions: Me interesa / También me pasa / Lo vi / Guardar.
     No JS required — toggle via POST/DELETE forms.
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

<article class="comm-post comm-post--{{ $post['state_tone'] }}" id="ticket-{{ $post['id'] }}" aria-label="Reporte público: {{ $post['title'] }}">
    <div class="comm-post__body">

        {{-- Category icon + label --}}
        <div class="comm-post__category comm-post__category--{{ $commCatColor }}">
            @if ($post['category'] !== null)
                <span class="comm-post__category-icon" aria-hidden="true">
                    <x-dynamic-component
                        :component="'lucide-'.($post['category']['icon'] ?: 'tag')"
                        width="18"
                        height="18"
                        stroke-width="1.75"
                    />
                </span>
                <span class="comm-post__category-name">{{ $post['category']['name'] }}</span>
            @else
                <span class="comm-post__category-icon" aria-hidden="true">
                    <x-lucide-wrench width="18" height="18" stroke-width="1.75" />
                </span>
                <span class="comm-post__category-name">General</span>
            @endif
        </div>

        {{-- Main content --}}
        <div class="comm-post__content">

            {{-- Meta: "Reporte público · hace X tiempo" --}}
            <div class="comm-post__meta-row">
                <span class="comm-post__meta-label">Reporte público</span>
                <span class="comm-post__meta-dot" aria-hidden="true">·</span>
                <span class="comm-post__meta-time">{{ $post['updated_ago'] }}</span>
            </div>

            {{-- Title + state + priority badges --}}
            <div class="comm-post__title-row">
                <h3 class="comm-post__title-text">{{ $post['title'] }}</h3>
                <span class="comm-badge comm-badge--state comm-badge--{{ $post['state_tone'] }}">
                    <x-dynamic-component :component="'lucide-'.$commStateIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
                    {{ $post['state_label'] }}
                </span>
                <span class="comm-badge comm-badge--priority comm-badge--priority-{{ $post['priority_tone'] }}">
                    <x-dynamic-component :component="'lucide-'.$commPrioIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
                    {{ $post['priority_label'] }}
                </span>
            </div>

            {{-- Description summary --}}
            @if ($post['summary'] !== '')
                <p class="comm-post__summary">{{ $post['summary'] }}</p>
            @endif

            {{-- Location --}}
            @if ($post['location'] !== null)
                <div class="comm-post__loc-row">
                    <span class="comm-post__loc-pill">
                        <x-lucide-map-pin class="comm-post__loc-icon" width="12" height="12" stroke-width="2" />
                        <span class="comm-post__loc-label">{{ $post['location']['room_code'] }}</span>
                    </span>
                    @if ($post['location']['building'] !== '')
                        <span class="comm-post__loc-pill">
                            <x-lucide-building-2 class="comm-post__loc-icon" width="12" height="12" stroke-width="2" />
                            <span class="comm-post__loc-label">{{ $post['location']['building'] }}</span>
                        </span>
                    @endif
                    @if ($post['location']['floor'] !== '')
                        <span class="comm-post__loc-pill">
                            <x-lucide-layers class="comm-post__loc-icon" width="12" height="12" stroke-width="2" />
                            <span class="comm-post__loc-label">Piso {{ $post['location']['floor'] }}</span>
                        </span>
                    @endif
                </div>
            @endif

            {{-- Contextual tags --}}
            <div class="comm-post__tags">
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

        </div>

        {{-- Thumbnail / image carousel --}}
        <div class="comm-post__media">
            @if ($carCount > 0)
                <div class="comm-thumb-car" data-comm-car>

                    {{-- Slides --}}
                    @foreach ($carImgs as $carIdx => $carUrl)
                        <div class="comm-thumb-car__slide {{ $carIdx === 0 ? 'comm-thumb-car__slide--visible' : '' }}"
                             data-car-slide="{{ $carIdx }}">
                            <img
                                src="{{ $carUrl }}"
                                alt="Evidencia {{ $carIdx + 1 }} del reporte"
                                class="comm-post__thumb-img"
                                loading="{{ $carIdx === 0 ? 'eager' : 'lazy' }}"
                                width="144"
                                height="112"
                            >
                        </div>
                    @endforeach

                    {{-- Prev / Next arrows (only when >1 image) --}}
                    @if ($carCount > 1)
                        <button type="button"
                                class="comm-thumb-car__arrow comm-thumb-car__arrow--prev"
                                data-car-prev
                                aria-label="Imagen anterior">
                            <x-lucide-chevron-left width="12" height="12" stroke-width="2.5" />
                        </button>
                        <button type="button"
                                class="comm-thumb-car__arrow comm-thumb-car__arrow--next"
                                data-car-next
                                aria-label="Imagen siguiente">
                            <x-lucide-chevron-right width="12" height="12" stroke-width="2.5" />
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
                <div class="comm-post__thumb-placeholder" aria-hidden="true">
                    <x-lucide-image width="28" height="28" stroke-width="1.5" />
                </div>
            @endif
        </div>

    </div>

    {{-- ── COMMENTS (Community v3) — count badge + latest + form ─── --}}
    <div class="comm-post__comments-toggle">
        <details class="comm-comments-details">
            <summary class="comm-comments-summary" aria-label="Ver comentarios">
                <x-lucide-message-circle width="14" height="14" stroke-width="2" />
                <span class="comm-comments-summary__label">
                    Comentarios
                </span>
                @if ($post['comments']['count'] > 0)
                    <span class="comm-comments-summary__count">{{ $post['comments']['count'] }}</span>
                @endif
            </summary>
            <div class="comm-comments-details__body">
                @include('reporter.community.partials.comments', ['post' => $post])
            </div>
        </details>
    </div>

    {{-- ── ACTION BAR — reactions + save (Community v2) ──────────── --}}
    <div class="comm-post__actions">

        {{-- Me interesa --}}
        @if (in_array('interested', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'interested']) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--active comm-action-btn--interested"
                        aria-label="Quitar reacción Me interesa">
                    <x-lucide-heart width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Me interesa</span>
                    @if ($post['reactions']['counts']['interested'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['interested'] }}</span>
                    @endif
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}">
                @csrf
                <input type="hidden" name="type" value="interested">
                <button type="submit"
                        class="comm-action-btn"
                        aria-label="Marcar como Me interesa">
                    <x-lucide-heart width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Me interesa</span>
                    @if ($post['reactions']['counts']['interested'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['interested'] }}</span>
                    @endif
                </button>
            </form>
        @endif

        {{-- También me pasa --}}
        @if (in_array('also_happens', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'also_happens']) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--active comm-action-btn--also"
                        aria-label="Quitar reacción También me pasa">
                    <x-lucide-users width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">También me pasa</span>
                    @if ($post['reactions']['counts']['also_happens'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['also_happens'] }}</span>
                    @endif
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}">
                @csrf
                <input type="hidden" name="type" value="also_happens">
                <button type="submit"
                        class="comm-action-btn"
                        aria-label="Marcar como También me pasa">
                    <x-lucide-users width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">También me pasa</span>
                    @if ($post['reactions']['counts']['also_happens'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['also_happens'] }}</span>
                    @endif
                </button>
            </form>
        @endif

        {{-- Lo vi --}}
        @if (in_array('seen', $post['reactions']['user_types']))
            <form method="POST"
                  action="{{ route('reporter.community.reactions.destroy', [$post['id'], 'seen']) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--active comm-action-btn--seen"
                        aria-label="Quitar reacción Lo vi">
                    <x-lucide-eye width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Lo vi</span>
                    @if ($post['reactions']['counts']['seen'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['seen'] }}</span>
                    @endif
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.reactions.store', $post['id']) }}">
                @csrf
                <input type="hidden" name="type" value="seen">
                <button type="submit"
                        class="comm-action-btn"
                        aria-label="Marcar como Lo vi">
                    <x-lucide-eye width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Lo vi</span>
                    @if ($post['reactions']['counts']['seen'] > 0)
                        <span class="comm-action-btn__count">{{ $post['reactions']['counts']['seen'] }}</span>
                    @endif
                </button>
            </form>
        @endif

        {{-- Guardar --}}
        @if ($post['saved'])
            <form method="POST"
                  action="{{ route('reporter.community.saves.destroy', $post['id']) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="comm-action-btn comm-action-btn--active comm-action-btn--saved"
                        aria-label="Quitar de guardados">
                    <x-lucide-bookmark width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Guardado</span>
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ route('reporter.community.saves.store', $post['id']) }}">
                @csrf
                <button type="submit"
                        class="comm-action-btn"
                        aria-label="Guardar reporte">
                    <x-lucide-bookmark width="15" height="15" stroke-width="2" />
                    <span class="comm-action-btn__label">Guardar</span>
                </button>
            </form>
        @endif

        {{-- Reportar publicación --}}
        @if ($post['viewer_report_pending'])
            <span class="comm-action-btn comm-action-btn--reported" aria-label="Reporte enviado">
                <x-lucide-flag width="15" height="15" stroke-width="2" />
                <span class="comm-action-btn__label">Reporte enviado</span>
            </span>
        @else
            <details class="comm-report-details">
                <summary class="comm-report-summary comm-report-summary--accent" aria-label="Reportar publicación">
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

        <span class="comm-action-btn__ref">
            <span class="comm-action-btn__ref-text">{{ $post['ref'] }}</span>
            <x-lucide-copy class="comm-action-btn__ref-icon" width="11" height="11" stroke-width="2" aria-hidden="true" />
        </span>
    </div>
</article>
