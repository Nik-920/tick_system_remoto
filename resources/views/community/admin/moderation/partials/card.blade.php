{{-- Single card in the community moderation queue.
     Receives: $item (array from CommunityModerationQueueViewModel::$items)
--}}
<article class="adm-comm-card adm-comm-card--{{ $item['state'] }}"
         aria-label="Ticket {{ $item['ref'] }}">

    <header class="adm-comm-card__topbar">
        <div class="adm-comm-card__meta">
            <a href="{{ $item['show_url'] }}" class="adm-comm-card__ref">{{ $item['ref'] }}</a>
            <span class="adm-comm-card__date">{{ $item['created_at_label'] }}</span>
        </div>
        <div class="adm-comm-card__badges">
            <span class="comm-mod-state-badge comm-mod-state-badge--{{ $item['state'] }}">
                {{ $item['state_label'] }}
            </span>
            @if($item['state_blocks_feed'])
                <span class="adm-comm-badge adm-comm-badge--feed-off">No aparece en feed</span>
            @endif
            @if($item['community_visible'])
                <span class="comm-mod-badge comm-mod-badge--visible">Visible</span>
            @else
                <span class="comm-mod-badge comm-mod-badge--hidden">Oculto</span>
            @endif
        </div>
    </header>

    <div class="adm-comm-card__body">
        <h3 class="adm-comm-card__title">{{ $item['title'] }}</h3>
        <div class="adm-comm-card__chips">
            @if($item['category'])
                <span class="adm-comm-chip">{{ $item['category']['name'] }}</span>
            @endif
            @if($item['location'])
                <span class="adm-comm-chip adm-comm-chip--location">{{ $item['location']['room_code'] }}</span>
                @if($item['location']['building'])
                    <span class="adm-comm-chip adm-comm-chip--building">{{ $item['location']['building'] }}</span>
                @endif
            @endif
        </div>
    </div>

    @if($item['has_context'] ?? false)
        <section class="adm-comm-card__context" aria-label="Contexto de moderación">
            @if(! $item['community_visible'] && $item['community_visibility_reason'])
                <div class="adm-comm-context-block adm-comm-context-block--hide">
                    <p class="adm-comm-context-reason">{{ $item['community_visibility_reason'] }}</p>
                    @if($item['hidden_by_name'] || $item['community_hidden_at'])
                        <p class="adm-comm-context-meta">
                            @if($item['community_hidden_at']){{ $item['community_hidden_at'] }}@endif
                            @if($item['hidden_by_name'] && $item['community_hidden_at']) · @endif
                            @if($item['hidden_by_name']){{ $item['hidden_by_name'] }}@endif
                        </p>
                    @endif
                </div>
            @endif

            @if($item['pending_reports_count'] > 0)
                <div class="adm-comm-context-block adm-comm-context-block--reports">
                    <p class="adm-comm-reports-summary">
                        {{ $item['pending_reports_count'] }} pendiente{{ $item['pending_reports_count'] !== 1 ? 's' : '' }}
                    </p>
                    @if($item['latest_pending_report'] !== null)
                        <p class="adm-comm-reports-target">
                            {{ $item['latest_pending_report']['target_label'] }}
                            · {{ $item['latest_pending_report']['reason_label'] }}
                        </p>
                        @if($item['latest_pending_report']['comment_excerpt'] !== null)
                            <p class="adm-comm-reports-excerpt">&ldquo;{{ $item['latest_pending_report']['comment_excerpt'] }}&rdquo;</p>
                        @endif
                        @if($item['latest_pending_report']['note'] !== null)
                            <p class="adm-comm-reports-note">{{ $item['latest_pending_report']['note'] }}</p>
                        @endif
                        <details class="adm-comm-report-details">
                            <summary class="adm-comm-btn adm-comm-btn--review">Resolver reporte</summary>
                            <div class="adm-comm-form-wrap">
                                <form method="POST"
                                      action="{{ $item['review_report_url'] }}"
                                      class="adm-comm-form">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" required class="adm-comm-form-input" aria-label="Resolución">
                                        <option value="" disabled selected>Selecciona resolución...</option>
                                        <option value="resolved">Resuelto — procede</option>
                                        <option value="dismissed">Descartado — no procede</option>
                                    </select>
                                    <input type="text"
                                           name="resolution_note"
                                           maxlength="500"
                                           placeholder="Nota de resolución (opcional)"
                                           class="adm-comm-form-input"
                                           aria-label="Nota de resolución">
                                    <button type="submit" class="adm-comm-btn adm-comm-btn--confirm">Confirmar</button>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>
            @endif
        </section>
    @endif

    <footer class="adm-comm-card__actions">
        <a href="{{ $item['show_url'] }}"
           class="adm-comm-btn adm-comm-btn--view">Ver ficha</a>

        @if($item['community_visible'])
            <details class="adm-comm-card__visibility-panel">
                <summary class="adm-comm-btn adm-comm-btn--hide">Ocultar</summary>
                <div class="adm-comm-form-wrap">
                    <form method="POST"
                          action="{{ $item['hide_url'] }}"
                          class="adm-comm-form">
                        @csrf
                        @method('PATCH')
                        <label for="adm-comm-hide-reason-{{ $item['id'] }}" class="adm-comm-form-label">
                            Motivo de ocultamiento
                        </label>
                        <input type="text"
                               id="adm-comm-hide-reason-{{ $item['id'] }}"
                               name="reason"
                               required
                               maxlength="255"
                               placeholder="Ej.: Contenido duplicado, información sensible..."
                               class="adm-comm-form-input"
                               aria-label="Motivo de ocultamiento">
                        <button type="submit" class="adm-comm-btn adm-comm-btn--confirm-hide">Confirmar ocultamiento</button>
                    </form>
                </div>
            </details>
        @else
            <form method="POST"
                  action="{{ $item['restore_url'] }}"
                  class="adm-comm-restore-form">
                @csrf
                @method('PATCH')
                <button type="submit" class="adm-comm-btn adm-comm-btn--restore">Restaurar</button>
            </form>
        @endif
    </footer>

</article>
