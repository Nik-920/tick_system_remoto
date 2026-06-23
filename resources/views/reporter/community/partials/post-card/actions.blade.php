{{-- post-card/actions: Me interesa / También me pasa / Lo vi / Guardar + Reportar.
     Every action is a POST/DELETE form (progressive enhancement — works without JS).
     community-social-actions.js upgrades these to JSON fetch calls.
     Tint classes (--tint-*) are stable and never toggled by JS; active classes
     (--active --interested/--also/--seen/--saved) are toggled by JS. --}}
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

    {{-- Guardar (no count rendered — optimistic JS skips save counts) --}}
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

    @include('reporter.community.partials.post-card.report-action', ['post' => $post])

</div>
