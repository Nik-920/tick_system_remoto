{{-- Sección de evidencias parametrizada (reporter o maintenance).
     Variables esperadas: $title, $tone, $count, $hasEvidence, $evidence, $avatarTone, $fallbackInitial, $vm --}}
<div>
    <div class="flex items-center gap-2 mb-3">
        <p class="ticket-show__subtitle">{{ $title }}</p>
        <span class="ticket-show__avatar ticket-show__avatar--{{ $tone }} w-5 h-5 text-[11px]">
            {{ $count }}
        </span>
    </div>

    @if ($hasEvidence)
        @include('tickets.partials.show.evidence-table', [
            'items' => $evidence,
            'avatarTone' => $avatarTone,
            'fallbackInitial' => $fallbackInitial,
        ])
    @else
        <x-empty-state message="No hay evidencias registradas.">
            <x-slot:icon>
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </x-slot:icon>
        </x-empty-state>
    @endif
</div>
