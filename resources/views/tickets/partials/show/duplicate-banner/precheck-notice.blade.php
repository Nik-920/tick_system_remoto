{{-- Soft, read-only notice: the reporter was warned about a related ticket at
     creation time and confirmed this is a distinct case. Independent of the
     AI duplicate lane (is_duplicate) — never blocks the flow, no review
     actions. Visible to all viewers of this show page (mirrors the existing
     duplicate warning's visibility: matched title/state/reason are already
     unguarded there and on the create-ticket form). --}}
<div class="tickets-dup-alert tickets-dup-alert--info">
    <div class="tickets-dup-banner tickets-dup-banner--info">
        <div class="tickets-dup-title">
            🔗 Relacionado con otro reporte
        </div>
        <p class="tickets-dup-precheck-note">
            Al crear este ticket se detectó un posible reporte relacionado y el
            reportante confirmó que se trata de un caso distinto.
        </p>
        <div class="tickets-dup-meta">
            <span class="tickets-dup-meta-item">Ticket similar: {{ $vm->precheckMatchedTicket()?->title ?? 'N/A' }}</span>
            <span class="tickets-dup-meta-item">Estado: {{ $vm->precheckMatchedTicket()?->state ?? 'N/A' }}</span>
            @if ($vm->precheckReason())
                <span class="tickets-dup-meta-item">Motivo detectado: {{ $vm->precheckReason() }}</span>
            @endif
            <span class="tickets-dup-meta-item">Confirmado el {{ $vm->fmtDate($vm->precheckConfirmedAt()) ?? 'N/A' }}</span>
            @if ($vm->precheckMatchedTicket())
                <a href="{{ route('tickets.show', $vm->precheckMatchedTicket()) }}" class="btn-secondary tickets-dup-link">Ver ticket</a>
            @endif
        </div>
    </div>
</div>
