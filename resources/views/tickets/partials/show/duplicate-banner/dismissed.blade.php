@can('reviewDuplicate', $ticket)
    <div class="alert-success tickets-dup-dismissed">
        <span>🚫 Duplicado descartado manualmente.</span>
        @if ($vm->duplicateReviewer())
            <span style="font-size:0.85rem; opacity:0.8;">
                Por {{ $vm->duplicateReviewer()->name ?? $vm->duplicateReviewer()->email }}
                el {{ $vm->fmtDate($vm->duplicateEmbedding()->reviewed_at) ?? 'N/A' }}.
                @if ($vm->duplicateEmbedding()->review_note) — <em>{{ $vm->duplicateEmbedding()->review_note }}</em> @endif
            </span>
        @endif
        {{-- Allow re-review --}}
        <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
              class="tickets-review-actions">
            @csrf
            @method('PATCH')
            <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
            <input type="hidden" name="review_note" value="{{ $vm->duplicateEmbedding()->review_note ?? '' }}">
            <button type="submit" name="review_status" value="confirmed" class="c-btn c-btn--ghost c-btn--sm tickets-review-btn">
                ↩ Reabrir como duplicado
            </button>
        </form>
    </div>
@endcan
