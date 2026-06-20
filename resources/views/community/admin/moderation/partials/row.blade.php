{{-- Single row in the community moderation queue table.
     Receives: $item (array from CommunityModerationQueueViewModel::$items)
--}}
<tr class="comm-mod-row {{ $item['community_visible'] ? 'comm-mod-row--visible' : 'comm-mod-row--hidden' }}">

    {{-- Ref --}}
    <td>
        <a href="{{ $item['show_url'] }}" class="comm-mod-ref-link">{{ $item['ref'] }}</a>
    </td>

    {{-- Título + fecha --}}
    <td>
        <span class="comm-mod-title-cell" title="{{ $item['title'] }}">{{ $item['title'] }}</span>
        <span class="comm-mod-date-soft">{{ $item['created_at'] }}</span>
    </td>

    {{-- Categoría --}}
    <td class="comm-mod-cell-secondary">
        {{ $item['category']['name'] ?? '—' }}
    </td>

    {{-- Ubicación --}}
    <td>
        @if($item['location'])
            <span class="comm-mod-location-code">{{ $item['location']['room_code'] }}</span>
            <span class="comm-mod-location-building">{{ $item['location']['building'] }}</span>
        @else
            <span class="comm-mod-cell-secondary">—</span>
        @endif
    </td>

    {{-- Estado + nota si no aparece en feed --}}
    <td>
        <span class="comm-mod-state-badge comm-mod-state-badge--{{ $item['state'] }}">
            {{ $item['state_label'] }}
        </span>
        @if($item['state_blocks_feed'])
        <span class="comm-mod-no-feed-note">No aparece en feed</span>
        @endif
    </td>

    {{-- Visibilidad comunitaria --}}
    <td>
        @if($item['community_visible'])
            <span class="comm-mod-badge comm-mod-badge--visible">Visible</span>
        @else
            <span class="comm-mod-badge comm-mod-badge--hidden">Oculto</span>
        @endif
    </td>

    {{-- Motivo / moderador (solo si oculto) --}}
    <td>
        @if(!$item['community_visible'] && $item['community_visibility_reason'])
            <p class="comm-mod-reason-text">{{ $item['community_visibility_reason'] }}</p>
            @if($item['community_hidden_at'])
                <p class="comm-mod-reason-meta">
                    {{ $item['community_hidden_at'] }}
                    @if($item['hidden_by_name'])
                        · {{ $item['hidden_by_name'] }}
                    @endif
                </p>
            @endif
        @else
            <span class="comm-mod-cell-secondary">—</span>
        @endif
    </td>

    {{-- Reportes de usuarios --}}
    <td class="comm-mod-reports-cell">
        @if($item['pending_reports_count'] > 0)
            <span class="comm-mod-reports-badge">
                {{ $item['pending_reports_count'] }} pendiente{{ $item['pending_reports_count'] !== 1 ? 's' : '' }}
            </span>
            @if($item['latest_pending_report'] !== null)
                <span class="comm-mod-reports-target">{{ $item['latest_pending_report']['target_label'] }}</span>
                <p class="comm-mod-reports-reason">{{ $item['latest_pending_report']['reason_label'] }}</p>
                @if($item['latest_pending_report']['comment_excerpt'] !== null)
                    <p class="comm-mod-reports-excerpt">&ldquo;{{ $item['latest_pending_report']['comment_excerpt'] }}&rdquo;</p>
                @endif
                @if($item['latest_pending_report']['note'] !== null)
                    <p class="comm-mod-reports-note">{{ $item['latest_pending_report']['note'] }}</p>
                @endif
                <details class="comm-mod-report-review-details">
                    <summary class="comm-mod-btn-secondary">Resolver reporte</summary>
                    <div class="comm-mod-hide-form-wrap">
                        <form method="POST"
                              action="{{ route('admin.community.reports.review', $item['latest_pending_report']['id']) }}"
                              class="comm-mod-hide-form">
                            @csrf
                            @method('PATCH')
                            <select name="status" required class="comm-mod-reason-input" aria-label="Resolución">
                                <option value="" disabled selected>Selecciona resolución...</option>
                                <option value="resolved">Resuelto — procede</option>
                                <option value="dismissed">Descartado — no procede</option>
                            </select>
                            <input type="text"
                                   name="resolution_note"
                                   maxlength="500"
                                   placeholder="Nota de resolución (opcional)"
                                   class="comm-mod-reason-input"
                                   aria-label="Nota de resolución">
                            <button type="submit" class="comm-mod-btn-hide">Confirmar</button>
                        </form>
                    </div>
                </details>
            @endif
        @else
            <span class="comm-mod-cell-secondary">—</span>
        @endif
    </td>

    {{-- Acciones --}}
    <td class="comm-mod-actions-cell">
        <div class="comm-mod-actions">

            <a href="{{ $item['show_url'] }}" class="comm-mod-btn-view">Ver ficha</a>

            @if($item['community_visible'])
                {{-- Formulario de ocultamiento expandible sin JS --}}
                <details class="comm-mod-hide-details">
                    <summary class="comm-mod-btn-secondary">Ocultar</summary>
                    <div class="comm-mod-hide-form-wrap">
                        <form method="POST"
                              action="{{ route('tickets.community.hide', $item['id']) }}"
                              class="comm-mod-hide-form">
                            @csrf
                            @method('PATCH')
                            <input type="text"
                                   name="reason"
                                   required
                                   maxlength="255"
                                   placeholder="Motivo de ocultamiento (requerido)"
                                   class="comm-mod-reason-input"
                                   aria-label="Motivo de ocultamiento">
                            <button type="submit" class="comm-mod-btn-hide">Confirmar</button>
                        </form>
                    </div>
                </details>
            @else
                <form method="POST"
                      action="{{ route('tickets.community.restore', $item['id']) }}"
                      class="comm-mod-restore-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="comm-mod-btn-restore">Restaurar</button>
                </form>
            @endif

        </div>
    </td>

</tr>
