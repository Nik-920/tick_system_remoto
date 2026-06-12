@php
    $embedding      = $ticket->embedding;
    $matchedTicket  = $embedding?->matchedTicket;
    $reviewer       = $embedding?->reviewer;

    // Use effective_duplicate (respects human review_status override)
    $showDuplicateWarning = $embedding
        && $embedding->effective_duplicate
        && $matchedTicket
        && in_array($matchedTicket->state, ['open', 'in_progress'], true);

    $reviewStatus   = $embedding?->review_status;
@endphp

@if ($showDuplicateWarning)
    <div class="tickets-dup-alert">
        <div class="tickets-dup-banner">
            <div class="tickets-dup-title">
                @if ($reviewStatus === 'confirmed')
                    ✅ Duplicado confirmado manualmente.
                @else
                    ⚠️ Posible duplicado detectado por IA.
                @endif
            </div>
            <div class="tickets-dup-meta">
                <span class="tickets-dup-meta-item">Ticket similar: {{ $matchedTicket?->title ?? 'N/A' }}</span>
                <span class="tickets-dup-meta-item">Estado: {{ $matchedTicket?->state ?? 'N/A' }}</span>
                @can('reviewDuplicate', $ticket)
                    <span class="tickets-dup-meta-item">Similitud: {{ $embedding?->similarity_score !== null ? number_format($embedding->similarity_score, 2) : 'N/A' }}</span>
                @endcan
                @if ($matchedTicket)
                    <a href="{{ route('tickets.show', $matchedTicket) }}" class="btn-secondary tickets-dup-link">Ver ticket</a>
                @endif
            </div>

            {{-- Manual review info --}}
            @if ($reviewer && $reviewStatus)
                <div class="tickets-dup-reviewer">
                    Revisado por <strong>{{ $reviewer->name ?? $reviewer->email }}</strong>
                    el {{ $fmtDate($embedding->reviewed_at) ?? 'N/A' }}.
                    @if ($embedding->review_note)
                        Nota: <em>{{ $embedding->review_note }}</em>
                    @endif
                </div>
            @endif

            {{-- ===== AI EXPLANATION: ¿por qué fue marcado? ===== --}}
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

            {{-- Review actions --}}
            @can('reviewDuplicate', $ticket)
                <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
                      class="tickets-review-actions">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="tickets-review-note-wrap">
                        <label class="tickets-review-label" for="review_note">Nota de revisión</label>
                        <textarea id="review_note" name="review_note" rows="2" maxlength="1000"
                                  placeholder="Escribe una nota breve (opcional)"
                                  class="tickets-review-note">{{ $embedding?->review_note }}</textarea>
                    </div>
                    <div class="tickets-review-buttons">
                        <button type="submit" name="review_status" value="dismissed" class="btn-secondary tickets-review-btn tickets-review-btn--dismiss">
                            🚫 Marcar como no duplicado
                        </button>
                        <button type="submit" name="review_status" value="confirmed" class="btn-primary tickets-review-btn tickets-review-btn--confirm">
                            ✅ Confirmar duplicado
                        </button>
                    </div>
                </form>
                @error('review')
                    <p style="color:var(--color-danger); font-size:0.85rem;">{{ $message }}</p>
                @enderror
            @endcan
        </div>
    </div>
@elseif ($embedding && $embedding->isDismissedDuplicate())
    {{-- Show dismissed badge for authorized users only --}}
    @can('reviewDuplicate', $ticket)
        <div class="alert-success tickets-dup-dismissed">
            <span>🚫 Duplicado descartado manualmente.</span>
            @if ($reviewer)
                <span style="font-size:0.85rem; opacity:0.8;">
                    Por {{ $reviewer->name ?? $reviewer->email }}
                    el {{ $fmtDate($embedding->reviewed_at) ?? 'N/A' }}.
                    @if ($embedding->review_note) — <em>{{ $embedding->review_note }}</em> @endif
                </span>
            @endif
            {{-- Allow re-review --}}
            <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
                  class="tickets-review-actions">
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="review_note" value="{{ $embedding->review_note ?? '' }}">
                <button type="submit" name="review_status" value="confirmed" class="c-btn c-btn--ghost c-btn--sm tickets-review-btn">
                    ↩ Reabrir como duplicado
                </button>
            </form>
        </div>
    @endcan
@endif
