{{-- ③ Actualizar estado --}}
@can('updateState', $ticket)
    @if (count($availableTransitions) > 0)
        <section id="update-state" class="ticket-show__card" aria-labelledby="update-state-heading">
            <h2 id="update-state-heading" class="ticket-show__title">
                <span class="ticket-show__section-number" aria-hidden="true">3</span>
                Actualizar estado
            </h2>

            <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="tickets-once-form space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                <div>
                    <label for="to_state" class="ticket-show__field-label">Nuevo estado *</label>
                    <select id="to_state" name="to_state" required class="ticket-show__control sm:max-w-xs">
                        <option value="">Selecciona estado</option>
                        @foreach ($availableTransitions as $state)
                            <option value="{{ $state }}" @selected(old('to_state') === $state)>
                                {{ $stateLabels[$state] ?? ucfirst(str_replace('_', ' ', $state)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('to_state')
                        <p class="ticket-show__error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="comment" class="ticket-show__field-label">Comentario</label>
                    <textarea id="comment" name="comment" rows="3" maxlength="1000"
                              placeholder="Explica brevemente por qué cambias el estado o qué acción se realizó"
                              class="ticket-show__control">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="ticket-show__error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        Actualizar estado
                    </button>
                </div>
            </form>
        </section>
    @endif
@endcan
