{{-- ⑨ Visibilidad en Comunidad — solo visible para admin/super_admin --}}
@can('moderateCommunityVisibility', $ticket)
<section class="ticket-show__card" aria-labelledby="community-visibility-heading" id="community-visibility">
    <h2 id="community-visibility-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">9</span>
        Visibilidad en Comunidad
    </h2>

    {{-- Estado actual --}}
    <div class="mb-4">
        @if ($vm->isCommunityVisible())
            <span class="ts-badge ts-badge--resolved">
                <span class="ts-badge__dot" aria-hidden="true"></span>
                Visible en Comunidad
            </span>
            <p class="ticket-show__value--soft mt-2 text-sm">
                Este ticket aparece en el feed de Comunidad si su estado lo permite.
            </p>
        @else
            <span class="ts-badge ts-badge--neutral">
                <span class="ts-badge__dot" aria-hidden="true"></span>
                Oculto en Comunidad
            </span>
            @if ($vm->communityVisibilityReason())
                <dl class="mt-3 space-y-1">
                    <div>
                        <dt class="ticket-show__label">Motivo</dt>
                        <dd class="ticket-show__value--soft text-sm">{{ $vm->communityVisibilityReason() }}</dd>
                    </div>
                    @if ($vm->communityHiddenAtLabel())
                        <div>
                            <dt class="ticket-show__label">Ocultado el</dt>
                            <dd class="ticket-show__value--soft text-sm">{{ $vm->communityHiddenAtLabel() }}</dd>
                        </div>
                    @endif
                </dl>
            @endif
        @endif
    </div>

    {{-- Formulario: ocultar (solo si actualmente visible) --}}
    @if ($vm->isCommunityVisible())
        <form method="POST"
              action="{{ route('tickets.community.hide', $ticket) }}"
              class="mt-4">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label for="community_hide_reason" class="ticket-show__field-label">
                    Motivo de ocultamiento <span aria-hidden="true" class="text-red-500">*</span>
                </label>
                <textarea id="community_hide_reason"
                          name="reason"
                          rows="2"
                          maxlength="255"
                          class="ticket-show__control{{ $errors->has('reason') ? ' border-red-400' : '' }}"
                          placeholder="Ej.: Información sensible, reporte duplicado, evidencia no apta...">{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="ticket-show__error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="ticket-show__btn ticket-show__btn--ghost">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-4.477-10-10 0-1.274.24-2.494.675-3.615M6.343 6.343A8 8 0 0120 12c0 1.274-.24 2.494-.675 3.615M3 3l18 18"/>
                </svg>
                Ocultar de Comunidad
            </button>
        </form>
    @else
        {{-- Formulario: restaurar (solo si actualmente oculto) --}}
        <form method="POST"
              action="{{ route('tickets.community.restore', $ticket) }}"
              class="mt-2">
            @csrf
            @method('PATCH')

            <button type="submit"
                    class="ticket-show__btn ticket-show__btn--success">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Restaurar en Comunidad
            </button>
        </form>
    @endif
</section>
@endcan
