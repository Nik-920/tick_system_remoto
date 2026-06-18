{{-- Reporter-safe duplicate notice.
     Included by reporter/show.blade.php when $tracking->hasDuplicateNotice().
     Receives: $duplicate (array from ReporterTicketTrackingViewModel::$duplicate).
     Never exposes matched-ticket URL, description, evidence or reporter name.
--}}
@if ($duplicate !== null)
<section class="rep-panel rep-dup-notice" aria-labelledby="rep-dup-title">
    <h2 class="rep-panel__title rep-dup-notice__title" id="rep-dup-title">
        <x-lucide-alert-triangle width="16" height="16" stroke-width="2" />
        Posible duplicado detectado por IA
    </h2>

    <p class="rep-dup-notice__intro">
        Este ticket podría estar relacionado con otro reporte similar.
        Esto ayuda al equipo a revisar incidencias conectadas más rápido.
        La revisión final la hace el equipo de mantenimiento.
    </p>

    @if ($duplicate['summary'] !== '')
        <p class="rep-dup-notice__summary">{{ $duplicate['summary'] }}</p>
    @endif

    <dl class="rep-dup-notice__meta">
        <div class="rep-dup-notice__row">
            <dt>Ticket similar</dt>
            <dd>{{ $duplicate['matchedTitle'] }}</dd>
        </div>
        <div class="rep-dup-notice__row">
            <dt>Estado</dt>
            <dd>{{ $duplicate['matchedState'] }}</dd>
        </div>
        @if ($duplicate['similarity'] !== null)
            <div class="rep-dup-notice__row">
                <dt>Similitud detectada</dt>
                <dd>{{ $duplicate['similarity'] }}</dd>
            </div>
        @endif
    </dl>

    @if (! empty($duplicate['topReasons']))
        <div class="rep-dup-notice__reasons">
            <p class="rep-dup-notice__reasons-title">¿Por qué la IA lo marcó?</p>
            <ul class="rep-dup-notice__list">
                @foreach ($duplicate['topReasons'] as $reason)
                    <li class="rep-dup-notice__item">
                        <span aria-hidden="true">{{ $reason['icon'] }}</span>
                        <span>
                            {{ $reason['label'] }}
                            @if (! empty($reason['detail']))
                                — {{ $reason['detail'] }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
@endif
