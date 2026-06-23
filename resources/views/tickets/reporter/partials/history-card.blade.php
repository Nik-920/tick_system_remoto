{{--
    History card v2 — one closed ticket in the reporter's history board.
    Variables: $t (array from ReporterTicketHistoryQuery::shapeRow), $loopIndex (int)
--}}
<article class="rep-history-card rep-tone-{{ $t['status_tone'] }}"
         id="ticket-{{ $t['id'] }}"
         aria-labelledby="rep-hist-title-{{ $loopIndex }}">

    {{-- Live region for accessible reaction feedback --}}
    <span class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-community-social-status></span>

    {{-- ── Top row: ref + badges ── --}}
    <div class="rep-history-card__top">
        <span class="rep-history-card__ref">{{ $t['ref'] }}</span>
        <div class="rep-history-card__badges">
            <span class="rep-card__status-badge rep-status--{{ $t['status'] }}">
                <x-dynamic-component :component="'lucide-' . $t['status_icon']" width="12" height="12" stroke-width="2.5" aria-hidden="true" />
                {{ $t['status_label'] }}
            </span>
            <span class="rep-card__prio-badge rep-tone-{{ $t['priority'] }}">
                <x-lucide-alert-circle width="12" height="12" stroke-width="2.5" aria-hidden="true" />
                {{ $t['priority_label'] }} Prioridad
            </span>
        </div>
    </div>

    {{-- ── Title ── --}}
    <h3 class="rep-history-card__title" id="rep-hist-title-{{ $loopIndex }}">{{ $t['title'] }}</h3>

    {{-- ── Metadata: building · room code · category ── --}}
    <div class="rep-history-card__meta">
        <x-lucide-building-2 width="13" height="13" stroke-width="2" aria-hidden="true" />
        <span>{{ $t['location'] }}</span>
        @if ($t['type'] !== '')
            <span class="rep-history-card__meta-sep" aria-hidden="true"></span>
            <x-lucide-map-pin width="13" height="13" stroke-width="2" aria-hidden="true" />
            <span>{{ $t['type'] }}</span>
        @endif
        <span class="rep-history-card__meta-sep" aria-hidden="true"></span>
        <x-lucide-tag width="13" height="13" stroke-width="2" aria-hidden="true" />
        <span>Categoría: {{ $t['category'] }}</span>
    </div>

    {{-- ── Divider ── --}}
    <div class="rep-history-card__divider" aria-hidden="true"></div>

    {{-- ── Bottom row: dates + social stats + actions ── --}}
    <div class="rep-history-card__bottom">

        <dl class="rep-history-card__dates">
            <div class="rep-history-card__date">
                <dt class="rep-history-card__date-label">
                    <x-lucide-calendar width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Creado
                </dt>
                <dd class="rep-history-card__date-value">{{ $t['created'] }}</dd>
            </div>
            <div class="rep-history-card__date">
                <dt class="rep-history-card__date-label rep-history-card__date-label--closed">
                    <x-dynamic-component :component="'lucide-' . $t['status_icon']" width="13" height="13" stroke-width="2" aria-hidden="true" />
                    {{ $t['status_label'] }}
                </dt>
                <dd class="rep-history-card__date-value">{{ $t['closed'] }}</dd>
            </div>
        </dl>

        <div class="rep-history-card__foot-right">

            {{-- Social stats: heart + comments --}}
            <div class="rep-card__stats">
                {{-- Heart — Me interesa reaction --}}
                <form method="POST"
                      action="{{ $t['viewer_reacted'] ? $t['reaction_destroy_url'] : $t['reaction_store_url'] }}"
                      data-community-social-form
                      data-community-action="reaction"
                      data-reaction-type="interested"
                      data-ticket-id="{{ $t['id'] }}"
                      data-active="{{ $t['viewer_reacted'] ? 'true' : 'false' }}"
                      data-store-url="{{ $t['reaction_store_url'] }}"
                      data-destroy-url="{{ $t['reaction_destroy_url'] }}"
                      data-reaction-modifier="interested"
                      class="rep-card__reaction-form">
                    @csrf
                    @if ($t['viewer_reacted'])
                        @method('DELETE')
                    @endif
                    <button type="submit"
                            class="rep-card__stat-btn {{ $t['viewer_reacted'] ? 'rep-card__stat-btn--active' : '' }}"
                            aria-pressed="{{ $t['viewer_reacted'] ? 'true' : 'false' }}"
                            aria-label="{{ $t['viewer_reacted'] ? 'Quitar reacción Me interesa' : 'Marcar como Me interesa' }}"
                            data-community-action-button>
                        <x-lucide-heart width="15" height="15" stroke-width="2" aria-hidden="true" />
                        <span data-community-action-count>{{ $t['reactions_count'] ?: '' }}</span>
                    </button>
                </form>

                {{-- Comments — opens the comments modal via JS --}}
                <button type="button"
                        class="rep-card__stat-btn rep-card__comment-btn"
                        data-reporter-ticket-comments-trigger
                        data-ticket-id="{{ $t['id'] }}"
                        data-ticket-ref="{{ $t['ref'] }}"
                        data-comments-url="{{ $t['comments_url'] }}"
                        data-comments-store-url="{{ $t['comments_store_url'] }}"
                        aria-haspopup="dialog"
                        aria-controls="reporter-ticket-comments-modal"
                        aria-expanded="false"
                        aria-label="{{ $t['comments_count'] === 1 ? '1 comentario' : $t['comments_count'].' comentarios' }}, abrir">
                    <x-lucide-message-circle width="15" height="15" stroke-width="2" aria-hidden="true" />
                    <span>{{ $t['comments_count'] ?: '' }}</span>
                    <span class="sr-only">Ver comentarios del ticket {{ $t['ref'] }}</span>
                </button>
            </div>

            {{-- Actions: Ver detalle + kebab --}}
            <div class="rep-history-card__actions">
                <a href="{{ route('reporter.tickets.show', $t['id']) }}"
                   class="rep-btn rep-btn--ghost rep-history-card__detail">
                    Ver detalle
                    <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                </a>

                <div class="rep-kebab" data-rep-kebab>
                    <button type="button" class="rep-kebab__btn"
                            aria-label="Más acciones para {{ $t['title'] }}"
                            aria-haspopup="true" aria-expanded="false" data-rep-kebab-btn>
                        <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                    </button>
                    <div class="rep-kebab__menu" hidden data-rep-kebab-menu>
                        <a href="{{ route('reporter.tickets.show', $t['id']) }}" class="rep-kebab__item">
                            <x-lucide-eye width="15" height="15" stroke-width="2" aria-hidden="true" /> Ver detalle
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </div>
</article>
