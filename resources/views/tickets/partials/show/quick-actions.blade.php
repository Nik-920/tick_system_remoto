{{-- ⑤ Acciones rápidas --}}
<section class="ticket-show__card" aria-labelledby="quick-actions-heading">
    <h2 id="quick-actions-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">5</span>
        Acciones rápidas
    </h2>

    <nav class="space-y-1" aria-label="Acciones disponibles">
        @can('updateState', $ticket)
            @if ($vm->canContinue() || $vm->canResolve())
                <a href="#update-state" class="ticket-show__action-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Registrar avance de atención
                </a>
            @endif
        @endcan

        @if ($vm->canEditOperational)
            <a href="#evidence" class="ticket-show__action-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Adjuntar evidencia
            </a>
        @endif

        <a href="#evidence" class="ticket-show__action-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.42 16.41a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Ver evidencias
        </a>

        <a href="#history" class="ticket-show__action-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            Ver historial completo
        </a>
    </nav>

    <div class="ticket-show__info-box">
        <div class="flex items-start gap-2 mb-2">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            <p class="font-semibold">Información importante</p>
        </div>
        <ul class="space-y-1 pl-6" role="list">
            <li class="list-disc">Revisa toda la información antes de realizar acciones.</li>
            <li class="list-disc">Agrega comentarios claros al cambiar el estado.</li>
            <li class="list-disc">Las evidencias ayudan a mantener un buen seguimiento.</li>
        </ul>
    </div>
</section>
