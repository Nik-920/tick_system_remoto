{{-- Reporter-safe "confirmed distinct" notice.
     Included by reporter/show.blade.php when $tracking->hasPrecheckNotice().
     Receives: $precheckNotice (array from ReporterTicketTrackingViewModel::$precheckNotice).
     Never exposes matched-ticket URL, description, evidence or reporter name.
--}}
@if ($precheckNotice !== null)
<section class="rep-panel rep-dup-notice rep-dup-notice--info" aria-labelledby="rep-precheck-title">
    <h2 class="rep-panel__title rep-dup-notice__title" id="rep-precheck-title">
        <x-lucide-link width="16" height="16" stroke-width="2" />
        Relacionado con otro reporte
    </h2>

    <p class="rep-dup-notice__intro">
        Al crear este ticket detectamos un posible reporte relacionado y
        confirmaste que se trata de un caso distinto.
    </p>

    <dl class="rep-dup-notice__meta">
        <div class="rep-dup-notice__row">
            <dt>Ticket similar</dt>
            <dd>{{ $precheckNotice['matchedTitle'] }}</dd>
        </div>
        <div class="rep-dup-notice__row">
            <dt>Estado</dt>
            <dd>{{ $precheckNotice['matchedState'] }}</dd>
        </div>
        @if ($precheckNotice['reason'] !== '')
            <div class="rep-dup-notice__row">
                <dt>Motivo detectado</dt>
                <dd>{{ $precheckNotice['reason'] }}</dd>
            </div>
        @endif
    </dl>
</section>
@endif
