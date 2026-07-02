{{--
    Form to post a new private core comment.
    Variables: $ticket (Ticket)
--}}
<form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="tc-comment-form mt-4">
    @csrf
    <label for="tc-body-{{ $ticket->id }}" class="sr-only">Agregar comentario</label>
    <textarea
        id="tc-body-{{ $ticket->id }}"
        name="body"
        class="tc-comment-form__input c-field"
        rows="3"
        maxlength="2000"
        placeholder="Escribe un comentario interno…"
        required
    >{{ old('body') }}</textarea>
    @error('body')
        <p class="tc-comment-form__error text-sm text-red-500 mt-1">{{ $message }}</p>
    @enderror
    <div class="tc-comment-form__actions mt-3 flex justify-end">
        <button type="submit" class="tc-comment-form__submit c-btn c-btn--primary">
            <x-lucide-send width="15" height="15" stroke-width="2" />
            Comentar
        </button>
    </div>
</form>
