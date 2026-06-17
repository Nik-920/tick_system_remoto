@if (($duplicateExplanation['visible'] ?? false))
    <section class="tickets-dup-explain" aria-labelledby="duplicate-explanation-title">
        <h3 id="duplicate-explanation-title" class="tickets-dup-explain-title">
            ¿Por qué la IA lo marcó como posible duplicado?
        </h3>

        @can('reviewDuplicate', $ticket)
            @if (! is_null($duplicateExplanation['score']) || ! is_null($duplicateExplanation['similarity']))
                <div class="tickets-dup-explain-scores">
                    @if (! is_null($duplicateExplanation['score']))
                        <span class="tickets-dup-score">Score IA: {{ $duplicateExplanation['score'] }}/100</span>
                    @endif
                    @if (! is_null($duplicateExplanation['similarity']))
                        <span class="tickets-dup-score">Similitud: {{ number_format($duplicateExplanation['similarity'], 2) }}</span>
                    @endif
                </div>
            @endif
        @endcan

        @if ($duplicateExplanation['summary'] !== '')
            <p class="tickets-dup-explain-summary">{{ $duplicateExplanation['summary'] }}</p>
        @endif

        @if (! empty($duplicateExplanation['topReasons']))
            <ul class="tickets-dup-reasons" role="list">
                @foreach ($duplicateExplanation['topReasons'] as $reason)
                    <li class="tickets-dup-reason tickets-dup-reason--positive">
                        <span class="tickets-dup-reason-icon" aria-hidden="true">{{ $reason['icon'] }}</span>
                        <span class="tickets-dup-reason-body">
                            <span class="tickets-dup-reason-label">
                                {{ $reason['label'] }}
                                @if ($reason['points'] > 0)
                                    <span class="tickets-dup-reason-points">(+{{ $reason['points'] }})</span>
                                @endif
                            </span>
                            @if ($reason['detail'] !== '')
                                <span class="tickets-dup-reason-detail">{{ $reason['detail'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! empty($duplicateExplanation['warnings']))
            <ul class="tickets-dup-reasons tickets-dup-warnings" role="list">
                @foreach ($duplicateExplanation['warnings'] as $warning)
                    <li class="tickets-dup-reason tickets-dup-reason--warning">
                        <span class="tickets-dup-reason-icon" aria-hidden="true">{{ $warning['icon'] }}</span>
                        <span class="tickets-dup-reason-body">
                            <span class="tickets-dup-reason-label">{{ $warning['label'] }}</span>
                            @if ($warning['detail'] !== '')
                                <span class="tickets-dup-reason-detail">{{ $warning['detail'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @can('reviewDuplicate', $ticket)
            @if (! empty($duplicateExplanation['technicalDetails']))
                <details class="tickets-dup-tech">
                    <summary class="tickets-dup-tech-summary">Ver detalles técnicos</summary>
                    <ul class="tickets-dup-tech-list" role="list">
                        @foreach ($duplicateExplanation['technicalDetails'] as $detail)
                            <li class="tickets-dup-tech-item">
                                <span class="tickets-dup-tech-name">{{ $detail['label'] }}</span>
                                <span class="tickets-dup-tech-points">{{ $detail['points'] > 0 ? '+' : '' }}{{ $detail['points'] }}</span>
                                <span class="tickets-dup-tech-reason">{{ $detail['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @endcan
    </section>
@endif
