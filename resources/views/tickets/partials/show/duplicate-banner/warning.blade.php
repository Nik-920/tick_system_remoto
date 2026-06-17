<div class="tickets-dup-alert">
    <div class="tickets-dup-banner">
        <div class="tickets-dup-title">
            @if ($vm->duplicateReviewStatus() === 'confirmed')
                ✅ Duplicado confirmado manualmente.
            @else
                ⚠️ Posible duplicado detectado por IA.
            @endif
        </div>
        <div class="tickets-dup-meta">
            <span class="tickets-dup-meta-item">Ticket similar: {{ $vm->duplicateMatchedTicket()?->title ?? 'N/A' }}</span>
            <span class="tickets-dup-meta-item">Estado: {{ $vm->duplicateMatchedTicket()?->state ?? 'N/A' }}</span>
            @can('reviewDuplicate', $ticket)
                <span class="tickets-dup-meta-item">Similitud: {{ $vm->duplicateEmbedding()?->similarity_score !== null ? number_format($vm->duplicateEmbedding()->similarity_score, 2) : 'N/A' }}</span>
            @endcan
            @if ($vm->duplicateMatchedTicket())
                <a href="{{ route('tickets.show', $vm->duplicateMatchedTicket()) }}" class="btn-secondary tickets-dup-link">Ver ticket</a>
            @endif
        </div>

        {{-- Manual review info --}}
        @if ($vm->duplicateReviewer() && $vm->duplicateReviewStatus())
            <div class="tickets-dup-reviewer">
                Revisado por <strong>{{ $vm->duplicateReviewer()->name ?? $vm->duplicateReviewer()->email }}</strong>
                el {{ $vm->fmtDate($vm->duplicateEmbedding()->reviewed_at) ?? 'N/A' }}.
                @if ($vm->duplicateEmbedding()->review_note)
                    Nota: <em>{{ $vm->duplicateEmbedding()->review_note }}</em>
                @endif
            </div>
        @endif

        @include('tickets.partials.show.duplicate-banner.explanation', [
            'ticket' => $ticket,
            'vm' => $vm,
            'duplicateExplanation' => $duplicateExplanation,
        ])

        {{-- Review actions --}}
        @can('reviewDuplicate', $ticket)
            <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
                  class="tickets-review-actions">
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                <div class="tickets-review-note-wrap">
                    <label class="tickets-review-label" for="review_note">Nota de revisión</label>
                    <textarea id="review_note" name="review_note" rows="2" maxlength="1000"
                              placeholder="Escribe una nota breve (opcional)"
                              class="tickets-review-note">{{ $vm->duplicateEmbedding()?->review_note }}</textarea>
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
